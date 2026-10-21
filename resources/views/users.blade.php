<x-layouts.institucional titulo="Usuarios y permisos">
    <div class="page-title">
        <div>
            <h1>Usuarios y permisos</h1>
            <p class="muted">Administra cuentas, roles y acceso. Los permisos de lectura de cada usuario se consultan en su ficha.</p>
        </div>
        <x-ui.boton :href="route('users.create')"><x-ui.icono nombre="agregar" class="icono-sm"/>Crear usuario</x-ui.boton>
    </div>

    <section class="card" aria-labelledby="titulo-usuarios" data-listado-dinamico>
        <div class="requests-header">
            <div>
                <div class="section-title"><x-ui.icono nombre="usuarios"/><h2 id="titulo-usuarios">Usuarios</h2></div>
                <span class="muted" data-listado-total>{{ $users->total() }} {{ $users->total() === 1 ? 'cuenta' : 'cuentas' }}</span>
            </div>
        </div>

        <form class="filter-bar" method="GET" action="{{ route('users.index') }}" role="search">
            <div class="form-field search-field">
                <label for="usuarios-q">Nombre o usuario</label>
                <div class="input-icon"><x-ui.icono nombre="buscar"/><input class="input-text" id="usuarios-q" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Buscar usuario"></div>
            </div>
            <div class="form-actions">
                <x-ui.boton variante="secondary" :href="route('users.index')" data-limpiar-filtros title="Limpiar filtros" aria-label="Limpiar filtros"><x-ui.icono nombre="actualizar" class="icono-sm"/></x-ui.boton>
                <noscript><x-ui.boton type="submit">Buscar</x-ui.boton></noscript>
            </div>
        </form>

        <p class="muted" role="status" data-listado-aviso hidden></p>
        <div data-listado-resultados>
        <div class="table-scroll" role="region" aria-labelledby="titulo-usuarios" tabindex="0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Usuario</th>
                        <th scope="col">Rol</th>
                        <th scope="col">Acceso</th>
                        <th scope="col">Último acceso</th>
                        <th scope="col" class="cell-action">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td class="cell-primary"><strong>{{ $user->name }}</strong><small>{{ $user->username }}</small></td>
                            <td>{{ $user->roleLabel() }}</td>
                            <td>@if($user->is_active)<span class="status-badge status-activo">Activo</span>@else<span class="status-badge status-inactivo">Desactivado</span>@endif</td>
                            <td class="cell-nowrap">@if($user->last_login_at){{ $user->last_login_at->format('d/m/Y') }}<small>{{ $user->last_login_at->format('H:i') }}</small>@else Sin accesos @endif</td>
                            <td class="cell-action"><a class="table-action" href="{{ route('users.edit', $user) }}"><x-ui.icono nombre="editar"/>Editar<span class="sr-only"> usuario {{ $user->name }}</span></a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state-cell">
                                <div class="empty-state"><x-ui.icono nombre="buscar"/><h3>Sin coincidencias</h3><p>Ningún usuario coincide con la búsqueda.</p></div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $users->links('pagination.institucional') }}
        </div>
    </section>
</x-layouts.institucional>
