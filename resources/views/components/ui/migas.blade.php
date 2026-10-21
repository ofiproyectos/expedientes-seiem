@props(['enlaces' => [], 'actual'])
<nav aria-label="Ruta de navegación">
    <ol class="breadcrumb">
        @foreach($enlaces as $url => $etiqueta)
            <li><a href="{{ $url }}">{{ $etiqueta }}</a></li>
        @endforeach
        <li aria-current="page">{{ $actual }}</li>
    </ol>
</nav>
