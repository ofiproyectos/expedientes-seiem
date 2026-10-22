@props(['tabla', 'total'])
<nav class="table-pagination" data-pagination-for="{{ $tabla }}" aria-label="Paginación de la tabla">
    <span data-page-range role="status">{{ $total ? '1–'.$total.' de '.$total : '0 registros' }}</span>
    <div data-page-controls hidden>
        <button type="button" class="btn-secondary icon-only" data-page-prev aria-label="Página anterior"><x-ui.icono nombre="flecha" class="icono-sm icono-invertido"/></button>
        <span data-page-label></span>
        <button type="button" class="btn-secondary icon-only" data-page-next aria-label="Página siguiente"><x-ui.icono nombre="flecha" class="icono-sm"/></button>
    </div>
</nav>
