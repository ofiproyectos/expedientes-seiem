<?php

namespace App\Http\Controllers;

use App\Models\AccessGrant;
use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\Expediente;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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
            'responsible' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
        ]);
        $user = $request->user();
        [$column, $direction] = self::SORTS[$request->input('sort', 'recientes')];
        $expedientes = Expediente::visibleTo($user)->with('responsible')->withCount('documents')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('folio', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%')->orWhereHas('responsible', fn ($u) => $u->where('name', 'like', '%'.$request->q.'%'))))
            ->when($request->filled('responsible'), fn ($q) => $q->where('responsible_id', $request->responsible))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderBy($column, $direction)->orderBy('id', $direction)
            ->paginate(10)->appends($request->only('q', 'responsible', 'from', 'to', 'sort'));

        $visible = Expediente::visibleTo($user);
        $responsibles = User::whereIn('id', (clone $visible)->select('responsible_id'))->orderBy('name')->get(['id', 'name']);
        $sharedDocuments = Document::with('latestVersion', 'expediente')
            ->whereIn('id', AccessGrant::active()->where('user_id', $user->id)->whereNotNull('document_id')->select('document_id'))->get();

        return view('expedientes.index', compact('expedientes', 'sharedDocuments', 'responsibles'));
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Expediente::class);

        return view('expedientes.create');
    }

    /** RF-EXP-001: solo LOADER; folio consecutivo EXP-AAAA-NNNNNN; su creador queda como responsable. */
    public function store(Request $request)
    {
        Gate::authorize('create', Expediente::class);
        $data = $this->data($request);
        // El folio se asigna en la misma transacción que el alta: un error no deja huecos en la numeración.
        $expediente = DB::transaction(fn () => Expediente::create([...$data, 'responsible_id' => $request->user()->id]));
        Audit::record('expediente.created', "Creación del expediente {$expediente->folio}", $expediente, ['name' => $expediente->name]);

        return redirect()->route('expedientes.show', $expediente)->with('status', 'Expediente creado.');
    }

    public function show(Request $request, Expediente $expediente)
    {
        Gate::authorize('view', $expediente);
        $user = $request->user();
        $expediente->load(['responsible', 'documents' => fn ($q) => $q->orderBy('name'), 'documents.versions' => fn ($q) => $q->with('uploader')->orderByDesc('version')]);
        $can = [
            'edit' => Gate::allows('update', $expediente),
            'history' => Gate::allows('viewHistory', $expediente),
            'share' => Gate::allows('share', $expediente),
            'audit' => Gate::allows('viewAudit', $expediente),
        ];
        $retired = $can['history'] ? $expediente->documents()->onlyTrashed()->with('retiredBy', 'latestVersion')->latest('deleted_at')->get() : collect();
        $grants = $can['share'] ? $expediente->grants()->with('user', 'document', 'grantor', 'revoker')->latest()->get() : collect();
        $readers = $can['share'] ? User::where('is_active', true)->where('role', 'READER')->orderBy('name')->get() : collect();
        $history = $can['audit'] ? AuditEvent::with('user')->where('expediente_id', $expediente->id)->orderByDesc('id')->limit(200)->get() : collect();

        return view('expedientes.show', compact('expediente', 'can', 'retired', 'grants', 'readers', 'history'));
    }

    /** RF-EXP-002: LOADER responsable y expediente editable; el folio y el identificador interno no cambian. */
    public function update(Request $request, Expediente $expediente)
    {
        Gate::authorize('update', $expediente);
        $before = $expediente->only('name', 'description');
        $expediente->update($this->data($request));
        $changed = array_keys(array_diff_key($expediente->getChanges(), array_flip(['updated_at'])));
        if ($changed) {
            Audit::record('expediente.updated', "Modificación de metadatos del expediente {$expediente->folio}", $expediente, ['fields' => $changed, 'before' => array_intersect_key($before, array_flip($changed))]);
        }

        return back()->with('status', 'Datos actualizados.');
    }

    private function data(Request $request): array
    {
        return $request->validate(['name' => 'required|string|max:255', 'description' => 'nullable|string|max:10000']);
    }
}
