<?php

use App\Http\Controllers\API\AccessControl\PermissionController;
use App\Http\Controllers\API\AccessControl\RoleController;
use App\Http\Controllers\API\AccessControl\UserController;
use App\Http\Controllers\API\Asociaciones\AsociacionController;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Auth\GoogleAuthController;
use App\Http\Controllers\API\Dashboard\DashboardController;
use App\Http\Controllers\API\Emprendedores\EmprendedorController;
use App\Http\Controllers\API\Emprendedores\MisEmprendimientosController;
use App\Http\Controllers\API\Emprendedores\EmprendedorPlanesController;
use App\Http\Controllers\API\Evento\EventController;
use App\Http\Controllers\API\PageGeneral\MunicipalidadController;
use App\Http\Controllers\API\PageGeneral\SliderController;
use App\Http\Controllers\API\Reservas\ReservaController;
use App\Http\Controllers\API\Reservas\ReservaServicioController;
use App\Http\Controllers\API\Servicios\CategoriaController;
use App\Http\Controllers\API\Servicios\ServicioController;
use App\Http\Controllers\API\Planes\PlanController;
use App\Http\Controllers\API\Planes\PlanInscripcionController;
use App\Http\Controllers\API\Planes\PlanEmprendedoresController; // NUEVO
use App\Http\Controllers\MenuController;
use App\Http\Controllers\API\Reservas\CarritoReservaController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Aquí se registran las rutas de la API del sistema
|
*/

// ===== RUTAS PÚBLICAS =====

// Autenticación
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/', fn() => response()->json(['status' => 'ok']));

// Rutas para autenticación con Google
Route::prefix('auth/google')->group(function () {
    Route::get('/', [GoogleAuthController::class, 'redirectToGoogle']);
    Route::get('/callback', [GoogleAuthController::class, 'handleGoogleCallback']);
    Route::post('/verify-token', [GoogleAuthController::class, 'verifyGoogleToken']);
});

// Rutas para verificación de correo
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed'])
    ->name('verification.verify');

// Rutas para recuperación de contraseña
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// ===== RUTAS PÚBLICAS DEL SISTEMA DE TURISMO =====

// Municipalidades
Route::prefix('municipalidad')->group(function () {
    Route::get('/', [MunicipalidadController::class, 'index']);
    Route::get('/{id}', [MunicipalidadController::class, 'show']);
    Route::get('/{id}/relaciones', [MunicipalidadController::class, 'getWithRelations']);
    Route::get('/{id}/asociaciones', [MunicipalidadController::class, 'getWithAsociaciones']);
    Route::get('/{id}/asociaciones/emprendedores', [MunicipalidadController::class, 'getWithAsociacionesAndEmprendedores']);
});

// Sliders
Route::prefix('sliders')->group(function () {
    Route::get('/', [SliderController::class, 'index']);
    Route::get('/{id}', [SliderController::class, 'show']);
    Route::get('/entidad/{tipo}/{id}', [SliderController::class, 'getByEntidad']);
    Route::get('/municipalidad/{municipalidadId}', [SliderController::class, 'getByMunicipalidadId']);
    Route::get('/{id}/with-descripciones', [SliderController::class, 'getWithDescripciones']);
    Route::get('/{id}/image', [SliderController::class, 'getImage']);
});

// Asociaciones
Route::prefix('asociaciones')->group(function () {
    Route::get('/', [AsociacionController::class, 'index']);
    Route::get('/{id}', [AsociacionController::class, 'show']);
    Route::get('/{id}/emprendedores', [AsociacionController::class, 'getEmprendedores']);
    Route::get('/municipalidad/{municipalidadId}', [AsociacionController::class, 'getByMunicipalidad']);
    Route::get('/ubicacion/buscar', [AsociacionController::class, 'getByUbicacion']);
});

