<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /** RF-AUT-001: usuario y contraseña; mensaje genérico; cuentas desactivadas rechazadas. */
    public function login(Request $request)
    {
        $data = $request->validate(['username' => 'required|string|max:50', 'password' => 'required|string|max:1024']);
        $username = Str::lower(trim($data['username']));
        $key = 'login:'.hash('sha256', $username.'|'.$request->ip());
        $account = User::where('username', $username)->first();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            Audit::record('auth.login', 'Intento bloqueado temporalmente', $account, ['username' => $username], 'denied', $account);

            return back()->withErrors(['username' => 'Demasiados intentos. Intenta en '.RateLimiter::availableIn($key).' segundos.'])->onlyInput('username');
        }
        if (! Auth::attempt(['username' => $username, 'password' => $data['password'], 'is_active' => true])) {
            RateLimiter::hit($key, 900);
            Audit::record('auth.login', 'Credenciales inválidas o cuenta desactivada', $account, ['username' => $username], 'failure', $account);

            return back()->withErrors(['username' => 'Credenciales incorrectas o cuenta desactivada.'])->onlyInput('username');
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->user()->update(['last_login_at' => now()]);
        Audit::record('auth.login', 'Inicio de sesión', $request->user());

        return redirect()->intended(route('expedientes.index'));
    }

    /** RF-AUT-002: invalida la sesión del lado del servidor. */
    public function logout(Request $request)
    {
        Audit::record('auth.logout', 'Cierre de sesión', $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
