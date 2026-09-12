<?php

use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Http\Middleware\SetPermissionsTeamId;
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
        $middleware->appendToGroup('web', SetPermissionsTeamId::class);
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn ($request, $throwable) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (InsufficientStockException $e, $request) {
            return response()->json(['message' => $e->getMessage()], 422);
        });

        $exceptions->render(function (InvalidOrderTransitionException $e, $request) {
            return response()->json(['message' => $e->getMessage()], 409);
        });
    })->create();
