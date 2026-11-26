<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Identity\AuthorizationRedirect;
use App\Services\Identity\LaravelOidcClient;
use App\Services\Identity\PlatformAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Jumbojett\OpenIDConnectClientException;

class InstitutionalAuthController extends Controller
{
    public function redirect(Request $request)
    {
        abort_unless(config('identity.enabled'), 404);
        $request->session()->put('oidc.started_at', time());
        if (Auth::check()) {
            return redirect()->route('expedientes.index');
        }
        try {
            $previous = $_REQUEST;
            try {
                $_REQUEST = [];
                app(LaravelOidcClient::class)->authenticate();
            } finally {
                $_REQUEST = $previous;
            }
        } catch (AuthorizationRedirect $redirect) {
            return redirect()->away($redirect->url);
        } catch (OpenIDConnectClientException) {
            return $this->message('Acceso temporalmente no disponible', 'No fue posible conectar con el inicio de sesión institucional. Intenta de nuevo más tarde.', 503);
        }
        abort(400);
    }

    public function callback(Request $request)
    {
        abort_unless(config('identity.enabled'), 404);
        $state = $request->session()->get('oidc.handshake.openid_connect_state');
        if (! is_string($state) || ! is_string($request->query('state'))
            || ! hash_equals($state, $request->query('state'))
            || (int) $request->session()->get('oidc.started_at', 0) < time() - 600
            || ! is_string($request->query('code'))) {
            $request->session()->forget('oidc');

            return $this->message('No se pudo completar el acceso', 'La solicitud de acceso venció o no es válida. Inicia sesión nuevamente.', 400);
        }
        try {
            $client = app(LaravelOidcClient::class);
            $previous = $_REQUEST;
            try {
                $_REQUEST = $request->query();
                abort_unless($client->authenticate(), 400);
            } finally {
                $_REQUEST = $previous;
            }
            $claims = $client->getVerifiedClaims();
        } catch (OpenIDConnectClientException) {
            $request->session()->forget('oidc');

            return $this->message('No se pudo completar el acceso', 'No fue posible verificar tu identidad. Inicia sesión nuevamente.', 400);
        }
        $issuer = config('identity.issuer');
        abort_unless(app(PlatformAccess::class)->allowed($issuer, $claims->sub), 403, 'Tu cuenta no tiene acceso a Expedientes desde la plataforma.');
        $user = User::where('identity_issuer', $issuer)->where('identity_subject', $claims->sub)->first();
        if (! $user) {
            // Approval comes from the platform; existing accounts are never linked by name/email.
            $user = new User;
            $user->identity_issuer = $issuer;
            $user->identity_subject = $claims->sub;
            $candidate = mb_strtolower((string) ($claims->preferred_username ?? ''));
            $user->username = preg_match('/^[a-z0-9._-]{1,50}$/', $candidate) && ! User::where('username', $candidate)->exists()
                ? $candidate : 'sso.'.substr(hash('sha256', $issuer.'|'.$claims->sub), 0, 40);
            $user->password = null;
            $user->role = hash_equals((string) config('identity.bootstrap_admin_sub'), $claims->sub) ? 'ADMIN' : 'READER';
            $user->is_active = true;
        }
        abort_unless(is_string($claims->sid ?? null) && $claims->sid !== '', 400);
        $user->name = mb_substr((string) ($claims->name ?? $claims->preferred_username ?? 'Cuenta institucional'), 0, 255);
        $user->save();
        if (! $user->is_active) {
            return $this->message('Cuenta suspendida', 'Tu acceso a Expedientes está suspendido. Contacta a su administración.', 403);
        }
        $user->last_login_at = now();
        $user->save();
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('oidc');
        $request->session()->put('identity.id_token', $client->getIdToken());
        DB::table('identity_sessions')->insert([
            'session_id' => $request->session()->getId(), 'user_id' => $user->id,
            'provider_sid' => $claims->sid, 'created_at' => now(),
        ]);

        return redirect()->route('expedientes.index');
    }

    public function logout(Request $request)
    {
        abort_unless(config('identity.enabled'), 404);
        $idToken = $request->session()->get('identity.id_token');
        DB::table('identity_sessions')->where('session_id', $request->session()->getId())->delete();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if (is_string($idToken)) {
            try {
                app(LaravelOidcClient::class)->signOut($idToken, rtrim(config('identity.portal_url'), '/').'/');
            } catch (AuthorizationRedirect $redirect) {
                return redirect()->away($redirect->url);
            } catch (OpenIDConnectClientException) {
                return $this->message('Sesión local cerrada', 'No fue posible confirmar el cierre institucional. Puedes volver a la plataforma.', 503);
            }
        }

        return redirect()->away(config('identity.portal_url'));
    }

    public function backchannel(Request $request)
    {
        abort_unless(config('identity.enabled'), 404);
        $request->validate(['logout_token' => ['required', 'string', 'max:16384']]);
        try {
            $client = app(LaravelOidcClient::class);
            $previous = $_REQUEST;
            try {
                $_REQUEST = $request->only('logout_token');
                abort_unless($client->verifyLogoutToken(), 400);
            } finally {
                $_REQUEST = $previous;
            }
            $claims = $client->getVerifiedClaims();
        } catch (OpenIDConnectClientException) {
            abort(400);
        }
        abort_unless(isset($claims->events->{'http://schemas.openid.net/event/backchannel-logout'})
            && is_string($claims->jti ?? null) && is_int($claims->iat ?? null)
            && $claims->iat >= time() - 120 && $claims->iat <= time() + 30, 400);
        if (! Cache::add('oidc.logout.'.hash('sha256', $claims->jti), true, 300)) {
            return response('', 200);
        }
        $query = DB::table('identity_sessions')->join('users', 'users.id', '=', 'identity_sessions.user_id')
            ->where('users.identity_issuer', config('identity.issuer'));
        if (isset($claims->sid)) {
            $query->where('provider_sid', $claims->sid);
        }
        if (isset($claims->sub)) {
            $query->where('users.identity_subject', $claims->sub);
        }
        $ids = $query->pluck('session_id');
        DB::table('sessions')->whereIn('id', $ids)->delete();
        DB::table('identity_sessions')->whereIn('session_id', $ids)->delete();

        return response('', 200)->header('Cache-Control', 'no-store');
    }

    private function message(string $title, string $description, int $status)
    {
        return response()->view('auth.message', compact('title', 'description'), $status)->header('Cache-Control', 'no-store');
    }
}
