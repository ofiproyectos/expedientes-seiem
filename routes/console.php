<?php

use App\Models\AccessGrant;
use App\Models\Expediente;
use App\Models\User;
use App\Services\Identity\IdentityProfile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

Artisan::command('expedientes:admin {username} {--name=Administrador}', function () {
    if (! app(IdentityProfile::class)->canManageLocalAccounts()) {
        $this->error('La creaci?n de cuentas locales est? deshabilitada en este perfil.');

        return 1;
    }
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

Artisan::command('expedientes:demo', function () {
    $profile = app(IdentityProfile::class);
    $database = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
    $documents = str_replace('\\', '/', Storage::disk('documentos')->path(''));
    if (! $profile->canManageLocalAccounts() || config('database.default') !== 'sqlite'
        || realpath($database) !== realpath(database_path('dev.sqlite'))
        || rtrim($documents, '/') !== str_replace('\\', '/', storage_path('framework/testing/dev-documents'))) {
        $this->error('Solo se permiten database/dev.sqlite y storage/framework/testing/dev-documents con perfil local.');

        return 1;
    }
    $names = ['demo.admin', 'demo.editor', 'demo.lector', 'demo.suspendido'];
    if (User::whereIn('username', $names)->exists()) {
        $this->error('Ya existen cuentas demo. No se reemplazan usuarios ni credenciales.');

        return 1;
    }
    $credentials = [];
    DB::transaction(function () use (&$credentials) {
        foreach (['admin' => 'ADMIN', 'editor' => 'LOADER', 'lector' => 'READER', 'suspendido' => 'READER'] as $name => $role) {
            $password = Str::random(24).'7a';
            $accounts[$name] = User::create(['name' => 'Demostracion '.$name, 'username' => 'demo.'.$name,
                'password' => $password, 'role' => $role, 'is_active' => $name !== 'suspendido']);
            $credentials[] = ['demo.'.$name, $password, $role];
        }
        $file = Expediente::create(['name' => 'Expediente ficticio de desarrollo', 'responsible_id' => $accounts['editor']->id]);
        AccessGrant::create(['user_id' => $accounts['lector']->id, 'expediente_id' => $file->id,
            'granted_by' => $accounts['admin']->id, 'can_download' => true, 'starts_at' => now(), 'expires_at' => now()->addDays(7)]);
    });
    $this->table(['Usuario ficticio', 'Clave generada (guardar localmente)', 'Rol'], $credentials);
})->purpose('Crear datos ficticios exclusivamente en la base SQLite aislada de desarrollo');
