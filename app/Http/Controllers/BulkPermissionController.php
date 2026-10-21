<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use App\Models\User;
use App\Support\Audit;
use App\Support\PermissionBatch;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BulkPermissionController extends Controller
{
    public function create(Request $request, Expediente $expediente)
    {
        Gate::authorize('share', $expediente);
        $readers = User::where('role', 'READER')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'username']);
        $documents = $expediente->documents()->orderBy('name')->get(['id', 'name', 'type']);
        $initial = json_decode($request->old('selection', '{}'), true) ?: [];
        if (! $request->old('selection')) {
            $initial = ['scope' => 'documents', 'document_ids' => array_map('intval', (array) $request->input('documents', [])),
                'user_ids' => array_map('intval', (array) $request->input('readers', []))];
        }

        return view('permissions.create', compact('expediente', 'readers', 'documents', 'initial'));
    }

    public function preview(Request $request, Expediente $expediente, PermissionBatch $batches)
    {
        Gate::authorize('share', $expediente);
        $data = $batches->validate($request->all(), $expediente);
        $plan = $batches->plan($expediente, $data);
        $review = ['id' => (string) Str::uuid(), 'actor' => $request->user()->id, 'expediente' => $expediente->id,
            'data' => $data, 'fingerprint' => $plan['fingerprint'], 'expires' => now()->addMinutes(30)->timestamp];

        return response()->json([
            'token' => Crypt::encryptString(json_encode($review)), 'created' => count($plan['create']),
            'skipped' => $plan['skipped'], 'consolidated' => count($plan['consolidate']), 'data' => $data,
            'reader_count' => count($data['user_ids']), 'document_count' => count($data['document_ids']),
        ]);
    }

    public function store(Request $request, Expediente $expediente, PermissionBatch $batches)
    {
        Gate::authorize('share', $expediente);
        $request->validate(['review_token' => 'required|string', 'selection' => 'nullable|string']);
        try {
            $review = json_decode(Crypt::decryptString($request->review_token), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException $exception) {
            throw ValidationException::withMessages(['review_token' => 'La revisión no es válida. Revisa el lote nuevamente.']);
        }
        abort_unless($review['actor'] === $request->user()->id && $review['expediente'] === $expediente->id, 403);
        if ($review['expires'] < now()->timestamp) {
            throw ValidationException::withMessages(['review_token' => 'La revisión expiró. Revisa nuevamente antes de asignar.']);
        }
        $batch = $batches->commit($expediente, $request->user(), $review);

        return redirect()->to(route('expedientes.show', $expediente).'#seccion-accesos')
            ->with('status', "Lote completado: {$batch->created_count} permisos asignados, {$batch->skipped_count} ya cubiertos y {$batch->consolidated_count} consolidados.");
    }

    public function reader(Request $request, Expediente $expediente, User $reader)
    {
        Gate::authorize('share', $expediente);
        abort_unless($expediente->grants()->where('user_id', $reader->id)->exists(), 404);
        $grants = $expediente->grants()->where('user_id', $reader->id)->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->with('document')->orderByDesc('id')->get();
        $documents = $expediente->documents()->orderBy('name')->get();
        $active = $reader->is_active && $reader->role === 'READER' ? $grants->filter(fn ($g) => $g->status()[0] === 'Activo') : collect();
        $effective = $documents->map(function ($document) use ($active) {
            $sources = $active->filter(fn ($g) => $g->document_id === null || $g->document_id === $document->id);

            return ['document' => $document, 'sources' => $sources];
        })->filter(fn ($row) => $row['sources']->isNotEmpty());

        $page = max(1, (int) $request->input('effective_page', 1));
        $effective = new LengthAwarePaginator($effective->values()->forPage($page, 10), $effective->count(), 10, $page, [
            'path' => $request->url(), 'pageName' => 'effective_page', 'fragment' => 'acceso-efectivo',
        ]);
        $effective->appends($request->except('effective_page'));

        return view('permissions.reader', compact('expediente', 'reader', 'grants', 'effective'));
    }

    public function revoke(Request $request, Expediente $expediente)
    {
        Gate::authorize('share', $expediente);
        $request->validate(['selection' => 'required|json', 'mode' => 'required|in:readers,grants']);
        $ids = json_decode($request->selection, true);
        validator(['ids' => $ids], ['ids' => 'required|array|min:1|max:20000', 'ids.*' => 'required|integer|distinct'])->validate();
        $count = DB::transaction(function () use ($expediente, $ids, $request) {
            Expediente::whereKey($expediente->id)->lockForUpdate()->firstOrFail();
            $query = $expediente->grants()->whereNull('revoked_at');
            if ($request->mode === 'readers') {
                $query->whereIn('user_id', $ids);
            } else {
                if ($expediente->grants()->whereIn('id', $ids)->count() !== count($ids)) {
                    throw ValidationException::withMessages(['selection' => 'La selección incluye permisos ajenos a este expediente.']);
                }
                $query->whereIn('id', $ids);
            }
            $grants = $query->orderBy('id')->lockForUpdate()->get();
            $batchId = (string) Str::uuid();
            foreach ($grants as $grant) {
                $grant->update(['revoked_at' => now(), 'revoked_by' => $request->user()->id]);
                Audit::record('grant.revoked', 'Revocación de permiso por lote', $grant, [
                    'batch_id' => $batchId, 'reader' => $grant->user_id, 'document_id' => $grant->document_id,
                ]);
            }
            Audit::record('grant.batch_revoked', 'Revocación de permisos por lote', $expediente, ['batch_id' => $batchId, 'count' => $grants->count(), 'mode' => $request->mode]);

            return $grants->count();
        });

        return back()->with('status', "Se revocaron {$count} permisos. El acceso se calcula con los demás permisos vigentes.");
    }
}
