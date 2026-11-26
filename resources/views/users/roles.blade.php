<x-layouts.institucional titulo="Permisos del usuario">
    <x-ui.migas :enlaces="[route('users.index') => 'Usuarios y permisos']" actual="Permisos del usuario"/>
    <h1>Permisos del usuario</h1>
    <section class="card narrow-card">
        <p>La plataforma administra la identidad y las credenciales. Aqui se administra el acceso interno a Expedientes.</p>
        <label for="institutional-name">Nombre institucional</label>
        <input id="institutional-name" class="input-text" value="{{ $user->name }}" readonly>
        <label for="institutional-username">Usuario institucional</label>
        <input id="institutional-username" class="input-text" value="{{ $user->username }}" readonly>
        <form method="POST" action="{{ route('users.update', $user) }}">
            @csrf @method('PUT')
            <div class="form-field">
                <label for="role">Rol interno</label>
                <select id="role" name="role" class="input-text" required>
                    @foreach(config('expedientes.roles') as $value => $label)
                        <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-ui.error-campo campo="role" id="role-error"/>
            </div>
            <div class="form-field">
                <label for="is_active">Acceso a Expedientes</label>
                <select id="is_active" name="is_active" class="input-text" required>
                    <option value="1" @selected((string) old('is_active', (int) $user->is_active) === '1')>Activo</option>
                    <option value="0" @selected((string) old('is_active', (int) $user->is_active) === '0')>Suspendido en este sistema</option>
                </select>
                <x-ui.error-campo campo="is_active" id="is-active-error"/>
            </div>
            <x-ui.boton type="submit">Guardar permisos</x-ui.boton>
            <x-ui.boton variante="secondary" :href="route('users.index')">Cancelar</x-ui.boton>
        </form>
    </section>
</x-layouts.institucional>
