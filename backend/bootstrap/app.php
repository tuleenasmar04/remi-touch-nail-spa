<?php

use App\Exceptions\DomainConflict;
use App\Http\Middleware\Locale;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Private API routes already use the web session/CSRF pipeline exactly once.
        $middleware->append(SecurityHeaders::class);
        $middleware->append(Locale::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => __('The given data was invalid.'), 'code' => 'validation_failed', 'errors' => $e->errors()], 422);
            }
        });
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => app()->getLocale() === 'ar' ? 'يرجى تسجيل الدخول.' : 'Please sign in.', 'code' => 'unauthenticated'], 401);
            }
        });
        $exceptions->render(function (DomainConflict $e, Request $request) {
            $translated = __('conflicts.'.$e->errorCode);

            return response()->json(['message' => $translated === 'conflicts.'.$e->errorCode ? $e->getMessage() : $translated, 'code' => $e->errorCode], $e->httpStatus);
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
