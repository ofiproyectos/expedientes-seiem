@php($titulo = $user ? 'Editar usuario' : 'Crear usuario')
<x-layouts.institucional :titulo="$titulo">
    <x-ui.migas :enlaces="[route('users.index') => 'Usuarios y permisos']" :actual="$titulo"/>
    <div class="page-title">
        <div>
            <h1>{{ $titulo }}</h1>
            <p class="muted">Los campos marcados con <span class="required" aria-hidden="true">*</span> son obligatorios.</p>
        </div>
    </div>

    <section class="card narrow-card">
        <form method="POST" action="{{ $user ? route('users.update', $user) : route('users.store') }}" data-cargando="{{ $user ? 'Guardando cambios…' : 'Creando usuario…' }}">
            @csrf @if($user) @method('PUT') @endif
            <div class="form-field">
                <label for="name">Nombre completo<span class="required" aria-hidden="true">*</span></label>
                <input class="input-text" id="name" name="name" value="{{ old('name', $user?->name) }}" maxlength="255" required aria-required="true" autocomplete="off" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                <x-ui.error-campo campo="name" id="name-error"/>
            </div>
            <div class="form-field">
                <label for="username">Nombre de usuario<span class="required" aria-hidden="true">*</span></label>
                <input class="input-text" id="username" name="username" value="{{ old('username', $user?->username) }}" minlength="3" maxlength="50" pattern="[a-zA-Z0-9._\-]+" required aria-required="true" autocomplete="off" autocapitalize="none" spellcheck="false" aria-describedby="username-hint @error('username') username-error @enderror" @error('username') aria-invalid="true" @enderror>
                <small id="username-hint" class="form-hint">Sin espacios ni acentos; por ejemplo, maria.lopez.</small>
                <x-ui.error-campo campo="username" id="username-error"/>
            </div>
                <div class="form-field">
                    <label for="role">Rol<span class="required" aria-hidden="true">*</span></label>
                    <select class="input-text" id="role" name="role" required aria-required="true" @error('role') aria-describedby="role-error" aria-invalid="true" @enderror>
                        @foreach(config('expedientes.roles') as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected(old('role', $user?->role ?? 'READER') === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    <x-ui.error-campo campo="role" id="role-error"/>
                </div>
                <div class="form-field">
                    <label for="is_active">Acceso<span class="required" aria-hidden="true">*</span></label>
                    <select class="input-text" id="is_active" name="is_active" required aria-required="true" @error('is_active') aria-describedby="is_active-error" aria-invalid="true" @enderror>
                        <option value="1" @selected((string) old('is_active', (int) ($user?->is_active ?? true)) === '1')>Activo</option>
                        <option value="0" @selected((string) old('is_active', (int) ($user?->is_active ?? true)) === '0')>Desactivado</option>
                    </select>
                    <x-ui.error-campo campo="is_active" id="is_active-error"/>
                </div>
            @if($user)
                <details class="account-password" @if($errors->has('password') || $errors->has('password_confirmation')) open @endif>
                    <summary>Restablecer contraseña</summary>
                    <p class="form-hint">Al restablecerla se cierran las sesiones abiertas de esta cuenta.</p>
            @endif
            <div class="form-field">
                <label for="password">{{ $user ? 'Nueva contraseña' : 'Contraseña' }}@unless($user)<span class="required" aria-hidden="true">*</span>@endunless</label>
                <div class="password-field"><input class="input-text" id="password" type="password" name="password" minlength="12" autocomplete="new-password" @required(! $user) @unless($user) aria-required="true" @endunless aria-describedby="password-hint @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror><button type="button" class="password-toggle" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Mostrar contraseña">Mostrar</button></div>
                <small id="password-hint" class="form-hint">Usa al menos 12 caracteres, letras y números.</small>
                <x-ui.error-campo campo="password" id="password-error"/>
            </div>
            <div class="form-field">
                <label for="password_confirmation">Confirmar contraseña @unless($user)<span class="required" aria-hidden="true">*</span>@endunless</label>
                <div class="password-field"><input class="input-text" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" @required(! $user)><button type="button" class="password-toggle" data-password-toggle aria-controls="password_confirmation" aria-pressed="false" aria-label="Mostrar contraseña">Mostrar</button></div>
            </div>
            @if($user)</details>@endif
            <div class="form-actions">
                <x-ui.boton type="submit"><x-ui.icono :nombre="$user ? 'check' : 'agregar'" class="icono-sm"/>{{ $user ? 'Guardar cambios' : 'Crear usuario' }}</x-ui.boton>
                <x-ui.boton variante="secondary" :href="route('users.index')">Cancelar</x-ui.boton>
            </div>
        </form>
    </section>

    @if($user)
        <p class="form-hint account-metadata">Alta: {{ $user->created_at->format('d/m/Y H:i') }}{{ $user->creator ? ' por '.$user->creator->name : '' }} · Último acceso: {{ $user->last_login_at?->format('d/m/Y H:i') ?? 'sin accesos' }}</p>
    @endif

    @if($user)
        <section class="card" aria-labelledby="titulo-permisos-usuario">
            <div class="section-heading">
                <div>
                    <h2 id="titulo-permisos-usuario">Expedientes autorizados</h2>
                    <p class="muted">Permisos de lectura vigentes, programados, expirados y revocados de esta cuenta.</p>
                </div>
            </div>
            @include('partials.grants-table', ['grants' => $grants, 'mostrarExpediente' => true, 'revocable' => false])
        </section>
    @endif
</x-layouts.institucional>
