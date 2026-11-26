<?php

return [
    'bootstrap_admin_sub' => env('OIDC_BOOTSTRAP_ADMIN_SUB'),
    'enabled' => env('OIDC_ENABLED', null),
    'issuer' => env('OIDC_ISSUER'),
    'client_id' => env('OIDC_CLIENT_ID', 'expedientes-seiem'),
    'client_secret' => env('OIDC_CLIENT_SECRET'),
    'redirect_uri' => env('OIDC_REDIRECT_URI'),
    'portal_url' => env('PLATFORM_URL'),
    'access_url' => env('PLATFORM_ACCESS_URL'),
    'access_token' => env('PLATFORM_ACCESS_TOKEN'),
    'ca_bundle' => env('OIDC_CA_BUNDLE'),
];
