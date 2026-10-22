<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }} · {{ config('expedientes.platform_name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/institucional.css') }}">
</head>
<body class="sin-menu error-page">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <header class="app-header"><div class="app-header-inner"><img class="brand-logo" src="{{ asset('images/logos/LOGOS.png') }}" alt="Gobierno del Estado de México"><span>{{ config('expedientes.platform_name') }}</span></div></header>
    <main id="contenido" class="workspace-content" tabindex="-1">
        <div class="page-title"><div><h1>{{ $titulo }}</h1><p class="muted">{{ $descripcion }}</p></div></div>
        <section class="card narrow-card" aria-label="Recuperar la navegación">
            <p>{{ $ayuda }}</p>
            <div class="form-actions"><a class="btn-primary" href="{{ url('/expedientes') }}">Ir a expedientes</a><a class="btn-secondary" href="{{ url('/login') }}">Iniciar sesión</a></div>
        </section>
    </main>
    <x-ui.pie/>
</body>
</html>
