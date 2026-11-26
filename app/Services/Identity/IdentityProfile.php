<?php

namespace App\Services\Identity;

use LogicException;

/** Server configuration only; never inferred from request data or provider availability. */
final class IdentityProfile
{
    public function validate(): void
    {
        $enabled = config('identity.enabled');
        if (! is_bool($enabled)) {
            throw new LogicException('OIDC_ENABLED debe ser explicitamente true o false.');
        }
        if (! $enabled) {
            if (! app()->environment(['local', 'testing'])) {
                throw new LogicException('La autenticacion local solo esta habilitada en desarrollo y pruebas.');
            }
            foreach (['issuer', 'client_secret', 'redirect_uri', 'access_url', 'access_token', 'portal_url', 'bootstrap_admin_sub'] as $key) {
                if (filled(config('identity.'.$key))) {
                    throw new LogicException('El perfil local no admite configuracion institucional activa: '.$key);
                }
            }

            return;
        }
        foreach (['issuer', 'redirect_uri', 'portal_url', 'access_url'] as $key) {
            $url = config('identity.'.$key);
            if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)
                || parse_url($url, PHP_URL_SCHEME) !== 'https'
                || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
                throw new LogicException('La configuracion institucional requiere una URL HTTPS valida: '.$key);
            }
        }
        foreach (['client_id', 'client_secret', 'access_token'] as $key) {
            if (! is_string(config('identity.'.$key)) || trim(config('identity.'.$key)) === '') {
                throw new LogicException('Falta configuracion institucional privada: '.$key);
            }
        }
        if (! app()->environment('testing') && (config('app.debug') || config('session.driver') !== 'database'
            || ! config('session.secure'))) {
            throw new LogicException('El perfil institucional requiere APP_DEBUG=false, sesiones database y cookie segura.');
        }
    }

    public function canManageLocalAccounts(): bool
    {
        return config('identity.enabled') === false && app()->environment(['local', 'testing']);
    }
}
