<?php

namespace App\Http\Middleware;

use App\Services\Identity\PlatformAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VerifyPlatformAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (config('identity.enabled') && $user) {
            $allowed = $user->identity_issuer === config('identity.issuer') && is_string($user->identity_subject)
                && app(PlatformAccess::class)->allowed($user->identity_issuer, $user->identity_subject);
            if (! $allowed) {
                DB::table('identity_sessions')->where('session_id', $request->session()->getId())->delete();
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                abort(403, 'Tu cuenta no tiene acceso a Expedientes desde la plataforma.');
            }
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
