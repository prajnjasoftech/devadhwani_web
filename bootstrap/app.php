<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'password.reset' => \App\Http\Middleware\EnsurePasswordReset::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'platform.admin' => \App\Http\Middleware\PlatformAdminOnly::class,
            'temple.active' => \App\Http\Middleware\CheckTempleStatus::class,
        ]);

        $middleware->statefulApi();

        // Redirect unauthenticated API requests to return JSON
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return null; // Return null to let the exception handler return 401
            }
            return '/login';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle unauthenticated API requests
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please login again.',
                ], 401);
            }
        });

        // Handle not found for API routes
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                ], 404);
            }
        });
    })->create();
