@php
    $versiones = $document->versions->sortByDesc('version')->values();
    $vigente = $versiones->first();
    $mostradas = $history ? $versiones : $versiones->take(1);
    $puedeDescargar = Gate::allows('download', $document);
@endphp
<article class="document-item" aria-labelledby="documento-{{ $document->id }}">
    <div class="document-item-header">
        <h3 id="documento-{{ $document->id }}"><x-ui.icono nombre="documento" class="icono-sm"/>{{ $document->name }}</h3>
        <span class="badge">{{ $document->type }}</span>
    </div>
    @if($document->description)<p class="document-description">{{ $document->description }}</p>@endif
    <div class="table-scroll" role="region" aria-label="{{ $history ? 'Historial de versiones' : 'Versión vigente' }} de {{ $document->name }}" tabindex="0">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Versión</th>
                    <th scope="col">Archivo</th>
                    <th scope="col">Formato</th>
                    <th scope="col">Tamaño</th>
                    <th scope="col">Cargado por</th>
                    <th scope="col">Fecha</th>
                    @if($history)<th scope="col">Descripción del cambio</th>@endif
                    <th scope="col" class="cell-action">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($mostradas as $version)
                    @php($esVigente = $version->is($vigente))
                    <tr>
                        <td class="cell-nowrap"><strong>Versión {{ $version->version }}</strong>@if($esVigente)<span class="status-badge status-activo">Vigente</span>@endif</td>
                        <td>{{ $version->original_name }}</td>
                        <td>{{ $version->format() }}</td>
                        <td class="cell-nowrap">{{ number_format($version->size / 1024, 1) }} KB</td>
                        <td>{{ $version->uploader->name }}</td>
                        <td class="cell-nowrap">{{ $version->created_at->format('d/m/Y') }}<small>{{ $version->created_at->format('H:i') }}</small></td>
                        @if($history)<td>@if($version->restored_from_version)<span class="status-badge status-en_revision">Restaurado</span>@endif{{ $version->change_description ?? '—' }}</td>@endif
                        <td class="cell-action">
                            <span class="row-actions">
                                <a class="table-action" href="{{ route('documents.viewer', $version) }}"><x-ui.icono nombre="buscar"/>Consultar<span class="sr-only"> versión {{ $version->version }} de {{ $document->name }}</span></a>
                                @if($puedeDescargar)
                                    <a class="table-action" href="{{ route('documents.file', ['version' => $version, 'download' => 1]) }}"><x-ui.icono nombre="descargar"/>Descargar<span class="sr-only"> versión {{ $version->version }} de {{ $document->name }}</span></a>
                                @endif
                                @if($editable && ! $esVigente)
                                    <form method="POST" action="{{ route('versions.restore', $version) }}" data-cargando="Restaurando versión…">
                                        @csrf
                                        <button type="submit" class="table-action text-button" data-confirm="Se creará la versión {{ $vigente->version + 1 }} con el contenido de la versión {{ $version->version }}, marcada como «Restaurado». Las versiones existentes no se modifican." data-confirm-title="Restaurar versión {{ $version->version }}" data-confirm-accept="Sí, restaurar"><x-ui.icono nombre="devolver"/>Restaurar<span class="sr-only"> versión {{ $version->version }} de {{ $document->name }}</span></button>
                                    </form>
                                @endif
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($editable)
        <div class="document-tools">
            <details class="disclosure disclosure-inset">
                <summary><x-ui.icono nombre="subir" class="icono-sm"/>Cargar nueva versión</summary>
                <form method="POST" enctype="multipart/form-data" action="{{ route('documents.replace', $document) }}" data-cargando="Cargando versión…">
                    @csrf
                    <div class="form-grid two">
                        <div class="form-field">
                            <label for="version-file-{{ $document->id }}">Archivo<span class="required" aria-hidden="true">*</span></label>
                            <input class="input-text" id="version-file-{{ $document->id }}" type="file" name="file" accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png" required aria-required="true" aria-describedby="version-file-{{ $document->id }}-hint">
                            <small id="version-file-{{ $document->id }}-hint" class="form-hint">PDF, DOCX, XLSX, JPG o PNG; máximo 10 MB. Las versiones anteriores se conservan.</small>
                        </div>
                        <div class="form-field">
                            <label for="version-change-{{ $document->id }}">Descripción del cambio</label>
                            <input class="input-text" id="version-change-{{ $document->id }}" name="change_description" maxlength="1000">
                        </div>
                    </div>
                    <div class="form-actions"><x-ui.boton type="submit"><x-ui.icono nombre="subir" class="icono-sm"/>Cargar versión</x-ui.boton></div>
                </form>
            </details>
            <details class="disclosure disclosure-inset">
                <summary><x-ui.icono nombre="editar" class="icono-sm"/>Editar clasificación</summary>
                <form method="POST" action="{{ route('documents.update', $document) }}" data-cargando="Guardando cambios…">
                    @csrf @method('PUT')
                    <div class="form-grid two">
                        <div class="form-field">
                            <label for="doc-name-{{ $document->id }}">Título<span class="required" aria-hidden="true">*</span></label>
                            <input class="input-text" id="doc-name-{{ $document->id }}" name="name" value="{{ $document->name }}" maxlength="255" required aria-required="true">
                        </div>
                        <div class="form-field">
                            <label for="doc-type-{{ $document->id }}">Tipo de documento<span class="required" aria-hidden="true">*</span></label>
                            <select class="input-text" id="doc-type-{{ $document->id }}" name="type" required aria-required="true">
                                @foreach(config('expedientes.document_types') as $type)<option @selected($document->type === $type)>{{ $type }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="doc-description-{{ $document->id }}">Descripción</label>
                        <textarea class="input-text" id="doc-description-{{ $document->id }}" name="description" maxlength="2000">{{ $document->description }}</textarea>
                    </div>
                    <div class="form-actions"><x-ui.boton type="submit"><x-ui.icono nombre="check" class="icono-sm"/>Guardar cambios</x-ui.boton></div>
                </form>
            </details>
            <details class="disclosure disclosure-inset">
                <summary><x-ui.icono nombre="eliminar" class="icono-sm"/>Dar de baja</summary>
                <form method="POST" action="{{ route('documents.retire', $document) }}" data-cargando="Dando de baja el documento…">
                    @csrf
                    <div class="form-field">
                        <label for="doc-reason-{{ $document->id }}">Motivo de la baja<span class="required" aria-hidden="true">*</span></label>
                        <textarea class="input-text" id="doc-reason-{{ $document->id }}" name="reason" minlength="5" maxlength="1000" required aria-required="true" aria-describedby="doc-reason-{{ $document->id }}-hint"></textarea>
                        <small id="doc-reason-{{ $document->id }}-hint" class="form-hint">El documento deja de aparecer en las consultas ordinarias; se conservan sus versiones, el responsable, la fecha y el motivo.</small>
                    </div>
                    <div class="form-actions">
                        <x-ui.boton variante="secondary" type="submit" data-confirm="«{{ $document->name }}» dejará de aparecer en las consultas ordinarias y los lectores perderán acceso. Su historial se conserva." data-confirm-title="Dar de baja el documento" data-confirm-accept="Sí, dar de baja" data-confirm-variant="danger"><x-ui.icono nombre="eliminar" class="icono-sm"/>Dar de baja</x-ui.boton>
                    </div>
                </form>
            </details>
        </div>
    @endif
</article>
