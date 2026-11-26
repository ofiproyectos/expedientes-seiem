<?php

namespace App\Services\Identity;

use Illuminate\Support\Facades\Http;

final class PlatformAccess
{
    public function allowed(string $issuer, string $subject): bool
    {
        abort_unless(config('identity.access_url') && config('identity.access_token'), 503, 'La verificación de acceso institucional no está configurada.');
        try {
            $response = Http::withToken(config('identity.access_token'))->acceptJson()
                ->withOptions(['verify' => config('identity.ca_bundle') ?: true])
                ->connectTimeout(3)->timeout(8)->withoutRedirecting()
                ->post(config('identity.access_url'), ['issuer' => $issuer, 'subject' => $subject]);
        } catch (\Illuminate\Http\Client\ConnectionException) {
            abort(503, 'No fue posible verificar tu acceso institucional. Intenta nuevamente.');
        }
        if ($response->status() === 403) {
            return false;
        }
        abort_unless($response->ok() && $response->json('allowed') === true, 503, 'No fue posible verificar tu acceso institucional. Intenta nuevamente.');
        return true;
    }
}
