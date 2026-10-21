@props(['grant'])
@php([$etiqueta, $clase] = $grant->status())
<span class="status-badge {{ $clase }}">{{ $etiqueta }}</span>
