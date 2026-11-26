<div class="header-account-menu">
    <button class="account-trigger" type="button" popovertarget="header-account-panel" aria-controls="header-account-panel" aria-expanded="false" aria-label="Cuenta de {{ auth()->user()->name }}" data-account-trigger>
        <span class="account-identity"><span class="account-name" title="{{ auth()->user()->name }}">{{ auth()->user()->name }}</span><small>{{ auth()->user()->roleLabel() }}</small></span>
        <x-ui.icono nombre="chevron" class="icono-sm"/>
    </button>
    <div id="header-account-panel" class="account-menu-panel" popover="auto" data-menu-acciones>
        <p>{{ config('identity.enabled') ? 'Cuenta institucional' : 'Mi cuenta' }}</p>
        <form method="POST" action="{{ route('logout') }}" data-cargando="Cerrando sesión…">
            @csrf
            <button class="account-signout" type="submit"><x-ui.icono nombre="salir" class="icono-sm"/>{{ config('identity.enabled') ? 'Cerrar sesión institucional' : 'Cerrar sesión' }}</button>
        </form>
    </div>
</div>
