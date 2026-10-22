@props(['titulo' => 'Expedientes'])
{{-- Menú lateral solo cuando el rol tiene más de una sección; el lector solo consulta expedientes (manual §6.1). --}}
@php($conMenu = auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isLoader()))
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $titulo }} · {{ config('expedientes.platform_name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/institucional.css') }}?v={{ filemtime(public_path('css/institucional.css')) }}">
    <script src="{{ asset('js/institucional.js') }}?v={{ filemtime(public_path('js/institucional.js')) }}" defer></script>
</head>
<body @class(['sin-menu' => ! $conMenu])>
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <x-ui.encabezado :con-menu="$conMenu" :inicio="route('expedientes.index')"/>
    <div class="workspace-shell">
        @if($conMenu)
        <aside class="workspace-sidebar" id="navegacion">
            <nav class="navigation" aria-label="Principal">
                <p class="nav-label">Espacio de trabajo</p>
                <a href="{{ route('expedientes.index') }}" @if((request()->routeIs('expedientes.*') && ! request()->routeIs('expedientes.create')) || request()->routeIs('permissions.*')) aria-current="page" @endif><x-ui.icono nombre="documento"/>Expedientes</a>
                @can('create', App\Models\Expediente::class)
                    <a href="{{ route('expedientes.create') }}" @if(request()->routeIs('expedientes.create')) aria-current="page" @endif><x-ui.icono nombre="agregar"/>Crear expediente</a>
                @endcan
                @if(auth()->user()->isAdmin())
                <p class="nav-label nav-section">Administración</p>
                <a href="{{ route('users.index') }}" @if(request()->routeIs('users.*')) aria-current="page" @endif><x-ui.icono nombre="usuarios"/>Usuarios y permisos</a>
                @endif
                <div class="sidebar-bottom"><form method="POST" action="{{ route('logout') }}" data-cargando="Cerrando sesión…">@csrf<button class="logout-button" type="submit"><x-ui.icono nombre="salir"/>Cerrar sesión</button></form></div>
            </nav>
        </aside>
        @endif
        <div class="workspace-main">
            <main id="contenido" class="workspace-content" tabindex="-1">
                @if($errors->any())
                    <div class="alert alert-error" role="alert" tabindex="-1" data-error-summary><x-ui.icono nombre="alerta"/><div><strong>No se pudo completar la operación.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
                @endif
                {{ $slot }}
            </main>
            <x-ui.pie/>
        </div>
    </div>
    <x-ui.notificaciones/>
    <x-ui.modal-confirmacion/>
</body>
</html>
