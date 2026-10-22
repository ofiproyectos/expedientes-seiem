@props(['conMenu' => false, 'inicio' => null, 'etiqueta' => null])
<header class="app-header">
    <div class="app-header-inner">
        <div class="brand-group">
            @if($conMenu)<button class="icon-button menu-toggle" type="button" data-menu-toggle aria-expanded="false" aria-controls="navegacion" aria-label="Abrir menú"><x-ui.icono nombre="menu"/></button>@endif
            <a class="brand" href="{{ $inicio ?? route('login') }}">
                <img class="brand-logo" src="{{ asset('images/logos/LOGOS.png') }}" alt="Gobierno del Estado de México. Estado de México, ¡El poder de servir!" width="1141" height="293">
            </a>
        </div>
        <div class="header-context">
            <span class="platform-label">{{ config('expedientes.platform_name') }}</span>
            @auth
                <span class="header-user"><span><span class="header-user-name" title="{{ auth()->user()->username }}">{{ auth()->user()->username }}</span><small>{{ auth()->user()->roleLabel() }}</small></span></span>
                @unless($conMenu)
                    <form method="POST" action="{{ route('logout') }}" data-cargando="Cerrando sesión…">@csrf<button class="header-logout" type="submit" aria-label="Cerrar sesión" title="Cerrar sesión"><x-ui.icono nombre="salir" class="icono-sm"/></button></form>
                @endunless
            @else
                <span class="header-label">{{ $etiqueta ?? 'Acceso de personal' }}</span>
            @endauth
        </div>
    </div>
</header>
