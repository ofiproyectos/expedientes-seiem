<x-layouts.acceso titulo="Iniciar sesión" titulo-tarjeta="Inicia sesión" intro="Ingresa tus credenciales para continuar.">
    <form method="POST" action="{{ route('login') }}" autocomplete="on" data-cargando="Iniciando sesión…">
        @csrf
        <div class="form-field">
            <label for="username">Usuario <span class="access-required">(obligatorio)</span></label>
            <input class="input-text" id="username" type="text" name="username" value="{{ old('username') }}" maxlength="50" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus aria-required="true" @error('username') aria-invalid="true" aria-describedby="username-error" @enderror>
            <x-ui.error-campo campo="username" id="username-error"/>
        </div>
        <div class="form-field">
            <label for="password">Contraseña <span class="access-required">(obligatorio)</span></label>
            <div class="password-field"><input class="input-text" id="password" type="password" name="password" maxlength="1024" autocomplete="current-password" required aria-required="true" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror><button type="button" class="password-toggle" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Mostrar contraseña">Mostrar</button></div>
            <x-ui.error-campo campo="password" id="password-error"/>
        </div>
        <button class="btn-primary btn-block access-submit" type="submit">Iniciar sesión</button>
    </form>
</x-layouts.acceso>
