<?php

namespace App\Services\Identity;

use Jumbojett\OpenIDConnectClient;

/** Keep the OIDC handshake in Laravel's session, never a second PHP session. */
class LaravelOidcClient extends OpenIDConnectClient
{
    public function __construct()
    {
        parent::__construct(config('identity.issuer'), config('identity.client_id'), config('identity.client_secret'));
        $this->setRedirectURL(config('identity.redirect_uri'));
        $this->setCodeChallengeMethod('S256');
        $this->setAllowImplicitFlow(false);
        $this->setTimeout(15);
        if (config('identity.ca_bundle')) {
            $this->setCertPath(config('identity.ca_bundle'));
        }
        $this->addScope(['profile', 'email']);
    }

    protected function startSession() {}

    protected function commitSession() {}

    protected function getSessionKey(string $key)
    {
        return session()->get('oidc.handshake.'.$key, false);
    }

    protected function setSessionKey(string $key, $value)
    {
        session()->put('oidc.handshake.'.$key, $value);
    }

    protected function unsetSessionKey(string $key)
    {
        session()->forget('oidc.handshake.'.$key);
    }

    public function redirect(string $url)
    {
        throw new AuthorizationRedirect($url);
    }

    public function verifyJWTSignature(string $jwt): bool
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return false;
        }
        $header = json_decode(\Jumbojett\base64url_decode($parts[0]), true);

        return is_array($header) && ($header['alg'] ?? null) === 'RS256'
            && ! isset($header['jwk']) && ! isset($header['jku']) && parent::verifyJWTSignature($jwt);
    }

    protected function validateIssuer(string $iss): bool
    {
        return $iss === config('identity.issuer');
    }

    protected function verifyJWTClaims($claims, ?string $accessToken = null): bool
    {
        $nonce = $this->getNonce();
        $audiences = is_array($claims->aud ?? null) ? $claims->aud : [$claims->aud ?? null];
        if (! is_string($claims->iss ?? null) || ! $this->validateIssuer($claims->iss)
            || ! in_array(config('identity.client_id'), $audiences, true)
            || (count($audiences) > 1 && ($claims->azp ?? null) !== config('identity.client_id'))
            || ! is_string($claims->sub ?? null) || $claims->sub === ''
            || ! is_string($claims->nonce ?? null) || ! is_string($nonce) || ! hash_equals($nonce, $claims->nonce)
            || ! is_int($claims->exp ?? null) || $claims->exp < time() - 30
            || ! is_int($claims->iat ?? null) || $claims->iat > time() + 30
            || (isset($claims->azp) && $claims->azp !== config('identity.client_id'))) {
            return false;
        }

        return parent::verifyJWTClaims($claims, $accessToken);
    }
}
