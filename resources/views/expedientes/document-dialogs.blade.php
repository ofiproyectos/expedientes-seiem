@php
    $versiones = $document->versions->sortByDesc('version')->values();
    $vigente = $versiones->first();
    $puedeDescargar = Gate::allows('download', $document);
    $prefijo = 'doc-'.$document->id;
    $conError = fn (string $dialogo, string $campo) => old('_dialogo') === $dialogo && $errors->has($campo);
@endphp
@if($history)
    <x-ui.dialogo :id="$prefijo.'-historial'" titulo="Historial de versiones" :descripcion="$document->name.' · cada versión conserva su autor, fecha y hora. Restaurar crea una versión nueva; las existentes no se modifican.'" amplio>
        <div class="table-scroll" role="region" aria-label="Versiones de {{ $document->name }}" tabindex="0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Versión</th>
                        <th scope="col">Archivo</th>
                        <th scope="col">Cargado por</th>
                        <th scope="col">Cambio</th>
                        <th scope="col" class="cell-action">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($versiones as $version)
                        @php($esVigente = $version->is($vigente))
                        <tr>
                            <td class="cell-nowrap"><strong>Versión {{ $version->version }}</strong>@if($esVigente)<span class="status-badge status-activo">Vigente</span>@endif</td>
                            <td class="cell-file"><strong>{{ $version->original_name }}</strong><small>{{ $version->format() }} · {{ number_format($version->size / 1024, 1) }} KB</small></td>
                            <td><strong>{{ $version->uploader->name }}</strong><small>{{ $version->created_at->format('d/m/Y H:i') }}</small></td>
                            <td>@if($version->restored_from_version)<span class="status-badge status-en_revision">Restaurado</span>@endif{{ $version->change_description ?? '—' }}</td>
                            <td class="cell-action">
                                <span class="row-actions">
                                    <a class="table-action" href="{{ route('documents.viewer', $version) }}"><x-ui.icono nombre="buscar"/>Consultar<span class="sr-only"> versión {{ $version->version }}</span></a>
                                    @if($puedeDescargar)
                                        <a class="table-action" href="{{ route('documents.file', ['version' => $version, 'download' => 1]) }}"><x-ui.icono nombre="descargar"/>Descargar<span class="sr-only"> versión {{ $version->version }}</span></a>
                                    @endif
                                    @if($editable && ! $esVigente)
                                        <form method="POST" action="{{ route('versions.restore', $version) }}" data-cargando="Restaurando versión…">
                                            @csrf
                                            <button type="submit" class="table-action text-button" data-confirm="Se creará la versión {{ $vigente->version + 1 }} con el contenido de la versión {{ $version->version }}, marcada como «Restaurado». Las versiones existentes no se modifican." data-confirm-title="Restaurar versión {{ $version->version }}" data-confirm-accept="Sí, restaurar"><x-ui.icono nombre="devolver"/>Restaurar<span class="sr-only"> versión {{ $version->version }}</span></button>
                                        </form>
                                    @endif
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-ui.pie-tabla :total="$versiones->count()"/>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" data-dialogo-cerrar>Cerrar</button>
        </div>
    </x-ui.dialogo>
@endif

