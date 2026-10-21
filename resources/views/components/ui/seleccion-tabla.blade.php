@props(['id', 'titulo', 'items', 'selected' => [], 'field' => null, 'columna' => null, 'detalle' => 'Detalle'])
@php($hasLinks = collect($items)->contains(fn ($item) => isset($item['href'])))
<div data-seleccion-tabla id="{{ $id }}">
    <div class="filter-bar">
        <div class="form-field search-field">
            <label for="{{ $id }}-buscar">{{ $titulo }}</label>
            <div class="input-icon"><x-ui.icono nombre="buscar"/><input type="search" class="input-text" id="{{ $id }}-buscar" data-selection-search placeholder="Buscar" autocomplete="off"></div>
        </div>
        <div class="form-actions"><button type="button" class="btn-secondary icon-only" data-selection-clear-filter title="Limpiar filtros" aria-label="Limpiar filtros"><x-ui.icono nombre="actualizar"/></button></div>
    </div>
    <div class="selection-toolbar" data-selection-controls hidden>
        <span class="muted" data-selection-count role="status">0 seleccionados</span>
        <button type="button" class="btn-secondary" popovertarget="{{ $id }}-selection-menu" aria-haspopup="true"><x-ui.icono nombre="check" class="icono-sm"/>Selección</button>
        <div class="action-menu" id="{{ $id }}-selection-menu" popover data-menu-acciones aria-label="Opciones de selección">
            <button type="button" data-selection-all><x-ui.icono nombre="check"/>Seleccionar todos los resultados</button>
            <label class="checkbox-label"><input type="checkbox" data-selection-only>Ver seleccionados</label>
            <button type="button" data-selection-clear><x-ui.icono nombre="cerrar"/>Quitar selección</button>
        </div>
    </div>
    <div class="table-scroll" role="region" aria-label="{{ $titulo }}" tabindex="0">
        <table class="data-table selection-table">
            <thead><tr><th scope="col" class="selection-cell"><input type="checkbox" data-selection-page aria-label="Seleccionar esta página"></th><th scope="col">{{ $columna ?? $titulo }}</th><th scope="col">{{ $detalle }}</th>@if($hasLinks)<th scope="col" class="cell-action">Acción</th>@endif</tr></thead>
            <tbody>
                @foreach($items as $item)
                    <tr data-selection-row data-search="{{ $item['label'].' '.($item['secondary'] ?? '').' '.$item['detail'] }}">
                        <td class="selection-cell"><input type="checkbox" value="{{ $item['id'] }}" data-selection-item @checked(in_array((int) $item['id'], array_map('intval', $selected))) aria-label="Seleccionar {{ $item['label'] }}"></td>
                        <td class="cell-primary"><strong>{{ $item['label'] }}</strong>@if(isset($item['secondary']))<small>{{ $item['secondary'] }}</small>@endif</td><td>{{ $item['detail'] }}</td>
                        @if(isset($item['href']))<td class="cell-action"><a href="{{ $item['href'] }}" class="table-action"><x-ui.icono nombre="editar" class="icono-sm"/>Administrar<span class="sr-only"> acceso de {{ $item['label'] }}</span></a></td>@endif
                    </tr>
                @endforeach
                <tr data-selection-empty @if(count($items)) hidden @endif><td colspan="{{ $hasLinks ? 4 : 3 }}" class="empty-state-cell"><div class="empty-state"><x-ui.icono nombre="buscar"/><h3>Sin coincidencias</h3><p>Ajusta la búsqueda o limpia los filtros.</p></div></td></tr>
            </tbody>
        </table>
    </div>
    <nav class="table-pagination" aria-label="Páginas de {{ $titulo }}">
        <span data-selection-range>{{ count($items) ? '1–'.count($items).' de '.count($items) : '0 registros' }}</span>
        <div data-selection-pagination hidden>
            <button type="button" class="btn-secondary icon-only" data-selection-prev aria-label="Página anterior"><x-ui.icono nombre="flecha" class="icono-invertido"/></button>
            <span data-selection-page-label></span>
            <button type="button" class="btn-secondary icon-only" data-selection-next aria-label="Página siguiente"><x-ui.icono nombre="flecha"/></button>
        </div>
    </nav>
    @if($field)<input type="hidden" name="{{ $field }}" data-selection-output value="[]">@endif
</div>
@once('permissions-script')
    <script src="{{ asset('js/permissions.js') }}?v={{ filemtime(public_path('js/permissions.js')) }}" defer></script>
@endonce
