<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()?->is_active) {
            if (config('identity.enabled')) \Illuminate\Support\Facades\DB::table('identity_sessions')->where('session_id', $request->session()->getId())->delete();
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['username' => 'La cuenta está desactivada.']);
        }

        return $next($request);
    }
}
