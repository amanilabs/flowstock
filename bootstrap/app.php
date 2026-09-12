<?php

use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Http\Middleware\SetPermissionsTeamId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

        // One consolidated renderer for every API/JSON error response, so the
        // shape is consistent and never leaks a trace/file/line — regardless
        // of APP_DEBUG. This never affects reporting: Handler::report() still
        // logs the full exception (message, trace) to storage/logs/laravel.log
        // as normal; this only controls what the CLIENT receives.
        $exceptions->render(function (Throwable $e, $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null; // let web requests keep normal (debug) HTML rendering
            }

            if ($e instanceof ValidationException) {
                return null; // Laravel's built-in {message, errors} shape is already safe
            }

            if ($e instanceof InsufficientStockException) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            if ($e instanceof InvalidOrderTransitionException) {
                return response()->json(['message' => $e->getMessage()], 409);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
                return response()->json(['message' => 'This action is unauthorized.'], 403);
            }

            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return response()->json(['message' => 'Not found.'], 404);
            }

            if ($e instanceof HttpExceptionInterface) {
                return response()->json(['message' => $e->getMessage() ?: 'Error.'], $e->getStatusCode());
            }

            // Anything else: never expose the real message/trace to the client.
            return response()->json(['message' => 'Server Error'], 500);
        });
    })->create();
