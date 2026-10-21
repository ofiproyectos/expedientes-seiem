@props(['titulo', 'tituloTarjeta', 'intro' => null])
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }} | {{ config('expedientes.platform_name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/institucional.css') }}?v={{ filemtime(public_path('css/institucional.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/acceso.css') }}?v={{ filemtime(public_path('css/acceso.css')) }}">
    <script src="{{ asset('js/institucional.js') }}?v={{ filemtime(public_path('js/institucional.js')) }}" defer></script>
</head>
<body class="access-body">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <main class="access-layout">
        <section class="access-brand" aria-labelledby="titulo-sistema">
            <div class="access-brand-header">
                <img class="access-brand-logos" src="{{ asset('images/logos/LOGOS.png') }}" alt="Gobierno del Estado de México">
                <div class="access-brand-line" aria-hidden="true"></div>
            </div>
            <div class="access-brand-copy">
                <span class="access-brand-accent" aria-hidden="true"></span>
                <h1 id="titulo-sistema">{{ config('expedientes.platform_name') }}</h1>
            </div>
            <img class="access-brand-hummingbird" src="{{ asset('images/logos/COLIBRI.png') }}" alt="" aria-hidden="true">
        </section>

        <section id="contenido" class="access-panel" aria-labelledby="titulo-acceso" tabindex="-1">
            <div class="access-card">
                <h2 id="titulo-acceso">{{ $tituloTarjeta }}</h2>
                @if($intro)<p class="access-intro">{{ $intro }}</p>@endif
                @if($errors->any())
                    <div class="alert alert-error" role="alert"><x-ui.icono nombre="alerta"/><p>{{ $errors->first() }}</p></div>
                @endif
                @if(session('status'))
                    <div class="alert alert-success" role="status"><x-ui.icono nombre="check"/><p>{{ session('status') }}</p></div>
                @endif
                {{ $slot }}
            </div>
        </section>
    </main>
</body>
</html>
