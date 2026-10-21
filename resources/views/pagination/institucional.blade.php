<nav class="table-pagination" aria-label="Páginas de resultados">
    <span>{{ $paginator->total() ? $paginator->firstItem().'–'.$paginator->lastItem().' de '.$paginator->total() : '0 registros' }}</span>
    @if($paginator->hasPages())
        <div>
            @if($paginator->onFirstPage())
                <button type="button" class="btn-secondary icon-only" disabled aria-label="Página anterior" title="Página anterior"><x-ui.icono nombre="flecha" class="icono-sm icono-invertido"/></button>
            @else
                <a class="btn-secondary icon-only" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Página anterior" title="Página anterior"><x-ui.icono nombre="flecha" class="icono-sm icono-invertido"/></a>
            @endif
            <span>Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>
            @if($paginator->hasMorePages())
                <a class="btn-secondary icon-only" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Página siguiente" title="Página siguiente"><x-ui.icono nombre="flecha" class="icono-sm"/></a>
            @else
                <button type="button" class="btn-secondary icon-only" disabled aria-label="Página siguiente" title="Página siguiente"><x-ui.icono nombre="flecha" class="icono-sm"/></button>
            @endif
        </div>
    @endif
</nav>
