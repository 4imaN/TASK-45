<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            \App\Http\Middleware\IdempotencyMiddleware::class,
            \App\Http\Middleware\RequestFrequencyGuard::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (\App\Common\Exceptions\BusinessRuleException $e) {
            \Illuminate\Support\Facades\Log::channel('operations')->notice('Business rule violation', [
                'message' => $e->getMessage(),
                'user_id' => request()?->user()?->id,
                'path' => request()?->path(),
            ]);
            return response()->json([
                'error' => $e->getMessage(),
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 422);
        });
    })->create();