@if($editable)
    <x-ui.dialogo :id="$prefijo.'-version'" titulo="Cargar nueva versión" :descripcion="$document->name.' · la versión cargada pasa a ser la vigente; las anteriores se conservan.'">
        <form method="POST" enctype="multipart/form-data" action="{{ route('documents.replace', $document) }}" data-cargando="Cargando versión…">
            @csrf
            <input type="hidden" name="_dialogo" value="{{ $prefijo }}-version">
            <div class="form-field">
                <label for="{{ $prefijo }}-version-file">Archivo<span class="required" aria-hidden="true">*</span></label>
                <input class="input-text" id="{{ $prefijo }}-version-file" type="file" name="file" accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png" required aria-required="true" aria-describedby="{{ $prefijo }}-version-file-hint @if($conError($prefijo.'-version', 'file')) {{ $prefijo }}-version-file-error @endif" @if($conError($prefijo.'-version', 'file')) aria-invalid="true" @endif>
                <small id="{{ $prefijo }}-version-file-hint" class="form-hint">PDF, DOCX, XLSX, JPG o PNG; máximo 10 MB.</small>
                <x-ui.error-campo campo="file" :id="$prefijo.'-version-file-error'" :dialogo="$prefijo.'-version'"/>
            </div>
            <div class="form-field">
                <label for="{{ $prefijo }}-version-change">Descripción del cambio</label>
                <input class="input-text" id="{{ $prefijo }}-version-change" name="change_description" maxlength="1000" value="{{ old('_dialogo') === $prefijo.'-version' ? old('change_description') : '' }}">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" data-dialogo-cerrar>Cancelar</button>
                <x-ui.boton type="submit"><x-ui.icono nombre="subir" class="icono-sm"/>Cargar versión</x-ui.boton>
            </div>
        </form>
    </x-ui.dialogo>

    <x-ui.dialogo :id="$prefijo.'-clasificacion'" titulo="Editar clasificación" :descripcion="$document->name">
        @php($mio = old('_dialogo') === $prefijo.'-clasificacion')
        <form method="POST" action="{{ route('documents.update', $document) }}" data-cargando="Guardando cambios…">
            @csrf @method('PUT')
            <input type="hidden" name="_dialogo" value="{{ $prefijo }}-clasificacion">
            <div class="form-grid two">
                <div class="form-field">
                    <label for="{{ $prefijo }}-name">Título<span class="required" aria-hidden="true">*</span></label>
                    <input class="input-text" id="{{ $prefijo }}-name" name="name" value="{{ $mio ? old('name') : $document->name }}" maxlength="255" required aria-required="true" @if($conError($prefijo.'-clasificacion', 'name')) aria-invalid="true" aria-describedby="{{ $prefijo }}-name-error" @endif>
                    <x-ui.error-campo campo="name" :id="$prefijo.'-name-error'" :dialogo="$prefijo.'-clasificacion'"/>
                </div>
                <div class="form-field">
                    <label for="{{ $prefijo }}-type">Tipo de documento<span class="required" aria-hidden="true">*</span></label>
                    <select class="input-text" id="{{ $prefijo }}-type" name="type" required aria-required="true">
                        @foreach(config('expedientes.document_types') as $type)<option @selected(($mio ? old('type') : $document->type) === $type)>{{ $type }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="form-field">
                <label for="{{ $prefijo }}-description">Descripción</label>
                <textarea class="input-text" id="{{ $prefijo }}-description" name="description" maxlength="2000">{{ $mio ? old('description') : $document->description }}</textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" data-dialogo-cerrar>Cancelar</button>
                <x-ui.boton type="submit"><x-ui.icono nombre="check" class="icono-sm"/>Guardar cambios</x-ui.boton>
            </div>
        </form>
    </x-ui.dialogo>

    <x-ui.dialogo :id="$prefijo.'-baja'" titulo="Dar de baja el documento" :descripcion="'«'.$document->name.'» dejará de aparecer en las consultas ordinarias y los lectores perderán acceso. Sus versiones, el responsable, la fecha y el motivo se conservan.'" peligro>
        <form method="POST" action="{{ route('documents.retire', $document) }}" data-cargando="Dando de baja el documento…">
            @csrf
            <input type="hidden" name="_dialogo" value="{{ $prefijo }}-baja">
            <div class="form-field">
                <label for="{{ $prefijo }}-reason">Motivo de la baja<span class="required" aria-hidden="true">*</span></label>
                <textarea class="input-text" id="{{ $prefijo }}-reason" name="reason" minlength="5" maxlength="1000" required aria-required="true" @if($conError($prefijo.'-baja', 'reason')) aria-invalid="true" aria-describedby="{{ $prefijo }}-reason-error" @endif>{{ old('_dialogo') === $prefijo.'-baja' ? old('reason') : '' }}</textarea>
                <x-ui.error-campo campo="reason" :id="$prefijo.'-reason-error'" :dialogo="$prefijo.'-baja'"/>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" data-dialogo-cerrar>Cancelar</button>
                <x-ui.boton variante="danger" type="submit"><x-ui.icono nombre="eliminar" class="icono-sm"/>Dar de baja</x-ui.boton>
            </div>
        </form>
    </x-ui.dialogo>
@endif