// Emprendedores (rutas públicas)
Route::prefix('emprendedores')->group(function () {
    Route::get('/', [EmprendedorController::class, 'index']);
    Route::get('/categoria/{categoria}', [EmprendedorController::class, 'byCategory']);
    Route::get('/asociacion/{asociacionId}', [EmprendedorController::class, 'byAsociacion']);
    Route::get('/search', [EmprendedorController::class, 'search']);
    Route::get('/{id}/servicios', [EmprendedorController::class, 'getServicios']);
    Route::get('/{id}/relaciones', [EmprendedorController::class, 'getWithRelations']);
    Route::get('/{id}', [EmprendedorController::class, 'show']);
});

// Servicios
Route::prefix('servicios')->group(function () {
    Route::get('/', [ServicioController::class, 'index']);
    Route::get('/emprendedor/{emprendedorId}', [ServicioController::class, 'byEmprendedor']);
    Route::get('/categoria/{categoriaId}', [ServicioController::class, 'byCategoria']);
    Route::get('/verificar-disponibilidad', [ServicioController::class, 'verificarDisponibilidad']);
    Route::get('/ubicacion', [ServicioController::class, 'byUbicacion']);
    Route::get('/{id}', [ServicioController::class, 'show']);
});

// Categorías
Route::prefix('categorias')->group(function () {
    Route::get('/', [CategoriaController::class, 'index']);
    Route::get('/{id}', [CategoriaController::class, 'show']);
});

// Búsqueda de usuarios (pública)
Route::get('/users/search', [UserController::class, 'search']);

// Eventos
Route::prefix('eventos')->group(function () {
    Route::get('/', [EventController::class, 'index']);
    Route::get('/proximos', [EventController::class, 'proximosEventos']);
    Route::get('/activos', [EventController::class, 'eventosActivos']);
    Route::get('/emprendedor/{emprendedorId}', [EventController::class, 'byEmprendedor']);
    Route::get('/{id}', [EventController::class, 'show']);
    Route::put('/{id}', [EventController::class, 'update']);
    Route::delete('/{id}', [EventController::class, 'destroy']);
    Route::post('/', [EventController::class, 'store']);
});

// Planes (rutas públicas)
Route::prefix('planes')->group(function () {
    Route::get('/', [PlanController::class, 'index']); // Listar planes con filtros
    Route::get('/publicos', [PlanController::class, 'publicos']); // NUEVO: Endpoint específico para planes públicos
    Route::get('/search', [PlanController::class, 'search']); // Buscar planes
    Route::get('/{id}', [PlanController::class, 'show']); // Ver plan específico

    // Ver emprendedores de un plan (público)
    Route::get('/{id}/emprendedores', [PlanEmprendedoresController::class, 'index']);
});

// ===== RUTAS PÚBLICAS ESPECÍFICAS PARA CATÁLOGO =====
Route::prefix('public')->group(function () {
    // Planes públicos para mostrar en landing page o catálogo público
    Route::get('/planes', function(Request $request) {
        $planes = \App\Models\Plan::with([
                'emprendedor:id,nombre,ubicacion', // Legacy
                'emprendedores:id,nombre,ubicacion' // Nuevo - múltiples emprendedores
            ])
            ->publicos()
            ->activos()
            ->conCuposDisponibles()
            ->select('id', 'nombre', 'descripcion', 'duracion_dias', 'precio_total', 'imagen_principal', 'emprendedor_id', 'dificultad')
            ->paginate(12);

        return response()->json([
            'success' => true,
            'data' => $planes
        ]);
    });

    Route::get('/planes/{id}', function($id) {
        $plan = \App\Models\Plan::with([
            'emprendedor:id,nombre,ubicacion,telefono,email', // Legacy
            'emprendedores:id,nombre,ubicacion,telefono,email', // Nuevo
            'organizadorPrincipal:id,nombre,ubicacion,telefono,email', // Organizador principal
            'dias.servicios:id,nombre,descripcion,precio'
        ])
        ->publicos()
        ->activos()
        ->find($id);

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $plan
        ]);
    });

    // ACTUALIZADO: Incluir planes donde el emprendedor participa (no solo organiza)
    Route::get('/emprendedores/{emprendedorId}/planes', function($emprendedorId) {
        $planes = \App\Models\Plan::with([
                'emprendedores' => function($query) use ($emprendedorId) {
                    $query->where('emprendedores.id', $emprendedorId);
                }
            ])
            ->whereHas('emprendedores', function($query) use ($emprendedorId) {
                $query->where('emprendedores.id', $emprendedorId);
            })
            ->publicos()
            ->activos()
            ->conCuposDisponibles()
            ->select('id', 'nombre', 'descripcion', 'duracion_dias', 'precio_total', 'imagen_principal', 'dificultad')
            ->get();

        // Agregar información del rol del emprendedor en cada plan
        $planes->each(function($plan) use ($emprendedorId) {
            $emprendedor = $plan->emprendedores->first();
            if ($emprendedor) {
                $plan->mi_rol = $emprendedor->pivot->rol;
                $plan->soy_organizador_principal = $emprendedor->pivot->es_organizador_principal;
            }
        });

        return response()->json([
            'success' => true,
            'data' => $planes
        ]);
    });
});

