<?php

namespace Tests\Feature;

use App\Services\Identity\LaravelOidcClient;
use phpseclib3\Crypt\RSA;
use Tests\TestCase;

class OidcValidationTest extends TestCase
{
    private function client(): LaravelOidcClient
    {
        config(['identity.issuer' => 'https://identity.test/realms/seiem',
            'identity.client_id' => 'expedientes-seiem', 'identity.client_secret' => 'test-only',
            'identity.redirect_uri' => 'https://app.test/auth/callback']);

        return new class extends LaravelOidcClient
        {
            public string $jwks = '';

            protected function fetchURL(string $url, ?string $post_body = null, array $headers = [])
            {
                if ($url !== 'https://identity.test/keys') {
                    throw new \RuntimeException('Unexpected network access');
                }

                return $this->jwks;
            }

            protected function getNonce()
            {
                return 'expected-nonce';
            }

            public function claimsAreValid(object $claims): bool
            {
                return $this->verifyJWTClaims($claims);
            }
        };
    }

    private function encode(array $value): string
    {
        return rtrim(strtr(base64_encode(json_encode($value)), '+/', '-_'), '=');
    }

    public function test_signature_is_verified_against_provider_keys_and_tampering_is_rejected(): void
    {
        $key = RSA::createKey(2048)->withPadding(RSA::SIGNATURE_PKCS1)->withHash('sha256');
        $client = $this->client();
        $client->providerConfigParam(['jwks_uri' => 'https://identity.test/keys']);
        $client->jwks = $key->getPublicKey()->toString('JWK');
        $payload = $this->encode(['alg' => 'RS256']).'.'.$this->encode(['sub' => 'signed-subject']);
        $signature = rtrim(strtr(base64_encode($key->sign($payload)), '+/', '-_'), '=');
        $this->assertTrue($client->verifyJWTSignature($payload.'.'.$signature));
        $tampered = $this->encode(['alg' => 'RS256']).'.'.$this->encode(['sub' => 'attacker']);
        $this->assertFalse($client->verifyJWTSignature($tampered.'.'.$signature));
        $this->assertFalse($client->verifyJWTSignature($this->encode(['alg' => 'none']).'.e30.unsigned'));
        $this->assertFalse($client->verifyJWTSignature('malformed'));
    }

    public function test_issuer_audience_expiry_nonce_and_authorized_party_are_required(): void
    {
        $client = $this->client();
        $valid = ['iss' => config('identity.issuer'), 'aud' => config('identity.client_id'),
            'sub' => 'verified-user', 'nonce' => 'expected-nonce', 'exp' => time() + 300, 'iat' => time()];
        $client->setIdToken($this->encode(['alg' => 'RS256']).'.'.$this->encode($valid).'.signature');
        $this->assertTrue($client->claimsAreValid((object) $valid));
        foreach ([['iss' => 'https://other.test'], ['aud' => 'another-app'], ['exp' => time() - 120],
            ['nonce' => 'forged'], ['iat' => time() + 300], ['sub' => ''],
            ['aud' => ['expedientes-seiem', 'other-app']], ['azp' => 'other-app']] as $changes) {
            $this->assertFalse($client->claimsAreValid((object) array_replace($valid, $changes)));
        }
    }
}
