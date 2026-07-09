<?php

define('LARAVEL_START', microtime(true));

require_once __DIR__.'/../vendor/autoload.php';

if (!getenv('DB_CONNECTION')) {
    putenv('DB_CONNECTION=mysql');
}
if (!getenv('DB_HOST')) {
    putenv('DB_HOST=localhost');
}
if (!getenv('DB_PORT')) {
    putenv('DB_PORT=3306');
}
if (!getenv('DB_DATABASE') && !getenv('DB_NAME')) {
    putenv('DB_DATABASE=law&inci');
}
if (!getenv('DB_USERNAME') && !getenv('DB_USER')) {
    putenv('DB_USERNAME=root');
}
if (!getenv('DB_PASSWORD') && !getenv('DB_PASS')) {
    putenv('DB_PASSWORD=');
}

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel application instance
| which serves as the "glue" for all the components of Laravel, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
|
| Next, we need to bind some important interfaces into the container so
| we will be able to resolve them when needed. The kernels serve the
| incoming requests to this application from both the web and CLI.
|
*/

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

return $app;
