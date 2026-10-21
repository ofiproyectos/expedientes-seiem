<?php

namespace App\Http\Controllers;

use App\Enums\ExpedienteStatus;
use App\Models\AccessGrant;
use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\Expediente;
use App\Models\User;
use App\Support\Audit;
use App\Support\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExpedienteController extends Controller
{
    public const SORTS = [
        'recientes' => ['created_at', 'desc', 'Más recientes'],
        'antiguos' => ['created_at', 'asc', 'Más antiguos'],
        'titulo' => ['name', 'asc', 'Título (A–Z)'],
        'titulo_desc' => ['name', 'desc', 'Título (Z–A)'],
        'actualizados' => ['updated_at', 'desc', 'Última modificación'],
    ];

    /** RF-BUS-001/002/003: búsqueda, filtros combinables, ordenamiento y paginación dentro del alcance autorizado. */
    public function index(Request $request)
    {
        $request->validate([
            'q' => 'nullable|string|max:150',
            'status' => ['nullable', Rule::enum(ExpedienteStatus::class)],
            'type' => 'nullable|string|max:100',
            'responsible' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'document_type' => ['nullable', Rule::in(config('expedientes.document_types'))],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'per_page' => Listing::perPageRule(),
        ]);
        $user = $request->user();
        [$column, $direction] = self::SORTS[$request->input('sort', 'recientes')];
        $expedientes = Expediente::visibleTo($user)->with('responsible')->withCount('documents')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('folio', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%')->orWhere('type', 'like', '%'.$request->q.'%')->orWhereHas('responsible', fn ($u) => $u->where('name', 'like', '%'.$request->q.'%'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('responsible'), fn ($q) => $q->where('responsible_id', $request->responsible))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->when($request->filled('document_type'), fn ($q) => $q->whereHas('documents', fn ($d) => $d->where('type', $request->document_type)))
            ->orderBy($column, $direction)->orderBy('id', $direction)
            ->paginate(Listing::perPage($request))->withQueryString();

        $visible = Expediente::visibleTo($user);
        $types = (clone $visible)->distinct()->orderBy('type')->pluck('type');
        $responsibles = User::whereIn('id', (clone $visible)->select('responsible_id'))->orderBy('name')->get(['id', 'name']);
        $sharedDocuments = Document::with('latestVersion', 'expediente')
            ->whereIn('id', AccessGrant::active()->where('user_id', $user->id)->whereNotNull('document_id')->select('document_id'))->get();

        return view('expedientes.index', compact('expedientes', 'sharedDocuments', 'types', 'responsibles'));
    }

    /** RF-EXP-001: solo LOADER; folio único generado; queda en borrador y su creador como responsable. */
    public function store(Request $request)
    {
        Gate::authorize('create', Expediente::class);
        $expediente = Expediente::create([...$this->data($request), 'folio' => (string) Str::uuid(), 'responsible_id' => $request->user()->id, 'status' => ExpedienteStatus::Borrador]);
        Audit::record('expediente.created', "Creación del expediente {$expediente->folio}", $expediente, ['name' => $expediente->name, 'type' => $expediente->type]);

        return redirect()->route('expedientes.show', $expediente)->with('status', 'Expediente creado en borrador.');
    }

    public function show(Request $request, Expediente $expediente)
    {
        Gate::authorize('view', $expediente);
        $user = $request->user();
        $expediente->load(['responsible', 'documents' => fn ($q) => $q->orderBy('name'), 'documents.versions' => fn ($q) => $q->with('uploader')->orderByDesc('version')]);
        $can = [
            'edit' => Gate::allows('update', $expediente),
            'status' => Gate::allows('changeStatus', $expediente),
            'history' => Gate::allows('viewHistory', $expediente),
            'share' => Gate::allows('share', $expediente),
            'audit' => Gate::allows('viewAudit', $expediente),
        ];
        $retired = $can['history'] ? $expediente->documents()->onlyTrashed()->with('retiredBy', 'latestVersion')->latest('deleted_at')->get() : collect();
        $grants = $can['share'] ? $expediente->grants()->with('user', 'document', 'grantor', 'revoker')->latest()->get() : collect();
        $readers = $can['share'] ? User::where('is_active', true)->where('role', 'READER')->orderBy('name')->get() : collect();
        $statusHistory = $can['status'] || $can['audit'] ? AuditEvent::with('user')->where('action', 'expediente.status_changed')->where('resource_type', 'expediente')->where('resource_id', $expediente->id)->orderByDesc('id')->get() : collect();
        $history = $can['audit'] ? AuditEvent::with('user')->where('expediente_id', $expediente->id)->orderByDesc('id')->limit(200)->get() : collect();

        return view('expedientes.show', compact('expediente', 'can', 'retired', 'grants', 'readers', 'statusHistory', 'history'));
    }

    /** RF-EXP-002: LOADER responsable y expediente editable; el folio y el identificador interno no cambian. */
    public function update(Request $request, Expediente $expediente)
    {
        Gate::authorize('update', $expediente);
        $before = $expediente->only('name', 'type', 'description');
        $expediente->update($this->data($request));
        $changed = array_keys(array_diff_key($expediente->getChanges(), array_flip(['updated_at'])));
        if ($changed) {
            Audit::record('expediente.updated', "Modificación de metadatos del expediente {$expediente->folio}", $expediente, ['fields' => $changed, 'before' => array_intersect_key($before, array_flip($changed))]);
        }

        return back()->with('status', 'Datos actualizados.');
    }

    /** RF-EXP-004: transiciones validadas; ADMIN solo por excepción con justificación obligatoria. */
    public function changeStatus(Request $request, Expediente $expediente)
    {
        Gate::authorize('changeStatus', $expediente);
        $isException = $request->user()->isAdmin();
        $data = $request->validate([
            'status' => ['required', Rule::enum(ExpedienteStatus::class)],
            'reason' => [$isException ? 'required' : 'nullable', 'string', 'max:1000'],
        ], ['reason.required' => 'Como administrador, el cambio de estado es una excepción y requiere justificación.']);
        $from = $expediente->status;
        $to = ExpedienteStatus::from($data['status']);
        if (! $from->canTransitionTo($to)) {
            Audit::record('expediente.status_changed', "Transición rechazada {$from->value} → {$to->value}", $expediente, ['from' => $from->value, 'to' => $to->value], 'denied');
            throw ValidationException::withMessages(['status' => "No se permite pasar de {$from->label()} a {$to->label()}."]);
        }
        $expediente->update(['status' => $to]);
        Audit::record('expediente.status_changed', "Estado: {$from->label()} → {$to->label()}".($isException ? ' (excepción administrativa)' : ''), $expediente, ['from' => $from->value, 'to' => $to->value, 'reason' => $data['reason'] ?? null, 'exception' => $isException]);

        return back()->with('status', "Expediente en estado {$to->label()}.");
    }

    private function data(Request $request): array
    {
        return $request->validate(['name' => 'required|string|max:255', 'description' => 'nullable|string|max:10000', 'type' => 'required|string|max:100']);
    }
}
