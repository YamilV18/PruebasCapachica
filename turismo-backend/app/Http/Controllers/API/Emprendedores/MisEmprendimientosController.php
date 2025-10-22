<?php

namespace App\Http\Controllers\API\Emprendedores;

use App\Http\Controllers\Controller;
use App\Services\EmprendedoresService;
use App\Models\Reserva;
use App\Models\ReservaServicio;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MisEmprendimientosController extends Controller
{
    protected $emprendedorService;

    public function __construct(EmprendedoresService $emprendedorService)
    {
        $this->emprendedorService = $emprendedorService;
    }

    /**
     * Obtener todos los emprendimientos del usuario autenticado
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Cargar los emprendimientos con todas sus relaciones
            $emprendimientos = $user->emprendimientos()
                ->with([
                    'asociacion',
                    'servicios.categorias',
                    'servicios.sliders',
                    'slidersPrincipales',
                    'slidersSecundarios.descripcion',
                    'administradores'
                ])
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $emprendimientos
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener emprendimientos del usuario: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener un emprendimiento específico del usuario autenticado
     */
    public function show($id): JsonResponse
    {
        try {
            // Convertir ID a entero
            $id = (int) $id;
            
            $user = Auth::user();
            
            // Buscar el emprendimiento entre los que administra el usuario
            $emprendimiento = $user->emprendimientos()
                ->with([
                    'asociacion',
                    'servicios.categorias',
                    'servicios.sliders',
                    'servicios.horarios',
                    'slidersPrincipales',
                    'slidersSecundarios.descripcion',
                    'administradores'
                ])
                ->where('emprendedores.id', $id)
                ->first();
            
            if (!$emprendimiento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Emprendimiento no encontrado o no tienes permisos para acceder'
                ], Response::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'success' => true,
                'data' => $emprendimiento
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener emprendimiento del usuario: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Obtener los servicios de un emprendimiento específico del usuario
     */
    public function getServicios($id): JsonResponse
    {
        try {
            // Convertir ID a entero
            $id = (int) $id;
            
            $user = Auth::user();
            
            // Verificar que el emprendimiento pertenezca al usuario
            $emprendimiento = $user->emprendimientos()
                ->where('emprendedores.id', $id)
                ->first();
            
            if (!$emprendimiento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Emprendimiento no encontrado o no tienes permisos para acceder'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Cargar los servicios con sus categorías
            $servicios = $emprendimiento->servicios()->with('categorias')->get();
            
            return response()->json([
                'success' => true,
                'data' => $servicios
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener servicios del emprendimiento: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Obtener las reservas de un emprendimiento específico del usuario
     * ACTUALIZADO: Usando nueva estructura de reservas
     */
    public function getReservas($id): JsonResponse
    {
        try {
            // Convertir ID a entero
            $id = (int) $id;
            
            $user = Auth::user();
            
            // Verificar que el emprendimiento pertenezca al usuario
            $emprendimiento = $user->emprendimientos()
                ->where('emprendedores.id', $id)
                ->first();
            
            if (!$emprendimiento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Emprendimiento no encontrado o no tienes permisos para acceder'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // NUEVA LÓGICA: Obtener reservas a través de reserva_servicios
            $reservas = Reserva::whereHas('servicios', function($query) use ($id) {
                $query->where('emprendedor_id', $id);
            })
            ->where('estado', '!=', Reserva::ESTADO_EN_CARRITO) // Excluir carritos
            ->with([
                'usuario:id,name,email,phone',
                'servicios' => function($query) use ($id) {
                    $query->where('emprendedor_id', $id)
                          ->with('servicio:id,nombre,descripcion,precio_referencial');
                }
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
            
            return response()->json([
                'success' => true,
                'data' => $reservas
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener reservas del emprendimiento: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * NUEVO: Obtener dashboard con estadísticas del emprendimiento
     */
    public function dashboard($id): JsonResponse
    {
        try {
            $id = (int) $id;
            $user = Auth::user();
            
            // Verificar permisos
            $emprendimiento = $user->emprendimientos()
                ->where('emprendedores.id', $id)
                ->first();
            
            if (!$emprendimiento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Emprendimiento no encontrado o no tienes permisos para acceder'
                ], Response::HTTP_NOT_FOUND);
            }
            
            $hoy = now()->format('Y-m-d');
            $mesActual = now()->month;
            $añoActual = now()->year;
            
            // Estadísticas del emprendimiento
            $estadisticas = [
                // Reservas de hoy
                'reservas_hoy' => ReservaServicio::where('emprendedor_id', $id)
                    ->where('fecha_inicio', $hoy)
                    ->where('estado', '!=', ReservaServicio::ESTADO_EN_CARRITO)
                    ->count(),
                
                // Reservas pendientes de confirmación
                'reservas_pendientes' => ReservaServicio::where('emprendedor_id', $id)
                    ->where('estado', ReservaServicio::ESTADO_PENDIENTE)
                    ->count(),
                
                // Ingresos del mes actual
                'ingresos_mes' => ReservaServicio::where('emprendedor_id', $id)
                    ->whereMonth('created_at', $mesActual)
                    ->whereYear('created_at', $añoActual)
                    ->whereIn('estado', [ReservaServicio::ESTADO_CONFIRMADO, ReservaServicio::ESTADO_COMPLETADO])
                    ->sum('precio') ?? 0,
                
                // Servicios activos
                'servicios_activos' => $emprendimiento->servicios()
                    ->where('estado', true)
                    ->count(),
                
                // Total de reservas confirmadas
                'total_reservas_confirmadas' => ReservaServicio::where('emprendedor_id', $id)
                    ->whereIn('estado', [ReservaServicio::ESTADO_CONFIRMADO, ReservaServicio::ESTADO_COMPLETADO])
                    ->count(),
                
                // Reservas próximas (próximos 7 días)
                'reservas_proximas' => ReservaServicio::where('emprendedor_id', $id)
                    ->where('fecha_inicio', '>=', $hoy)
                    ->where('fecha_inicio', '<=', now()->addDays(7)->format('Y-m-d'))
                    ->where('estado', ReservaServicio::ESTADO_CONFIRMADO)
                    ->with([
                        'reserva.usuario:id,name,email',
                        'servicio:id,nombre,precio_referencial'
                    ])
                    ->orderBy('fecha_inicio')
                    ->orderBy('hora_inicio')
                    ->limit(10)
                    ->get(),
                
                // Servicios más reservados del mes
                'servicios_populares' => ReservaServicio::where('emprendedor_id', $id)
                    ->whereMonth('created_at', $mesActual)
                    ->whereYear('created_at', $añoActual)
                    ->whereIn('estado', [ReservaServicio::ESTADO_CONFIRMADO, ReservaServicio::ESTADO_COMPLETADO])
                    ->with('servicio:id,nombre')
                    ->selectRaw('servicio_id, COUNT(*) as total_reservas, SUM(precio) as ingresos_generados')
                    ->groupBy('servicio_id')
                    ->orderBy('total_reservas', 'desc')
                    ->limit(5)
                    ->get()
            ];
            
            return response()->json([
                'success' => true,
                'data' => $estadisticas
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener dashboard del emprendimiento: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * NUEVO: Obtener calendario de reservas del emprendimiento
     */
    public function getCalendario($id, Request $request): JsonResponse
    {
        try {
            $id = (int) $id;
            $user = Auth::user();
            
            // Verificar permisos
            $emprendimiento = $user->emprendimientos()
                ->where('emprendedores.id', $id)
                ->first();
            
            if (!$emprendimiento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Emprendimiento no encontrado o no tienes permisos para acceder'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Validar parámetros de fecha
            $fechaInicio = $request->get('fecha_inicio', now()->startOfMonth()->format('Y-m-d'));
            $fechaFin = $request->get('fecha_fin', now()->endOfMonth()->format('Y-m-d'));
            
            // Obtener reservas del emprendimiento en el rango de fechas
            $reservas = ReservaServicio::where('emprendedor_id', $id)
                ->whereBetween('fecha_inicio', [$fechaInicio, $fechaFin])
                ->where('estado', '!=', ReservaServicio::ESTADO_EN_CARRITO)
                ->with([
                    'reserva.usuario:id,name,email,phone',
                    'servicio:id,nombre,precio_referencial'
                ])
                ->orderBy('fecha_inicio')
                ->orderBy('hora_inicio')
                ->get();
            
            // Formatear para calendario
            $eventosPorDia = $reservas->groupBy('fecha_inicio')->map(function($reservasDia) {
                return $reservasDia->map(function($reserva) {
                    return [
                        'id' => $reserva->id,
                        'titulo' => $reserva->servicio->nombre,
                        'cliente' => $reserva->reserva->usuario->name,
                        'email_cliente' => $reserva->reserva->usuario->email,
                        'telefono_cliente' => $reserva->reserva->usuario->phone ?? null,
                        'hora_inicio' => $reserva->hora_inicio,
                        'hora_fin' => $reserva->hora_fin,
                        'estado' => $reserva->estado,
                        'precio' => $reserva->precio,
                        'notas_cliente' => $reserva->notas_cliente,
                        'duracion_minutos' => $reserva->duracion_minutos
                    ];
                });
            });
            
            return response()->json([
                'success' => true,
                'data' => [
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin' => $fechaFin,
                    'eventos_por_dia' => $eventosPorDia,
                    'total_reservas' => $reservas->count(),
                    'ingresos_periodo' => $reservas->whereIn('estado', [
                        ReservaServicio::ESTADO_CONFIRMADO, 
                        ReservaServicio::ESTADO_COMPLETADO
                    ])->sum('precio')
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener calendario del emprendimiento: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Agregar un administrador a un emprendimiento
     */
    public function agregarAdministrador(Request $request, $id): JsonResponse
    {
        try {
            // Convertir ID a entero
            $id = (int) $id;
            
            $user = Auth::user();
            
            // Verificar que el emprendimiento pertenezca al usuario principal
            $emprendimiento = $user->emprendimientos()
                ->wherePivot('es_principal', true)
                ->where('emprendedores.id', $id)
                ->first();
            
            if (!$emprendimiento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Emprendimiento no encontrado o no eres el administrador principal'
                ], Response::HTTP_FORBIDDEN);
            }
            
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
                'rol' => 'required|in:administrador,colaborador',
                'es_principal' => 'boolean',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Obtener el usuario a agregar como administrador
            $nuevoAdmin = \App\Models\User::where('email', $request->email)->first();
            
            // Verificar si ya es administrador de este emprendimiento
            if ($emprendimiento->administradores()->where('users.id', $nuevoAdmin->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este usuario ya es administrador de este emprendimiento'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Si se está intentando agregar como principal, verificar que no haya otro principal
            if ($request->es_principal && $emprendimiento->administradores()->wherePivot('es_principal', true)->exists()) {
                // Cambiar el actual principal a no principal
                $emprendimiento->administradores()->wherePivot('es_principal', true)->updateExistingPivot(
                    $user->id,
                    ['es_principal' => false]
                );
            }
            
            // Agregar el nuevo administrador
            $emprendimiento->administradores()->attach($nuevoAdmin->id, [
                'es_principal' => $request->es_principal ?? false,
                'rol' => $request->rol,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Administrador agregado correctamente',
                'data' => $nuevoAdmin
            ]);
        } catch (\Exception $e) {
            Log::error('Error al agregar administrador: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Eliminar un administrador de un emprendimiento
     */
    public function eliminarAdministrador(Request $request, $id, $userId): JsonResponse
    {
        try {
            // Convertir IDs a enteros
            $id = (int) $id;
            $userId = (int) $userId;
            
            $user = Auth::user();
            
            // Verificar que el emprendimiento pertenezca al usuario principal
            $emprendimiento = $user->emprendimientos()
                ->wherePivot('es_principal', true)
                ->where('emprendedores.id', $id)
                ->first();
            
            if (!$emprendimiento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Emprendimiento no encontrado o no eres el administrador principal'
                ], Response::HTTP_FORBIDDEN);
            }
            
            // No permitir eliminar al administrador principal
            $adminAEliminar = $emprendimiento->administradores()->where('users.id', $userId)->first();
            
            if (!$adminAEliminar) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado en este emprendimiento'
                ], Response::HTTP_NOT_FOUND);
            }
            
            if ($adminAEliminar->pivot->es_principal) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes eliminar al administrador principal'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Eliminar el administrador
            $emprendimiento->administradores()->detach($userId);
            
            return response()->json([
                'success' => true,
                'message' => 'Administrador eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar administrador: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}