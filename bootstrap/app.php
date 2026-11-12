<?php

use App\Http\Middleware\LuisFigueroa\CabecerasSeguridad;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(CabecerasSeguridad::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Same Spanish error shape as the 422 of tema 2.2. Messages never include tokens or credentials.
        $exceptions->render(fn (AuthenticationException $e, Request $request) => $request->is('api/*')
            ? response()->json(['mensaje' => 'No autenticado.'], 401, [], JSON_UNESCAPED_UNICODE) : null);

        $exceptions->render(fn (AuthorizationException|AccessDeniedHttpException $e, Request $request) => $request->is('api/*')
            ? response()->json(['mensaje' => 'No tiene permiso para esta acción.'], 403, [], JSON_UNESCAPED_UNICODE) : null);

        $exceptions->render(fn (ValidationException $e, Request $request) => $request->is('api/*')
            ? response()->json([
                'mensaje' => 'Los datos enviados no cumplen el contrato.',
                'errores' => $e->errors(),
            ], 422, [], JSON_UNESCAPED_UNICODE) : null);
    })->create();
