@php($expediente = $document->expediente)
<x-layouts.institucional :titulo="$document->name">
    @can('view', $expediente)
        <x-ui.migas :enlaces="[route('expedientes.index') => 'Expedientes', route('expedientes.show', $expediente) => $expediente->name]" :actual="$document->name"/>
    @else
        <x-ui.migas :enlaces="[route('expedientes.index') => 'Expedientes']" :actual="$document->name"/>
    @endcan
    <div class="page-title">
        <div>
            <h1>{{ $document->name }}</h1>
            <p class="muted">Versión {{ $version->version }} · {{ $version->format() }} · {{ number_format($version->size / 1024, 1) }} KB · cargada por {{ $version->uploader->name }} el {{ $version->created_at->format('d/m/Y H:i') }}</p>
        </div>
        <div class="form-actions">
            <x-ui.boton variante="secondary" :href="route('documents.file', ['version' => $version, 'download' => 1])"><x-ui.icono nombre="descargar" class="icono-sm"/>Descargar documento</x-ui.boton>
            <x-ui.boton variante="secondary" :href="Gate::allows('view', $expediente) ? route('expedientes.show', $expediente) : route('expedientes.index')"><x-ui.icono nombre="flecha" class="icono-sm icono-invertido"/>{{ Gate::allows('view', $expediente) ? 'Volver al expediente' : 'Volver a expedientes' }}</x-ui.boton>
        </div>
    </div>

    @if($version->isViewable())
        <section class="card viewer-card" aria-label="Visor del documento">
            @if($version->mime === 'application/pdf')
                <iframe class="document-viewer" src="{{ route('documents.file', $version) }}" title="{{ $document->name }}, versión {{ $version->version }}"></iframe>
            @else
                <img class="document-viewer-image" src="{{ route('documents.file', $version) }}" alt="{{ $document->name }}, versión {{ $version->version }}">
            @endif
        </section>
    @else
        <div class="notice" role="status">
            <x-ui.icono nombre="info"/>
            <p>El formato {{ $version->format() }} no tiene visor integrado. Descarga el archivo para abrirlo con una aplicación compatible.</p>
        </div>
    @endif
</x-layouts.institucional>