// ===== RUTAS PROTEGIDAS =====
Route::middleware('auth:sanctum')->group(function () {
    // Perfil de usuario
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail']);

    // Menú dinámico
    Route::get('/menu', [MenuController::class, 'getMenu']);

    // Mis Emprendimientos (para usuarios emprendedores)
    Route::prefix('mis-emprendimientos')->group(function () {
        Route::get('/', [MisEmprendimientosController::class, 'index']);
        Route::get('/{id}', [MisEmprendimientosController::class, 'show']);

        // NUEVOS ENDPOINTS
        Route::get('/{id}/dashboard', [MisEmprendimientosController::class, 'dashboard']);
        Route::get('/{id}/calendario', [MisEmprendimientosController::class, 'getCalendario']);

        // ENDPOINTS EXISTENTES
        Route::get('/{id}/servicios', [MisEmprendimientosController::class, 'getServicios']);
        Route::get('/{id}/reservas', [MisEmprendimientosController::class, 'getReservas']); // ACTUALIZADO

        // Gestión de administradores
        Route::post('/{id}/administradores', [MisEmprendimientosController::class, 'agregarAdministrador']);
        Route::delete('/{id}/administradores/{userId}', [MisEmprendimientosController::class, 'eliminarAdministrador']);
    });

    // Municipalidades (rutas protegidas)
    Route::prefix('municipalidad')->group(function () {
        Route::post('/', [MunicipalidadController::class, 'store'])->middleware('permission:municipalidad_update');
        Route::put('/{id}', [MunicipalidadController::class, 'update'])->middleware('permission:municipalidad_update');
        Route::delete('/{id}', [MunicipalidadController::class, 'destroy'])->middleware('permission:municipalidad_update');
    });

    // Sliders (rutas protegidas)
    Route::prefix('sliders')->group(function () {
        Route::post('/', [SliderController::class, 'store']);
        Route::post('/multiple', [SliderController::class, 'storeMultiple']);
        Route::put('/{id}', [SliderController::class, 'update']);
        Route::delete('/{id}', [SliderController::class, 'destroy']);
    });

    // Asociaciones (rutas protegidas)
    Route::prefix('asociaciones')->group(function () {
        Route::post('/', [AsociacionController::class, 'store'])->middleware('permission:asociacion_create');
        Route::put('/{id}', [AsociacionController::class, 'update'])->middleware('permission:asociacion_update');
        Route::delete('/{id}', [AsociacionController::class, 'destroy'])->middleware('permission:asociacion_delete');
        Route::get('/{id}/emprendedores/detallado', [AsociacionController::class, 'getEmprendedores'])
            ->middleware('permission:asociacion_read');
        Route::get('/municipalidad/{municipalidadId}/admin', [AsociacionController::class, 'getByMunicipalidad'])
            ->middleware('permission:asociacion_read');
        Route::post('/ubicacion/buscar-avanzada', [AsociacionController::class, 'getByUbicacion'])
            ->middleware('permission:asociacion_read');
    });

    // Emprendedores (rutas protegidas)
    Route::prefix('emprendedores')->group(function () {
        Route::post('/', [EmprendedorController::class, 'store'])->middleware('permission:emprendedor_create');
        Route::put('/{id}', [EmprendedorController::class, 'update']);
        Route::delete('/{id}', [EmprendedorController::class, 'destroy']);
        Route::get('/{id}/reservas', [EmprendedorController::class, 'getReservas']);

        // Gestión de administradores de emprendimientos
        Route::post('/{id}/administradores', [EmprendedorController::class, 'agregarAdministrador']);
        Route::delete('/{id}/administradores/{userId}', [EmprendedorController::class, 'eliminarAdministrador']);

        // ===== GESTIÓN DE PLANES POR EMPRENDEDORES (ACTUALIZADO) =====
        Route::prefix('{emprendedorId}/planes')->group(function () {
            Route::get('/', [EmprendedorPlanesController::class, 'index']);
            // Parámetros disponibles: ?tipo_rol=organizando|colaborando|todos&solo_activos=true|false

            Route::get('/{planId}', [EmprendedorPlanesController::class, 'show']);
            Route::get('/{planId}/inscripciones', [EmprendedorPlanesController::class, 'inscripciones']);
            Route::get('/{planId}/estadisticas', [EmprendedorPlanesController::class, 'estadisticas']);
            Route::patch('/{planId}/inscripciones/{inscripcionId}/confirmar',
                        [EmprendedorPlanesController::class, 'confirmarInscripcion']);
            Route::patch('/{planId}/inscripciones/{inscripcionId}/cancelar',
                        [EmprendedorPlanesController::class, 'cancelarInscripcion']);
        });

        // Resumen de todos los planes del emprendedor
        Route::get('/{emprendedorId}/planes-resumen', [EmprendedorPlanesController::class, 'resumen']);
    });

    // Servicios (rutas protegidas)
    Route::prefix('servicios')->group(function () {
        Route::post('/', [ServicioController::class, 'store']);
        Route::put('/{id}', [ServicioController::class, 'update']);
        Route::delete('/{id}', [ServicioController::class, 'destroy']);
    });

    // Categorías (rutas protegidas)
    Route::prefix('categorias')->group(function () {
        Route::post('/', [CategoriaController::class, 'store']);
        Route::put('/{id}', [CategoriaController::class, 'update']);
        Route::delete('/{id}', [CategoriaController::class, 'destroy']);
    });

    // ===== PLANES (rutas protegidas) =====
    Route::prefix('planes')->group(function () {
        Route::post('/', [PlanController::class, 'store']); // Crear plan
        Route::put('/{id}', [PlanController::class, 'update']); // Actualizar plan
        Route::patch('/{id}', [PlanController::class, 'update']); // Actualización parcial
        Route::delete('/{id}', [PlanController::class, 'destroy']); // Eliminar plan
        Route::patch('/{id}/estado', [PlanController::class, 'cambiarEstado']); // Cambiar estado
        Route::get('/{id}/estadisticas', [PlanController::class, 'estadisticas']); // Estadísticas del plan

        // ===== NUEVAS RUTAS: GESTIÓN DE EMPRENDEDORES EN PLANES =====
        Route::prefix('{plan}/emprendedores')->group(function () {
            Route::get('/', [PlanEmprendedoresController::class, 'index']); // Listar emprendedores del plan
            Route::post('/', [PlanEmprendedoresController::class, 'store']); // Agregar emprendedor al plan
            Route::put('/{emprendedor}', [PlanEmprendedoresController::class, 'update']); // Actualizar rol del emprendedor
            Route::delete('/{emprendedor}', [PlanEmprendedoresController::class, 'destroy']); // Remover emprendedor del plan
            Route::post('/cambiar-organizador', [PlanEmprendedoresController::class, 'cambiarOrganizadorPrincipal']); // Cambiar organizador principal
            Route::get('/disponibles', [PlanEmprendedoresController::class, 'emprendedoresDisponibles']); // Emprendedores disponibles para agregar
        });
    });

    // ===== INSCRIPCIONES A PLANES =====
    Route::prefix('inscripciones')->group(function () {
        // Mis inscripciones
        Route::get('/mis-inscripciones', [PlanInscripcionController::class, 'misInscripciones']);
        Route::get('/proximas', [PlanInscripcionController::class, 'proximasInscripciones']);
        Route::get('/en-progreso', [PlanInscripcionController::class, 'inscripcionesEnProgreso']);

        // Gestión de inscripciones
        Route::post('/', [PlanInscripcionController::class, 'inscribirse']); // Inscribirse a un plan
        Route::get('/{id}', [PlanInscripcionController::class, 'show']); // Ver inscripción
        Route::put('/{id}', [PlanInscripcionController::class, 'update']); // Actualizar inscripción
        Route::patch('/{id}/cancelar', [PlanInscripcionController::class, 'cancelar']); // Cancelar inscripción
        Route::patch('/{id}/completar', [PlanInscripcionController::class, 'marcarCompletada']); // Marcar como completada
        Route::delete('/{id}', [PlanInscripcionController::class, 'destroy']); // Eliminar inscripción
    });

    // ===== RESERVAS =====
    Route::prefix('reservas')->group(function () {
        // Rutas específicas (deben ir primero)
        Route::get('carrito', [CarritoReservaController::class, 'obtenerCarrito']);
        Route::post('carrito/agregar', [CarritoReservaController::class, 'agregarAlCarrito']);
        Route::delete('carrito/servicio/{id}', [CarritoReservaController::class, 'eliminarDelCarrito']);
        Route::post('carrito/confirmar', [CarritoReservaController::class, 'confirmarCarrito']);
        Route::delete('carrito/vaciar', [CarritoReservaController::class, 'vaciarCarrito']);

        // Otras rutas fijas
        Route::get('mis-reservas', [ReservaController::class, 'misReservas']);
        Route::post('mis-reservas', [ReservaController::class, 'createUserReservation']);
        Route::get('emprendedor/{emprendedorId}', [ReservaController::class, 'byEmprendedor']);
        Route::get('servicio/{servicioId}', [ReservaController::class, 'byServicio']);

        // Rutas REST convencionales
        Route::get('/', [ReservaController::class, 'index']);
        Route::post('/', [ReservaController::class, 'store']);
        Route::get('{id}', [ReservaController::class, 'show']);
        Route::put('{id}', [ReservaController::class, 'update']);
        Route::delete('{id}', [ReservaController::class, 'destroy']);
        Route::put('{id}/estado', [ReservaController::class, 'cambiarEstado']);
    });

    // Reserva Servicios
    Route::prefix('reserva-servicios')->group(function () {
        Route::get('/reserva/{reservaId}', [ReservaServicioController::class, 'byReserva']);
        Route::put('/{id}/estado', [ReservaServicioController::class, 'cambiarEstado']);
        Route::get('/calendario', [ReservaServicioController::class, 'calendario']);
        Route::get('/verificar-disponibilidad', [ReservaServicioController::class, 'verificarDisponibilidad']);
    });

    // ===== RUTAS DE ADMINISTRACIÓN (CON PERMISOS) =====

    // Roles
    Route::prefix('roles')->middleware('permission:role_read')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::get('/{id}', [RoleController::class, 'show']);
        Route::post('/', [RoleController::class, 'store'])->middleware('permission:role_create');
        Route::put('/{id}', [RoleController::class, 'update'])->middleware('permission:role_update');
        Route::delete('/{id}', [RoleController::class, 'destroy'])->middleware('permission:role_delete');
    });

    // Permisos
    Route::get('/users/{id}/permissions', [PermissionController::class, 'getUserPermissions']);
    Route::prefix('permissions')->middleware('permission:permission_read')->group(function () {
        Route::get('/', [PermissionController::class, 'index']);
        Route::post('/assign-to-user', [PermissionController::class, 'assignPermissionsToUser'])->middleware('permission:permission_assign');
        Route::post('/assign-to-role', [PermissionController::class, 'assignPermissionsToRole'])->middleware('permission:permission_assign');
    });

    // Gestión de Usuarios
    Route::prefix('users')->middleware('can:user_read')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::post('/', [UserController::class, 'store'])->middleware('can:user_create');

        Route::middleware('can:user_update')->group(function () {
            Route::put('/{id}', [UserController::class, 'update']);
            Route::patch('/{id}', [UserController::class, 'update']);
            Route::post('/{id}/activate', [UserController::class, 'activate']);
            Route::post('/{id}/deactivate', [UserController::class, 'deactivate']);
            Route::post('/{id}/roles', [UserController::class, 'assignRoles']);
            Route::post('/{id}/profile-photo', [UserController::class, 'updateProfilePhoto']);
            Route::delete('/{id}/profile-photo', [UserController::class, 'deleteProfilePhoto']);
        });

        Route::delete('/{id}', [UserController::class, 'destroy'])->middleware('can:user_delete');
    });

    // ===== ADMINISTRACIÓN DE PLANES (ACTUALIZADO) =====
    Route::prefix('admin/planes')->middleware('permission:user_read')->group(function () {
        Route::get('/todos', [PlanController::class, 'index']);
        Route::get('/estadisticas-generales', function() {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_planes' => \App\Models\Plan::count(),
                    'planes_activos' => \App\Models\Plan::activos()->count(),
                    'planes_publicos' => \App\Models\Plan::publicos()->count(),
                    'total_inscripciones' => \App\Models\PlanInscripcion::count(),
                    'inscripciones_confirmadas' => \App\Models\PlanInscripcion::confirmadas()->count(),
                    'inscripciones_pendientes' => \App\Models\PlanInscripcion::pendientes()->count(),
                    'ingresos_totales' => \App\Models\PlanInscripcion::confirmadas()->sum('precio_pagado') ?? 0,

                    // ACTUALIZADO: Estadísticas de emprendedores participantes
                    'emprendedores_con_planes' => \App\Models\PlanEmprendedor::distinct('emprendedor_id')->count('emprendedor_id'),
                    'emprendedores_organizadores' => \App\Models\PlanEmprendedor::where('rol', 'organizador')->distinct('emprendedor_id')->count('emprendedor_id'),
                    'emprendedores_colaboradores' => \App\Models\PlanEmprendedor::where('rol', 'colaborador')->distinct('emprendedor_id')->count('emprendedor_id'),
                    'planes_colaborativos' => \App\Models\Plan::has('emprendedores', '>', 1)->count(),

                    'planes_por_dificultad' => [
                        'facil' => \App\Models\Plan::where('dificultad', 'facil')->count(),
                        'moderado' => \App\Models\Plan::where('dificultad', 'moderado')->count(),
                        'dificil' => \App\Models\Plan::where('dificultad', 'dificil')->count(),
                    ],
                    'planes_por_estado' => [
                        'activos' => \App\Models\Plan::where('estado', 'activo')->count(),
                        'inactivos' => \App\Models\Plan::where('estado', 'inactivo')->count(),
                        'borradores' => \App\Models\Plan::where('estado', 'borrador')->count(),
                    ]
                ]
            ]);
        });

        // Gestión avanzada de inscripciones para administradores
        Route::get('/inscripciones/todas', function(Request $request) {
            $query = \App\Models\PlanInscripcion::with(['plan:id,nombre', 'usuario:id,name,email']);

            if ($request->has('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->has('plan_id')) {
                $query->where('plan_id', $request->plan_id);
            }

            if ($request->has('fecha_desde')) {
                $query->where('fecha_inscripcion', '>=', $request->fecha_desde);
            }

            if ($request->has('fecha_hasta')) {
                $query->where('fecha_inscripcion', '<=', $request->fecha_hasta);
            }

            $inscripciones = $query->orderBy('created_at', 'desc')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $inscripciones
            ]);
        });
    });

    // Dashboard
    Route::prefix('dashboard')->middleware('permission:user_read')->group(function () {
        Route::get('/summary', [DashboardController::class, 'summary']);
    });
});

// Status endpoint
Route::get('/status', function () {
    return response()->json([
        'status' => 'online',
        'version' => '1.0.0',
        'timestamp' => now()->toIso8601String()
    ]);
});
