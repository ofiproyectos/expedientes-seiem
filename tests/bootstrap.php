<?php

// PHPUnit forces getenv/ENV; Windows can retain inherited values in SERVER.
// Align both sources before Laravel creates its environment repository.
foreach (['APP_CONFIG_CACHE', 'DOCUMENTS_PATH', 'APP_ENV', 'OIDC_ENABLED',
    'APP_MAINTENANCE_DRIVER', 'BCRYPT_ROUNDS', 'BROADCAST_CONNECTION', 'CACHE_STORE',
    'DB_CONNECTION', 'DB_DATABASE', 'DB_URL', 'MAIL_MAILER', 'QUEUE_CONNECTION', 'SESSION_DRIVER',
    'OIDC_ISSUER', 'OIDC_CLIENT_SECRET', 'OIDC_REDIRECT_URI', 'OIDC_BOOTSTRAP_ADMIN_SUB',
    'PLATFORM_URL', 'PLATFORM_ACCESS_URL', 'PLATFORM_ACCESS_TOKEN'] as $key) {
    $value = getenv($key);
    if ($value !== false) {
        $_SERVER[$key] = $_ENV[$key] = $value;
    }
}

require dirname(__DIR__).'/vendor/autoload.php';

// Reject test invocation with a cached configuration before providers or migrations run.
$app = require dirname(__DIR__).'/bootstrap/app.php';
if ($app->configurationIsCached()
    || getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:') {
    throw new RuntimeException('Las pruebas requieren SQLite en memoria y configuracion sin cache.');
}
unset($app);
