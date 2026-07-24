<?php

require __DIR__.'/../vendor/autoload.php';

$database = getenv('DB_DATABASE') ?: '';
if (getenv('APP_ENV') !== 'testing'
    || getenv('DB_CONNECTION') !== 'pgsql'
    || ! str_ends_with($database, '_test')) {
    throw new RuntimeException('PHPUnit yalnız ayrı bir PostgreSQL *_test veritabanında çalıştırılabilir.');
}

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$exitCode = Illuminate\Support\Facades\Artisan::call('migrate', [
    '--force' => true,
    '--no-interaction' => true,
]);
if ($exitCode !== 0) {
    throw new RuntimeException(Illuminate\Support\Facades\Artisan::output());
}
