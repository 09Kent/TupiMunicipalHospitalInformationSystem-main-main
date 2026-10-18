<?php

require_once __DIR__ . '/../app/Helpers/helpers.php';

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'admin/api/*',
            'director/api/*',
            'register/api/*',
            'register/submit',
            'register/classify',
            'api/registration/*',
            'billing/api/*',
            'pharmacy/api/*',
            'medtech/*',
            'records/*',
            'register/patients/*',
            'doctor/*',
            'nurse/*',
            'register/appointments/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

// Support writable storage directory on Vercel serverless functions
if (isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL']) || getenv('VERCEL') || env('APP_STORAGE')) {
    $storagePath = env('APP_STORAGE', '/tmp/storage');
    $dirs = [
        $storagePath . '/framework/views',
        $storagePath . '/framework/cache/data',
        $storagePath . '/framework/sessions',
        $storagePath . '/logs',
        $storagePath . '/app/public',
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
    $app->useStoragePath($storagePath);
}

return $app;

