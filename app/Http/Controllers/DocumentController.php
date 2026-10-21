<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Expediente;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    private const ALLOWED = ['pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'docx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'], 'xlsx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']];

    /** RF-DOC-001/002: uno o varios archivos con tipo de catálogo; se acepta todo el lote o nada. */
    public function store(Request $request, Expediente $expediente)
    {
        Gate::authorize('update', $expediente);
        $data = $request->validate([
            'files' => 'required|array|min:1|max:20',
            'files.*' => ['required', 'file', 'max:'.config('expedientes.max_file_kb')],
            'type' => ['required', Rule::in(config('expedientes.document_types'))],
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
        ], ['files.required' => 'Selecciona al menos un archivo.', 'files.*.max' => 'Cada archivo debe pesar como máximo 10 MB.']);
        $files = $data['files'];
        $inspected = array_map(fn (UploadedFile $file) => $this->inspect($file), $files);
        $hashes = array_column($inspected, 'hash');
        if (count($hashes) !== count(array_unique($hashes))) {
            throw ValidationException::withMessages(['files' => 'Seleccionaste el mismo archivo más de una vez.']);
        }
        $title = count($files) === 1 && filled($data['title'] ?? null) ? $data['title'] : null;

        $created = $this->persist($expediente, function (array &$paths) use ($request, $expediente, $files, $inspected, $data, $title) {
            $documents = [];
            foreach ($files as $i => $file) {
                $this->ensureNotDuplicate($expediente, $inspected[$i]['hash'], $file);
                $document = $expediente->documents()->create(['name' => $title ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 'type' => $data['type'], 'description' => $data['description'] ?? null]);
                $this->storeVersion($request, $document, $file, $inspected[$i], $paths, 'Carga inicial');
                $documents[] = $document;
            }

            return $documents;
        });
        foreach ($created as $document) {
            Audit::record('document.uploaded', "Carga de «{$document->name}» ({$document->type})", $document, ['type' => $document->type]);
        }

        return back()->with('status', count($created) === 1 ? 'Documento guardado.' : count($created).' documentos guardados.');
    }

    /** RF-VER-001: nueva versión consecutiva; la anterior se conserva. */
    public function replace(Request $request, Document $document)
    {
        Gate::authorize('update', $document);
        $data = $request->validate(['file' => ['required', 'file', 'max:'.config('expedientes.max_file_kb')], 'change_description' => 'nullable|string|max:1000']);
        $inspected = $this->inspect($data['file']);
        $version = $this->persist($document->expediente, function (array &$paths) use ($request, $document, $data, $inspected) {
            $this->ensureNotDuplicate($document->expediente, $inspected['hash'], $data['file']);

            return $this->storeVersion($request, $document, $data['file'], $inspected, $paths, $data['change_description'] ?? null);
        });
        Audit::record('version.created', "Versión {$version->version} de «{$document->name}»", $version, ['document_id' => $document->id, 'version' => $version->version]);

        return back()->with('status', "Versión {$version->version} guardada como vigente.");
    }

    /** RF-VER-003: copia una versión histórica como nueva versión vigente, sin tocar las existentes. */
    public function restore(Request $request, DocumentVersion $version)
    {
        $document = $version->document;
        Gate::authorize('update', $document);
        if ($version->isCurrent()) {
            throw ValidationException::withMessages(['version' => 'La versión seleccionada ya es la vigente.']);
        }
        $disk = Storage::disk('local');
        abort_unless($disk->exists($version->path), 404);
        $restored = $this->persist($document->expediente, function (array &$paths) use ($request, $document, $version, $disk) {
            $path = 'documents/'.$document->expediente_id.'/'.bin2hex(random_bytes(20)).'.'.pathinfo($version->path, PATHINFO_EXTENSION);
            if (! $disk->copy($version->path, $path)) {
                throw new \RuntimeException('No fue posible copiar el archivo de la versión.');
            }
            $paths[] = $path;

            return $document->versions()->create([
                'version' => $document->versions()->lockForUpdate()->max('version') + 1,
                'path' => $path, 'original_name' => $version->original_name, 'mime' => $version->mime, 'size' => $version->size, 'hash' => $version->hash,
                'uploaded_by' => $request->user()->id, 'restored_from_version' => $version->version,
                'change_description' => "Restaurado desde la versión {$version->version}",
            ]);
        });
        Audit::record('version.restored', "Versión {$restored->version} de «{$document->name}» restaurada desde la versión {$version->version}", $restored, ['document_id' => $document->id, 'from_version' => $version->version, 'version' => $restored->version]);

        return back()->with('status', "Se creó la versión {$restored->version} (restaurado desde la versión {$version->version}).");
    }

    /** RF-DOC-002: título, tipo de catálogo y descripción opcional. */
    public function update(Request $request, Document $document)
    {
        Gate::authorize('update', $document);
        $data = $request->validate(['name' => 'required|string|max:255', 'type' => ['required', Rule::in(config('expedientes.document_types'))], 'description' => 'nullable|string|max:2000']);
        $before = $document->only('name', 'type', 'description');
        $document->update($data);
        $changed = array_keys(array_diff_key($document->getChanges(), array_flip(['updated_at'])));
        if ($changed) {
            Audit::record('document.updated', "Clasificación de «{$document->name}» modificada", $document, ['fields' => $changed, 'before' => array_intersect_key($before, array_flip($changed))]);
        }

        return back()->with('status', 'Clasificación actualizada.');
    }

    /** RF-DOC-004: baja lógica con motivo; se conservan el registro y sus versiones. */
    public function retire(Request $request, Document $document)
    {
        Gate::authorize('update', $document);
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000'], ['reason.required' => 'Indica el motivo de la baja.']);
        $document->forceFill(['deleted_by' => $request->user()->id, 'deletion_reason' => $data['reason']])->save();
        $document->delete();
        Audit::record('document.retired', "Baja de «{$document->name}»", $document, ['reason' => $data['reason']]);

        return back()->with('status', 'Documento dado de baja. Su historial se conserva.');
    }

    /** RF-VIS-001: visor integrado; los formatos sin visor muestran un mensaje informativo. */
    public function viewer(Request $request, DocumentVersion $version)
    {
        $document = $version->document;
        $this->authorizeVersion($request, $version, 'view');

        return view('expedientes.viewer', ['version' => $version, 'document' => $document, 'canDownload' => Gate::allows('download', $document)]);
    }

    /** RF-VIS-001/002, RF-PRM-004: autorización en cada petición de archivo; visualización y descarga se auditan. */
    public function file(Request $request, DocumentVersion $version)
    {
        $download = $request->boolean('download');
        $this->authorizeVersion($request, $version, $download ? 'download' : 'view');
        $disk = Storage::disk('local');
        $document = $version->document;
        $label = "«{$document->name}» versión {$version->version}";
        if (! $disk->exists($version->path)) {
            Audit::record($download ? 'document.downloaded' : 'document.viewed', "Archivo no disponible: {$label}", $version, [], 'failure');
            abort(404);
        }
        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'self'".($version->mime === 'application/pdf' ? '' : '; sandbox')];
        if ($download) {
            Audit::record('document.downloaded', "Descarga de {$label}", $version);

            return $disk->download($version->path, $version->original_name, $headers);
        }
        abort_unless($version->isViewable(), 415, 'Este formato no tiene visor integrado; requiere descarga autorizada.');
        Audit::record('document.viewed', "Visualización de {$label}", $version);

        return response()->file($disk->path($version->path), [...$headers, 'Content-Type' => $version->mime, 'Content-Disposition' => 'inline']);
    }

    /** Las versiones anteriores solo las consultan ADMIN y LOADER responsable (RF-VER-002). */
    private function authorizeVersion(Request $request, DocumentVersion $version, string $ability): void
    {
        $document = $version->document;
        $allowed = Gate::allows($ability, $document) && ($version->isCurrent() || Gate::allows('viewHistory', $document));
        if (! $allowed) {
            Audit::denied(($ability === 'download' ? 'Descarga' : 'Visualización')." no autorizada de «{$document->name}» versión {$version->version}", $version);
            abort(403);
        }
    }

    /** RF-DOC-001 CA-03: extensión y contenido real deben coincidir; los Office se validan como paquete. */
    private function inspect(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        $name = $file->getClientOriginalName();
        if (! in_array($mime, self::ALLOWED[$ext] ?? [], true)) {
            throw ValidationException::withMessages(['files' => "«{$name}»: la extensión o el contenido real no está permitido."]);
        }
        if (in_array($ext, ['docx', 'xlsx'])) {
            $zip = new \ZipArchive;
            $opened = $zip->open($file->getRealPath()) === true;
            $valid = $opened && $zip->locateName('[Content_Types].xml') !== false && $zip->locateName($ext === 'docx' ? 'word/document.xml' : 'xl/workbook.xml') !== false;
            if ($opened) {
                $zip->close();
            }
            if (! $valid) {
                throw ValidationException::withMessages(['files' => "«{$name}»: el archivo Office no es válido."]);
            }
        }

        return ['mime' => $mime, 'hash' => hash_file('sha256', $file->getRealPath())];
    }

    private function ensureNotDuplicate(Expediente $expediente, string $hash, UploadedFile $file): void
    {
        $exists = DocumentVersion::whereHas('document', fn ($q) => $q->withTrashed()->where('expediente_id', $expediente->id))->where('hash', $hash)->exists();
        if ($exists) {
            throw ValidationException::withMessages(['files' => "«{$file->getClientOriginalName()}» ya existe en el historial del expediente."]);
        }
    }

    private function storeVersion(Request $request, Document $document, UploadedFile $file, array $inspected, array &$paths, ?string $description): DocumentVersion
    {
        $path = $file->store('documents/'.$document->expediente_id, 'local');
        if (! $path) {
            throw new \RuntimeException('No fue posible almacenar el archivo.');
        }
        $paths[] = $path;

        return $document->versions()->create([
            'version' => ($document->versions()->max('version') ?? 0) + 1, 'path' => $path, 'original_name' => $file->getClientOriginalName(),
            'mime' => $inspected['mime'], 'size' => $file->getSize(), 'hash' => $inspected['hash'], 'uploaded_by' => $request->user()->id,
            'change_description' => $description,
        ]);
    }

    /** RF-DOC-001 CA-05: si algo falla, se revierte la base y se eliminan los archivos ya escritos. */
    private function persist(Expediente $expediente, callable $work): mixed
    {
        $paths = [];
        try {
            return DB::transaction(function () use ($expediente, $work, &$paths) {
                Expediente::whereKey($expediente->id)->lockForUpdate()->firstOrFail();

                return $work($paths);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($paths);
            throw $e;
        }
    }
}
