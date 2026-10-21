{{-- Documentos: una fila por documento con su versión vigente (manual §8.3.1). Todas las acciones de la fila están en un solo botón (manual §7.13). --}}
@php
    $tablaId = $tablaId ?? 'tabla-documentos';
    $tipos = $documents->pluck('type')->unique()->sort()->values();
    // El filtro aparece con dos o más documentos; trabaja sobre las filas ya cargadas (manual §8.3.1).
    $conFiltros = $documents->count() > 1;
    $selectable = $selectable ?? false;
@endphp
@if($conFiltros)
    <form class="filter-bar document-filters" role="search" aria-label="Filtrar documentos" data-filtro-documentos="{{ $tablaId }}" hidden>
        <div class="form-field search-field">
            <label for="{{ $tablaId }}-q">Nombre, descripción o archivo</label>
            <div class="input-icon"><x-ui.icono nombre="buscar"/><input class="input-text" id="{{ $tablaId }}-q" name="q" type="search" maxlength="150" placeholder="Buscar documento" autocomplete="off"></div>
        </div>
        @if($tipos->count() > 1)
            <div class="form-field">
                <label for="{{ $tablaId }}-tipo">Tipo</label>
                <select class="input-text" id="{{ $tablaId }}-tipo" name="tipo">
                    <option value="">Todos</option>
                    @foreach($tipos as $tipo)<option>{{ $tipo }}</option>@endforeach
                </select>
            </div>
        @endif
        <div class="form-actions">
            <x-ui.boton variante="secondary" data-limpiar-documentos title="Limpiar filtros" aria-label="Limpiar filtros"><x-ui.icono nombre="actualizar" class="icono-sm"/></x-ui.boton>
        </div>
    </form>
@endif
@if($selectable)
    <form method="GET" action="{{ route('permissions.create', $expediente) }}" data-share-documents>
    <div class="selection-toolbar" data-share-actions hidden><span data-share-count role="status">0 documentos seleccionados</span><button type="submit" class="btn-primary" data-share-selected disabled><x-ui.icono nombre="agregar" class="icono-sm"/>Asignar permisos</button></div>
    @once('permissions-script')<script src="{{ asset('js/permissions.js') }}?v={{ filemtime(public_path('js/permissions.js')) }}" defer></script>@endonce
@endif
<div class="table-scroll" role="region" aria-label="Documentos" tabindex="0">
    <table class="data-table documents-table" id="{{ $tablaId }}">
        <thead>
            <tr>
                @if($selectable)<th scope="col">Seleccionar</th>@endif
                <th scope="col">Documento</th>
                <th scope="col">Tipo</th>
                <th scope="col">Versión</th>
                <th scope="col">Actualizado</th>
                <th scope="col" class="cell-action">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($documents as $document)
                @php
                    $vigente = $document->versions->sortByDesc('version')->first();
                    $puedeDescargar = Gate::allows('download', $document);
                @endphp
                <tr data-documento data-texto="{{ Str::lower(Str::ascii($document->name.' '.$document->description.' '.$vigente->original_name)) }}" data-tipo="{{ $document->type }}">
                    @if($selectable)<td class="selection-cell"><input type="checkbox" name="documents[]" value="{{ $document->id }}" data-share-document aria-label="Seleccionar {{ $document->name }}"></td>@endif
                    <td class="cell-primary">
                        <strong>{{ $document->name }}</strong>
                        @if($document->description)<small class="cell-truncate" title="{{ $document->description }}">{{ $document->description }}</small>@endif
                    </td>
                    <td data-label="Tipo">{{ $document->type }}</td>
                    <td class="cell-nowrap" data-label="Versión">Versión {{ $vigente->version }}<small>{{ $vigente->format() }} · {{ number_format($vigente->size / 1024, 1) }} KB</small></td>
                    <td class="cell-nowrap" data-label="Actualizado">{{ $vigente->created_at->format('d/m/Y H:i') }}<small>{{ $vigente->uploader->name }}</small></td>
                    <td class="cell-action">
                        <button type="button" class="row-menu-button" popovertarget="doc-{{ $document->id }}-menu" aria-haspopup="true" aria-label="Acciones de {{ $document->name }}" title="Acciones"><x-ui.icono nombre="opciones"/></button>
                        <div class="action-menu" id="doc-{{ $document->id }}-menu" popover data-menu-acciones aria-label="Acciones de {{ $document->name }}">
                            <a href="{{ route('documents.viewer', $vigente) }}"><x-ui.icono nombre="buscar"/>Consultar</a>
                            @if($puedeDescargar)
                                <a href="{{ route('documents.file', ['version' => $vigente, 'download' => 1]) }}"><x-ui.icono nombre="descargar"/>Descargar</a>
                            @endif
                            @if($history)
                                <button type="button" data-dialogo-abrir="doc-{{ $document->id }}-historial"><x-ui.icono nombre="historial"/>Historial de versiones</button>
                            @endif
                            @if($editable)
                                <hr>
                                <button type="button" data-dialogo-abrir="doc-{{ $document->id }}-version"><x-ui.icono nombre="subir"/>Cargar nueva versión</button>
                                <button type="button" data-dialogo-abrir="doc-{{ $document->id }}-clasificacion"><x-ui.icono nombre="editar"/>Editar clasificación</button>
                                <hr>
                                <button type="button" class="action-menu-danger" data-dialogo-abrir="doc-{{ $document->id }}-baja"><x-ui.icono nombre="eliminar"/>Dar de baja</button>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
            @if($conFiltros)
                <tr data-sin-coincidencias hidden>
                    <td colspan="{{ $selectable ? 6 : 5 }}" class="empty-state-cell">
                        <div class="empty-state"><x-ui.icono nombre="buscar"/><h3>Sin coincidencias</h3><p>Ningún documento coincide con los filtros. Ajusta la búsqueda o quita algún filtro.</p></div>
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
<x-ui.pie-tabla :total="$documents->count()" data-pie-documentos="{{ $tablaId }}" role="status"/>
@if($selectable)</form>@endif
@if($editable || $history)
    @foreach($documents as $document)
        @include('expedientes.document-dialogs')
    @endforeach
@endif
