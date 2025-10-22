<?php

namespace App\Http\Controllers\API\Planes;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanInscripcion;
use App\Http\Requests\PlanInscripcionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PlanInscripcionController extends Controller
{
    /**
     * Obtener inscripciones del usuario autenticado
     */
    public function misInscripciones(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $estado = $request->get('estado');
            $perPage = $request->get('per_page', 15);
            
            $query = $user->inscripciones()->with([
                'plan:id,nombre,descripcion,duracion_dias,precio_total,imagen_principal',
                'plan.emprendedor:id,nombre,ubicacion,telefono'
            ]);
            
            if ($estado) {
                $query->where('estado', $estado);
            }
            
            $inscripciones = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $inscripciones
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener inscripciones del usuario: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Inscribirse a un plan
     */
    public function inscribirse(PlanInscripcionRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $data = $request->validated();
            $planId = $data['plan_id'];
            $user = Auth::user();
            
            // Verificar que el plan existe y está activo
            $plan = Plan::where('id', $planId)
                       ->where('estado', Plan::ESTADO_ACTIVO)
                       ->where('es_publico', true)
                       ->first();
            
            if (!$plan) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Plan no encontrado o no disponible'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Verificar si el usuario ya está inscrito
            $inscripcionExistente = PlanInscripcion::where('plan_id', $planId)
                                                  ->where('user_id', $user->id)
                                                  ->whereIn('estado', ['pendiente', 'confirmada'])
                                                  ->first();
            
            if ($inscripcionExistente) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Ya tienes una inscripción activa para este plan'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Verificar disponibilidad de cupos
            $participantesSolicitados = $data['numero_participantes'] ?? 1;
            $inscripcionesConfirmadas = $plan->inscripciones()
                                            ->where('estado', 'confirmada')
                                            ->sum('numero_participantes');
            
            if (($inscripcionesConfirmadas + $participantesSolicitados) > $plan->capacidad) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No hay suficientes cupos disponibles. Cupos restantes: ' . 
                                ($plan->capacidad - $inscripcionesConfirmadas)
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Crear la inscripción
            $inscripcion = new PlanInscripcion();
            $inscripcion->plan_id = $planId;
            $inscripcion->user_id = $user->id;
            $inscripcion->estado = PlanInscripcion::ESTADO_PENDIENTE;
            $inscripcion->fecha_inscripcion = now();
            $inscripcion->numero_participantes = $participantesSolicitados;
            
            // Calcular precio
            $precioTotal = ($plan->precio_total ?? 0) * $participantesSolicitados;
            $inscripcion->precio_pagado = $precioTotal;
            
            // Campos opcionales
            if (isset($data['notas_usuario'])) {
                $inscripcion->notas_usuario = $data['notas_usuario'];
            }
            
            if (isset($data['requerimientos_especiales'])) {
                $inscripcion->requerimientos_especiales = $data['requerimientos_especiales'];
            }
            
            if (isset($data['metodo_pago'])) {
                $inscripcion->metodo_pago = $data['metodo_pago'];
            }
            
            if (isset($data['comentarios_adicionales'])) {
                $inscripcion->comentarios_adicionales = $data['comentarios_adicionales'];
            }
            
            $inscripcion->save();
            
            DB::commit();
            
            // Cargar relaciones para la respuesta
            $inscripcion->load(['plan:id,nombre,precio_total,duracion_dias', 'user:id,name,email']);
            
            return response()->json([
                'success' => true,
                'message' => 'Inscripción realizada correctamente. Pendiente de confirmación.',
                'data' => $inscripcion
            ], Response::HTTP_CREATED);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear inscripción: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la inscripción: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener una inscripción específica
     */
    public function show($id): JsonResponse
    {
        try {
            $id = (int) $id;
            $user = Auth::user();
            
            $inscripcion = PlanInscripcion::with([
                'plan.emprendedor',
                'plan.dias.servicios',
                'usuario'
            ])->find($id);
            
            if (!$inscripcion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inscripción no encontrada'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Verificar permisos - solo el usuario inscrito o administradores del emprendimiento
            if ($inscripcion->user_id !== $user->id && 
                !$user->hasRole('admin') &&
                (!$inscripcion->plan->emprendedor || 
                 !$inscripcion->plan->emprendedor->administradores()->where('users.id', $user->id)->exists())) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para acceder a esta inscripción'
                ], Response::HTTP_FORBIDDEN);
            }
            
            return response()->json([
                'success' => true,
                'data' => $inscripcion
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener inscripción: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Actualizar una inscripción (solo por el usuario inscrito)
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $id = (int) $id;
            $user = Auth::user();
            
            $inscripcion = PlanInscripcion::find($id);
            
            if (!$inscripcion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inscripción no encontrada'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Solo el usuario inscrito puede actualizar sus datos
            if ($inscripcion->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para modificar esta inscripción'
                ], Response::HTTP_FORBIDDEN);
            }
            
            // Solo se puede actualizar si está pendiente
            if ($inscripcion->estado !== PlanInscripcion::ESTADO_PENDIENTE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden modificar inscripciones pendientes'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Validar datos de entrada
            $request->validate([
                'notas_usuario' => 'nullable|string|max:1000',
                'requerimientos_especiales' => 'nullable|string|max:1000',
                'numero_participantes' => 'nullable|integer|min:1|max:10',
                'comentarios_adicionales' => 'nullable|string|max:1000',
            ]);
            
            // Verificar cupos si se cambia el número de participantes
            if ($request->has('numero_participantes') && 
                $request->numero_participantes != $inscripcion->numero_participantes) {
                
                $plan = $inscripcion->plan;
                $inscripcionesConfirmadas = $plan->inscripciones()
                                                ->where('estado', 'confirmada')
                                                ->where('id', '!=', $inscripcion->id)
                                                ->sum('numero_participantes');
                
                if (($inscripcionesConfirmadas + $request->numero_participantes) > $plan->capacidad) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No hay suficientes cupos disponibles'
                    ], Response::HTTP_BAD_REQUEST);
                }
                
                // Recalcular precio
                $inscripcion->numero_participantes = $request->numero_participantes;
                $inscripcion->precio_pagado = ($plan->precio_total ?? 0) * $request->numero_participantes;
            }
            
            // Actualizar campos permitidos
            if ($request->has('notas_usuario')) {
                $inscripcion->notas_usuario = $request->notas_usuario;
            }
            
            if ($request->has('requerimientos_especiales')) {
                $inscripcion->requerimientos_especiales = $request->requerimientos_especiales;
            }
            
            if ($request->has('comentarios_adicionales')) {
                $inscripcion->comentarios_adicionales = $request->comentarios_adicionales;
            }
            
            $inscripcion->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Inscripción actualizada correctamente',
                'data' => $inscripcion->fresh(['plan', 'usuario'])
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar inscripción: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Cancelar una inscripción (por el usuario)
     */
    public function cancelar($id, Request $request): JsonResponse
    {
        try {
            $id = (int) $id;
            $user = Auth::user();
            
            $inscripcion = PlanInscripcion::find($id);
            
            if (!$inscripcion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inscripción no encontrada'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Solo el usuario inscrito puede cancelar
            if ($inscripcion->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para cancelar esta inscripción'
                ], Response::HTTP_FORBIDDEN);
            }
            
            if (!$inscripcion->puedeSerCancelada()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta inscripción no puede ser cancelada'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $inscripcion->estado = PlanInscripcion::ESTADO_CANCELADA;
            
            if ($request->has('motivo_cancelacion')) {
                $inscripcion->comentarios_adicionales = ($inscripcion->comentarios_adicionales ? 
                    $inscripcion->comentarios_adicionales . '\n\n' : '') . 
                    'Cancelado por usuario: ' . $request->motivo_cancelacion;
            }
            
            $inscripcion->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Inscripción cancelada correctamente',
                'data' => $inscripcion->fresh(['plan', 'usuario'])
            ]);
        } catch (\Exception $e) {
            Log::error('Error al cancelar inscripción: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Eliminar una inscripción
     */
    public function destroy($id): JsonResponse
    {
        try {
            $id = (int) $id;
            $user = Auth::user();
            
            $inscripcion = PlanInscripcion::find($id);
            
            if (!$inscripcion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inscripción no encontrada'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Solo admins o el usuario inscrito pueden eliminar
            if (!$user->hasRole('admin') && $inscripcion->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para eliminar esta inscripción'
                ], Response::HTTP_FORBIDDEN);
            }
            
            // Solo se puede eliminar si está cancelada o pendiente
            if (!in_array($inscripcion->estado, [PlanInscripcion::ESTADO_CANCELADA, PlanInscripcion::ESTADO_PENDIENTE])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden eliminar inscripciones canceladas o pendientes'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $inscripcion->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Inscripción eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar inscripción: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener próximas inscripciones del usuario
     */
    public function proximasInscripciones(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $dias = (int) $request->get('dias', 30);
            
            $inscripciones = PlanInscripcion::with([
                'plan:id,nombre,duracion_dias,imagen_principal',
                'plan.emprendedor:id,nombre,telefono'
            ])
            ->where('user_id', $user->id)
            ->where('fecha_inicio_plan', '>=', now())
            ->where('fecha_inicio_plan', '<=', now()->addDays($dias))
            ->activas()
            ->orderBy('fecha_inicio_plan')
            ->get();
            
            return response()->json([
                'success' => true,
                'data' => $inscripciones
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener próximas inscripciones: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener inscripciones en progreso del usuario
     */
    public function inscripcionesEnProgreso(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $inscripciones = PlanInscripcion::with([
                'plan:id,nombre,duracion_dias,imagen_principal',
                'plan.emprendedor:id,nombre,telefono',
                'plan.dias.servicios'
            ])
            ->where('user_id', $user->id)
            ->enProgreso()
            ->get();
            
            return response()->json([
                'success' => true,
                'data' => $inscripciones
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener inscripciones en progreso: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Marcar inscripción como completada
     */
    public function marcarCompletada($id, Request $request): JsonResponse
    {
        try {
            $id = (int) $id;
            $user = Auth::user();
            
            $inscripcion = PlanInscripcion::find($id);
            
            if (!$inscripcion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inscripción no encontrada'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Solo el usuario inscrito o administradores del emprendimiento pueden marcar como completada
            if ($inscripcion->user_id !== $user->id && 
                !$user->hasRole('admin') &&
                (!$inscripcion->plan->emprendedor || 
                 !$inscripcion->plan->emprendedor->administradores()->where('users.id', $user->id)->exists())) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para modificar esta inscripción'
                ], Response::HTTP_FORBIDDEN);
            }
            
            if ($inscripcion->estado !== PlanInscripcion::ESTADO_CONFIRMADA) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden completar inscripciones confirmadas'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $inscripcion->estado = PlanInscripcion::ESTADO_COMPLETADA;
            
            if ($request->has('comentarios_finales')) {
                $inscripcion->comentarios_adicionales = ($inscripcion->comentarios_adicionales ? 
                    $inscripcion->comentarios_adicionales . '\n\n' : '') . 
                    'Comentarios finales: ' . $request->comentarios_finales;
            }
            
            $inscripcion->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Inscripción marcada como completada',
                'data' => $inscripcion->fresh(['plan', 'usuario'])
            ]);
        } catch (\Exception $e) {
            Log::error('Error al marcar inscripción como completada: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}