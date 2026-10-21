<?php

use App\Models\AccessGrant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

Artisan::command('expedientes:admin {username} {--name=Administrador}', function () {
    $username = Str::lower(trim($this->argument('username')));
    if (User::where('username', $username)->exists()) {
        $this->error('Ese nombre de usuario ya existe.');

        return 1;
    }
    $password = $this->secret('Contraseña (mínimo 12 caracteres, letras y números)');
    $validator = Validator::make(['username' => $username, 'password' => $password], ['username' => ['required', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/'], 'password' => ['required', Password::min(12)->letters()->numbers()]]);
    if ($validator->fails()) {
        $this->error($validator->errors()->first());

        return 1;
    }
    User::create(['username' => $username, 'name' => $this->option('name'), 'password' => $password, 'role' => 'ADMIN', 'is_active' => true]);
    $this->info('Administrador creado.');
})->purpose('Crear el primer administrador');
// Marca como revocados los permisos vencidos; la vigencia se evalúa además en cada petición.
Schedule::call(fn () => AccessGrant::whereNull('revoked_at')->whereNotNull('expires_at')->where('expires_at', '<=', now())->update(['revoked_at' => now()]))->everyMinute()->name('expire-access-grants')->withoutOverlapping();
