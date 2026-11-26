<?php

require dirname(__DIR__).'/vendor/autoload.php';

// Reject test invocation with a cached configuration before providers or migrations run.
$app = require dirname(__DIR__).'/bootstrap/app.php';
if ($app->configurationIsCached()
    || getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:') {
    throw new RuntimeException('Las pruebas requieren SQLite en memoria y configuracion sin cache.');
}
unset($app);
