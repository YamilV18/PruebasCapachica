<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Middleware de Sanctum para API stateful
        $middleware->statefulApi();

        // Aliases para Spatie
        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
        ]);

        // Middleware adicional para el grupo API
        $middleware->group('api', [
            EnsureFrontendRequestsAreStateful::class,

        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // No autenticado (token inválido o no enviado)
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado. El token es inválido o no fue proporcionado.',
                ], 401);
            }
        });
    
        // Error de validación
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $e->errors(),
                ], 422);
            }
        });
    
        // Usuario no tiene permiso o rol
        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para realizar esta acción',
                ], 403);
            }
        });
    
        // Recurso no encontrado
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recurso no encontrado',
                ], 404);
            }
        });
    
        // Error interno (sólo muestra el mensaje si estás en debug)
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error interno del servidor',
                    'error' => config('app.debug') ? $e->getMessage() : 'Ocurrió un error inesperado',
                ], 500);
            }
        });
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recurso no encontrado',
                ], 404);
            }
        });
        
    })
    
    
    ->create();
