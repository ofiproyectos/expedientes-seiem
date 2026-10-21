@props(['total'])
{{-- Pie de tabla sin paginación: mismo formato y posición que pagination/institucional (manual §7.6). --}}
<div class="table-pagination">
    <span {{ $attributes }}>{{ $total ? '1–'.$total.' de '.$total : '0 registros' }}</span>
</div>
