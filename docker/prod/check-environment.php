<?php

// Executed by the production image before Laravel caches its configuration.
// This script intentionally reads process variables before Laravel boots.
$required = ['APP_KEY', 'APP_URL', 'DB_PASSWORD', 'REDIS_PASSWORD'];
$errors = [];
foreach ($required as $name) {
    if (! getenv($name)) {
        $errors[] = "$name is required";
    }
}
if (getenv('APP_ENV') !== 'production' || filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN)) {
    $errors[] = 'APP_ENV must be production and APP_DEBUG must be false';
}
if (parse_url(getenv('APP_URL') ?: '', PHP_URL_SCHEME) !== 'https') {
    $errors[] = 'APP_URL must use HTTPS';
}
foreach (['QUEUE_CONNECTION', 'CACHE_STORE', 'SESSION_DRIVER'] as $name) {
    if (getenv($name) !== 'redis') {
        $errors[] = "$name must use redis";
    }
}
if (! filter_var(getenv('SESSION_SECURE_COOKIE'), FILTER_VALIDATE_BOOLEAN)) {
    $errors[] = 'SESSION_SECURE_COOKIE must be true';
}
if ((int) getenv('REDIS_QUEUE_RETRY_AFTER') <= 270) {
    $errors[] = 'Redis retry must exceed the Horizon worker timeout (270s).';
}
if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
    exit(1);
}
