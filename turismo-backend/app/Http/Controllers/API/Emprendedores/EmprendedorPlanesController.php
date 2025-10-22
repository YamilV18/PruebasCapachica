<?php

namespace App\Http\Controllers\API\Emprendedores;

use App\Http\Controllers\Controller;
use App\Services\PlanService;
use App\Services\EmprendedoresService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class EmprendedorPlanesController extends Controller
{
    protected $planService;
    protected $emprendedorService;

    public function __construct(PlanService $planService, EmprendedoresService $emprendedorService)
    {
        $this->planService = $planService;
        $this->emprendedorService = $emprendedorService;
    }

    /**
     * Obtener todos los planes donde participa un emprendedor específico
     */
    public function index($emprendedorId, Request $request): JsonResponse
    {
        try {
            $emprendedorId = (int) $emprendedorId;
            $user = Auth::user();
            
            // Verificar que el usuario tenga acceso al emprendedor
            if (!$user->hasRole('admin') && 
                !$this->emprendedorService->esAdministrador($emprendedorId, $user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para acceder a los planes de este emprendimiento'
                ], Response::HTTP_FORBIDDEN);
            }
            
            $soloActivos = $request->get('solo_activos', false);
            $tipoRol = $request->get('tipo_rol'); // 'organizando', 'colaborando', 'todos'
            
            $emprendedor = \App\Models\Emprendedor::find($emprendedorId);
            if (!$emprendedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Emprendimiento no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Obtener planes según el tipo de rol solicitado
            switch ($tipoRol) {
                case 'organizando':
                    $query = $emprendedor->planesComoOrganizador();
                    break;
                case 'colaborando':
                    $query = $emprendedor->planesColaborando();
                    break;
                default:
                    $query = $emprendedor->planesParticipando();
                    break;
            }
            
            if ($soloActivos) {
                $query->where('plans.estado', 'activo');
            }
            
            $planes = $query->with([
                'dias.servicios',
                'inscripciones',
                'emprendedores' => function($q) {
                    $q->select('emprendedores.id', 'emprendedores.nombre', 'emprendedores.ubicacion');
                }
            ])->get();
            
            // Agregar información específica del rol del emprendedor en cada plan
            $planes->each(function($plan) use ($emprendedorId) {
                $rolInfo = $plan->emprendedores->where('id', $emprendedorId)->first();
                if ($rolInfo) {
                    $plan->mi_rol = [
                        'rol' => $rolInfo->pivot->rol,
                        'es_organizador_principal' => $rolInfo->pivot->es_organizador_principal,
                        'descripcion_participacion' => $rolInfo->pivot->descripcion_participacion,
                        'porcentaje_ganancia' => $rolInfo->pivot->porcentaje_ganancia,
                    ];
                }
            });
            
            return response()->json([
                'success' => true,
                'data' => $planes,
                'meta' => [
                    'total' => $planes->count(),
                    'emprendedor' => [
                        'id' => $emprendedor->id,
                        'nombre' => $emprendedor->nombre,
                    ],
                    'estadisticas' => $this->planService->getEstadisticasPorEmprendedor($emprendedorId)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener planes del emprendedor: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener un plan específico donde participa un emprendedor
     */
    public function show($emprendedorId, $planId): JsonResponse
    {
        try {
            $emprendedorId = (int) $emprendedorId;
            $planId = (int) $planId;
            $user = Auth::user();
            
            // Verificar que el usuario tenga acceso al emprendedor
            if (!$user->hasRole('admin') && 
                !$this->emprendedorService->esAdministrador($emprendedorId, $user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para acceder a este emprendimiento'
                ], Response::HTTP_FORBIDDEN);
            }
            
            $plan = $this->planService->getById($planId);
            
            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Verificar que el emprendedor participe en este plan
            if (!$plan->tieneEmprendedor($emprendedorId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este emprendimiento no participa en el plan solicitado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Agregar información del rol del emprendedor en este plan
            $emprendedorEnPlan = $plan->emprendedores->where('id', $emprendedorId)->first();
            if ($emprendedorEnPlan) {
                $plan->mi_rol = [
                    'rol' => $emprendedorEnPlan->pivot->rol,
                    'es_organizador_principal' => $emprendedorEnPlan->pivot->es_organizador_principal,
                    'descripcion_participacion' => $emprendedorEnPlan->pivot->descripcion_participacion,
                    'porcentaje_ganancia' => $emprendedorEnPlan->pivot->porcentaje_ganancia,
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $plan
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener plan del emprendedor: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener las inscripciones de un plan donde participa el emprendedor
     */
    public function inscripciones($emprendedorId, $planId, Request $request): JsonResponse
    {
        try {
            $emprendedorId = (int) $emprendedorId;
            $planId = (int) $planId;
            $user = Auth::user();
            
            // Verificar que el usuario tenga acceso al emprendedor
            if (!$user->hasRole('admin') && 
                !$this->emprendedorService->esAdministrador($emprendedorId, $user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para acceder a este emprendimiento'
                ], Response::HTTP_FORBIDDEN);
            }
            
            $plan = $this->planService->getById($planId);
            
            if (!$plan || !$plan->tieneEmprendedor($emprendedorId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan no encontrado o el emprendimiento no participa en él'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Solo organizadores pueden ver todas las inscripciones
            $emprendedor = \App\Models\Emprendedor::find($emprendedorId);
            if (!$emprendedor->esOrganizadorDePlan($planId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo los organizadores del plan pueden ver las inscripciones'
                ], Response::HTTP_FORBIDDEN);
            }
            
            // Filtros opcionales
            $estado = $request->get('estado');
            $fechaDesde = $request->get('fecha_desde');
            $fechaHasta = $request->get('fecha_hasta');
            
            $query = $plan->inscripciones()->with('usuario:id,name,email,phone');
            
            if ($estado) {
                $query->where('estado', $estado);
            }
            
            if ($fechaDesde) {
                $query->where('fecha_inscripcion', '>=', $fechaDesde);
            }
            
            if ($fechaHasta) {
                $query->where('fecha_inscripcion', '<=', $fechaHasta);
            }
            
            $inscripciones = $query->orderBy('fecha_inscripcion', 'desc')->get();
            
            return response()->json([
                'success' => true,
                'data' => $inscripciones,
                'meta' => [
                    'plan' => [
                        'id' => $plan->id,
                        'nombre' => $plan->nombre,
                    ],
                    'mi_rol' => $emprendedor->esOrganizadorPrincipalDePlan($planId) ? 'organizador_principal' : 'organizador'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener inscripciones del plan: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener estadísticas de un plan donde participa el emprendedor
     */
    public function estadisticas($emprendedorId, $planId): JsonResponse
    {
        try {
            $emprendedorId = (int) $emprendedorId;
            $planId = (int) $planId;
            $user = Auth::user();
            
            // Verificar que el usuario tenga acceso al emprendedor
            if (!$user->hasRole('admin') && 
                !$this->emprendedorService->esAdministrador($emprendedorId, $user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para acceder a este emprendimiento'
                ], Response::HTTP_FORBIDDEN);
            }
            
            $plan = $this->planService->getById($planId);
            
            if (!$plan || !$plan->tieneEmprendedor($emprendedorId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan no encontrado o el emprendimiento no participa en él'
                ], Response::HTTP_NOT_FOUND);
            }
            
            $estadisticas = $this->planService->getEstadisticas($planId);
            
            // Agregar información específica del emprendedor
            $emprendedor = \App\Models\Emprendedor::find($emprendedorId);
            $estadisticas['mi_participacion'] = [
                'rol' => $emprendedor->esOrganizadorPrincipalDePlan($planId) ? 'organizador_principal' : 
                        ($emprendedor->esOrganizadorDePlan($planId) ? 'organizador' : 'colaborador'),
                'puede_gestionar_inscripciones' => $emprendedor->esOrganizadorDePlan($planId),
                'puede_editar_plan' => $emprendedor->esOrganizadorPrincipalDePlan($planId),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $estadisticas
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas del plan: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener resumen de todos los planes del emprendedor
     */
    public function resumen($emprendedorId): JsonResponse
    {
        try {
            $emprendedorId = (int) $emprendedorId;
            $user = Auth::user();
            
            // Verificar que el usuario tenga acceso al emprendedor
            if (!$user->hasRole('admin') && 
                !$this->emprendedorService->esAdministrador($emprendedorId, $user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para acceder a este emprendimiento'
                ], Response::HTTP_FORBIDDEN);
            }
            
            $emprendedor = $this->emprendedorService->getById($emprendedorId);
            
            if (!$emprendedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Emprendimiento no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            $estadisticas = $emprendedor->estadisticas_planes;
            $estadisticasDetalladas = $this->planService->getEstadisticasPorEmprendedor($emprendedorId);
            
            // Combinar estadísticas
            $resumen = array_merge($estadisticas, $estadisticasDetalladas);
            
            // Agregar información adicional por rol
            $planesOrganizando = $emprendedor->planesComoOrganizador;
            $planesColaborando = $emprendedor->planesColaborando;
            
            $resumen['planes_por_rol'] = [
                'organizando' => [
                    'total' => $planesOrganizando->count(),
                    'activos' => $planesOrganizando->where('estado', 'activo')->count(),
                    'principales' => $emprendedor->planesOrganizando->count(),
                ],
                'colaborando' => [
                    'total' => $planesColaborando->count(),
                    'activos' => $planesColaborando->where('estado', 'activo')->count(),
                ]
            ];
            
            $resumen['planes_por_estado'] = [
                'activos' => $emprendedor->planesParticipando->where('estado', 'activo')->count(),
                'inactivos' => $emprendedor->planesParticipando->where('estado', 'inactivo')->count(),
                'borradores' => $emprendedor->planesParticipando->where('estado', 'borrador')->count(),
            ];
            
            $resumen['planes_por_dificultad'] = [
                'facil' => $emprendedor->planesParticipando->where('dificultad', 'facil')->count(),
                'moderado' => $emprendedor->planesParticipando->where('dificultad', 'moderado')->count(),
                'dificil' => $emprendedor->planesParticipando->where('dificultad', 'dificil')->count(),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $resumen
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener resumen de planes del emprendedor: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Confirmar una inscripción a un plan (solo organizadores)
     */
    public function confirmarInscripcion($emprendedorId, $planId, $inscripcionId, Request $request): JsonResponse
    {
        try {
            $emprendedorId = (int) $emprendedorId;
            $planId = (int) $planId;
            $inscripcionId = (int) $inscripcionId;
            $user = Auth::user();
            
            // Verificar que el usuario tenga acceso al emprendedor
            if (!$user->hasRole('admin') && 
                !$this->emprendedorService->esAdministrador($emprendedorId, $user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para gestionar inscripciones de este emprendimiento'
                ], Response::HTTP_FORBIDDEN);
            }
            
            $plan = $this->planService->getById($planId);
            
            if (!$plan || !$plan->tieneEmprendedor($emprendedorId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan no encontrado o el emprendimiento no participa en él'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Verificar que sea organizador del plan
            $emprendedor = \App\Models\Emprendedor::find($emprendedorId);
            if (!$emprendedor->esOrganizadorDePlan($planId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo los organizadores pueden confirmar inscripciones'
                ], Response::HTTP_FORBIDDEN);
            }
            
            $inscripcion = $plan->inscripciones()->find($inscripcionId);
            
            if (!$inscripcion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inscripción no encontrada'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Actualizar estado y datos adicionales
            $inscripcion->estado = 'confirmada';
            $inscripcion->fecha_inscripcion = now();
            
            if ($request->has('fecha_inicio_plan')) {
                $inscripcion->fecha_inicio_plan = $request->fecha_inicio_plan;
            }
            
            if ($request->has('fecha_fin_plan')) {
                $inscripcion->fecha_fin_plan = $request->fecha_fin_plan;
            }
            
            if ($request->has('notas')) {
                $inscripcion->notas = $request->notas;
            }
            
            $inscripcion->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Inscripción confirmada correctamente',
                'data' => $inscripcion->fresh(['usuario', 'plan'])
            ]);
        } catch (\Exception $e) {
            Log::error('Error al confirmar inscripción: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Cancelar una inscripción a un plan (solo organizadores)
     */
    public function cancelarInscripcion($emprendedorId, $planId, $inscripcionId, Request $request): JsonResponse
    {
        try {
            $emprendedorId = (int) $emprendedorId;
            $planId = (int) $planId;
            $inscripcionId = (int) $inscripcionId;
            $user = Auth::user();
            
            // Verificar que el usuario tenga acceso al emprendedor
            if (!$user->hasRole('admin') && 
                !$this->emprendedorService->esAdministrador($emprendedorId, $user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para gestionar inscripciones de este emprendimiento'
                ], Response::HTTP_FORBIDDEN);
            }
            
            $plan = $this->planService->getById($planId);
            
            if (!$plan || !$plan->tieneEmprendedor($emprendedorId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan no encontrado o el emprendimiento no participa en él'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Verificar que sea organizador del plan
            $emprendedor = \App\Models\Emprendedor::find($emprendedorId);
            if (!$emprendedor->esOrganizadorDePlan($planId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo los organizadores pueden cancelar inscripciones'
                ], Response::HTTP_FORBIDDEN);
            }
            
            $inscripcion = $plan->inscripciones()->find($inscripcionId);
            
            if (!$inscripcion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inscripción no encontrada'
                ], Response::HTTP_NOT_FOUND);
            }
            
            if (!$inscripcion->puedeSerCancelada()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta inscripción no puede ser cancelada'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Actualizar estado
            $inscripcion->estado = 'cancelada';
            
            if ($request->has('motivo_cancelacion')) {
                $inscripcion->notas = ($inscripcion->notas ? $inscripcion->notas . '\n\n' : '') . 
                                    'Motivo de cancelación: ' . $request->motivo_cancelacion;
            }
            
            $inscripcion->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Inscripción cancelada correctamente',
                'data' => $inscripcion->fresh(['usuario', 'plan'])
            ]);
        } catch (\Exception $e) {
            Log::error('Error al cancelar inscripción: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}