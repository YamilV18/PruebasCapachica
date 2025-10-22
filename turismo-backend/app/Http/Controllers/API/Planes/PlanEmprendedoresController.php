<?php

namespace App\Http\Controllers\API\Planes;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanEmprendedor;
use App\Models\Emprendedor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PlanEmprendedoresController extends Controller
{
    /**
     * Obtener todos los emprendedores de un plan
     */
    public function index($planId): JsonResponse
    {
        try {
            $planId = (int) $planId;
            
            $plan = Plan::with([
                'emprendedores' => function($query) {
                    $query->select('emprendedores.id', 'nombre', 'ubicacion', 'telefono', 'email');
                }
            ])->find($planId);
            
            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Verificar permisos - debe ser público o tener acceso
            $user = Auth::user();
            if (!$plan->es_publico && !$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan no disponible'
                ], Response::HTTP_FORBIDDEN);
            }
            
            $emprendedores = $plan->emprendedores->map(function($emprendedor) {
                return [
                    'id' => $emprendedor->id,
                    'nombre' => $emprendedor->nombre,
                    'ubicacion' => $emprendedor->ubicacion,
                    'telefono' => $emprendedor->telefono,
                    'email' => $emprendedor->email,
                    'rol' => $emprendedor->pivot->rol,
                    'es_organizador_principal' => $emprendedor->pivot->es_organizador_principal,
                    'descripcion_participacion' => $emprendedor->pivot->descripcion_participacion,
                    'porcentaje_ganancia' => $emprendedor->pivot->porcentaje_ganancia,
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $emprendedores,
                'meta' => [
                    'plan' => [
                        'id' => $plan->id,
                        'nombre' => $plan->nombre,
                    ],
                    'total_emprendedores' => $emprendedores->count(),
                    'organizadores' => $emprendedores->where('rol', 'organizador')->count(),
                    'colaboradores' => $emprendedores->where('rol', 'colaborador')->count(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener emprendedores del plan: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Agregar un emprendedor al plan
     */
    public function store($planId, Request $request): JsonResponse
    {
        try {
            $planId = (int) $planId;
            $user = Auth::user();
            
            $plan = Plan::find($planId);
            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Verificar permisos - solo organizador principal o admin
            if (!$user->hasRole('admin') && 
                $plan->creado_por_usuario_id !== $user->id &&
                !$plan->esOrganizadorPrincipal($user->emprendimientos()->pluck('id')->toArray())) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para agregar emprendedores a este plan'
                ], Response::HTTP_FORBIDDEN);
            }
            
            $validator = Validator::make($request->all(), [
                'emprendedor_id' => 'required|integer|exists:emprendedores,id',
                'rol' => 'required|in:organizador,colaborador',
                'es_organizador_principal' => 'boolean',
                'descripcion_participacion' => 'nullable|string|max:500',
                'porcentaje_ganancia' => 'nullable|numeric|min:0|max:100',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            
            $emprendedorId = $request->emprendedor_id;
            
            // Verificar que el emprendedor no esté ya en el plan
            if ($plan->tieneEmprendedor($emprendedorId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este emprendedor ya participa en el plan'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            DB::beginTransaction();
            
            // Si se está agregando como organizador principal, quitar el flag de otros
            if ($request->get('es_organizador_principal', false)) {
                PlanEmprendedor::where('plan_id', $planId)
                               ->update(['es_organizador_principal' => false]);
            }
            
            // Agregar el emprendedor al plan
            $planEmprendedor = PlanEmprendedor::create([
                'plan_id' => $planId,
                'emprendedor_id' => $emprendedorId,
                'rol' => $request->rol,
                'es_organizador_principal' => $request->get('es_organizador_principal', false),
                'descripcion_participacion' => $request->descripcion_participacion,
                'porcentaje_ganancia' => $request->porcentaje_ganancia,
            ]);
            
            DB::commit();
            
            // Cargar el emprendedor con sus datos
            $emprendedor = Emprendedor::find($emprendedorId);
            
            return response()->json([
                'success' => true,
                'message' => 'Emprendedor agregado al plan correctamente',
                'data' => [
                    'id' => $emprendedor->id,
                    'nombre' => $emprendedor->nombre,
                    'ubicacion' => $emprendedor->ubicacion,
                    'rol' => $planEmprendedor->rol,
                    'es_organizador_principal' => $planEmprendedor->es_organizador_principal,
                    'descripcion_participacion' => $planEmprendedor->descripcion_participacion,
                    'porcentaje_ganancia' => $planEmprendedor->porcentaje_ganancia,
                ]
            ], Response::HTTP_CREATED);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al agregar emprendedor al plan: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Actualizar la participación de un emprendedor en el plan
     */
    public function update($planId, $emprendedorId, Request $request): JsonResponse
    {
        try {
            $planId = (int) $planId;
            $emprendedorId = (int) $emprendedorId;
            $user = Auth::user();
            
            $plan = Plan::find($planId);
            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Verificar permisos
            if (!$user->hasRole('admin') && 
                $plan->creado_por_usuario_id !== $user->id &&
                !$plan->esOrganizadorPrincipal($user->emprendimientos()->pluck('id')->toArray())) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para modificar emprendedores en este plan'
                ], Response::HTTP_FORBIDDEN);
            }
            
            $planEmprendedor = PlanEmprendedor::where('plan_id', $planId)
                                             ->where('emprendedor_id', $emprendedorId)
                                             ->first();
            
            if (!$planEmprendedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'El emprendedor no participa en este plan'
                ], Response::HTTP_NOT_FOUND);
            }
            
            $validator = Validator::make($request->all(), [
                'rol' => 'sometimes|in:organizador,colaborador',
                'es_organizador_principal' => 'sometimes|boolean',
                'descripcion_participacion' => 'nullable|string|max:500',
                'porcentaje_ganancia' => 'nullable|numeric|min:0|max:100',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            
            DB::beginTransaction();
            
            // Si se está cambiando a organizador principal, quitar el flag de otros
            if ($request->has('es_organizador_principal') && $request->es_organizador_principal) {
                PlanEmprendedor::where('plan_id', $planId)
                               ->where('emprendedor_id', '!=', $emprendedorId)
                               ->update(['es_organizador_principal' => false]);
            }
            
            // Actualizar los datos
            $planEmprendedor->fill($request->only([
                'rol',
                'es_organizador_principal',
                'descripcion_participacion',
                'porcentaje_ganancia'
            ]));
            
            $planEmprendedor->save();
            
            DB::commit();
            
            // Cargar el emprendedor actualizado
            $emprendedor = Emprendedor::find($emprendedorId);
            
            return response()->json([
                'success' => true,
                'message' => 'Participación del emprendedor actualizada correctamente',
                'data' => [
                    'id' => $emprendedor->id,
                    'nombre' => $emprendedor->nombre,
                    'ubicacion' => $emprendedor->ubicacion,
                    'rol' => $planEmprendedor->rol,
                    'es_organizador_principal' => $planEmprendedor->es_organizador_principal,
                    'descripcion_participacion' => $planEmprendedor->descripcion_participacion,
                    'porcentaje_ganancia' => $planEmprendedor->porcentaje_ganancia,
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar emprendedor en el plan: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remover un emprendedor del plan
     */
    public function destroy($planId, $emprendedorId): JsonResponse
    {
        try {
            $planId = (int) $planId;
            $emprendedorId = (int) $emprendedorId;
            $user = Auth::user();
            
            $plan = Plan::find($planId);
            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Verificar permisos
            if (!$user->hasRole('admin') && 
                $plan->creado_por_usuario_id !== $user->id &&
                !$plan->esOrganizadorPrincipal($user->emprendimientos()->pluck('id')->toArray())) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para remover emprendedores de este plan'
                ], Response::HTTP_FORBIDDEN);
            }
            
            $planEmprendedor = PlanEmprendedor::where('plan_id', $planId)
                                             ->where('emprendedor_id', $emprendedorId)
                                             ->first();
            
            if (!$planEmprendedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'El emprendedor no participa en este plan'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // No permitir eliminar al organizador principal si es el único organizador
            if ($planEmprendedor->es_organizador_principal) {
                $otrosOrganizadores = PlanEmprendedor::where('plan_id', $planId)
                                                    ->where('emprendedor_id', '!=', $emprendedorId)
                                                    ->where('rol', 'organizador')
                                                    ->exists();
                
                if (!$otrosOrganizadores) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se puede eliminar al organizador principal si no hay otros organizadores'
                    ], Response::HTTP_BAD_REQUEST);
                }
            }
            
            $planEmprendedor->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Emprendedor removido del plan correctamente'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al remover emprendedor del plan: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Cambiar el organizador principal del plan
     */
    public function cambiarOrganizadorPrincipal($planId, Request $request): JsonResponse
    {
        try {
            $planId = (int) $planId;
            $user = Auth::user();
            
            $plan = Plan::find($planId);
            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Solo el organizador principal actual o admin pueden cambiar esto
            if (!$user->hasRole('admin') && 
                !$plan->esOrganizadorPrincipal($user->emprendimientos()->pluck('id')->toArray())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo el organizador principal puede cambiar este rol'
                ], Response::HTTP_FORBIDDEN);
            }
            
            $validator = Validator::make($request->all(), [
                'nuevo_organizador_id' => 'required|integer|exists:emprendedores,id',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            
            $nuevoOrganizadorId = $request->nuevo_organizador_id;
            
            // Verificar que el nuevo organizador esté en el plan
            if (!$plan->tieneEmprendedor($nuevoOrganizadorId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El emprendedor seleccionado no participa en este plan'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            DB::beginTransaction();
            
            // Quitar el flag de organizador principal a todos
            PlanEmprendedor::where('plan_id', $planId)
                           ->update(['es_organizador_principal' => false]);
            
            // Asignar el nuevo organizador principal
            PlanEmprendedor::where('plan_id', $planId)
                           ->where('emprendedor_id', $nuevoOrganizadorId)
                           ->update([
                               'es_organizador_principal' => true,
                               'rol' => 'organizador'
                           ]);
            
            DB::commit();
            
            $nuevoOrganizador = Emprendedor::find($nuevoOrganizadorId);
            
            return response()->json([
                'success' => true,
                'message' => 'Organizador principal cambiado correctamente',
                'data' => [
                    'nuevo_organizador_principal' => [
                        'id' => $nuevoOrganizador->id,
                        'nombre' => $nuevoOrganizador->nombre,
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al cambiar organizador principal: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener emprendedores disponibles para agregar al plan
     */
    public function emprendedoresDisponibles($planId): JsonResponse
    {
        try {
            $planId = (int) $planId;
            $user = Auth::user();
            
            $plan = Plan::find($planId);
            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Verificar permisos
            if (!$user->hasRole('admin') && 
                $plan->creado_por_usuario_id !== $user->id &&
                !$plan->esOrganizadorPrincipal($user->emprendimientos()->pluck('id')->toArray())) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para ver esta información'
                ], Response::HTTP_FORBIDDEN);
            }
            
            // Obtener IDs de emprendedores que ya están en el plan
            $emprendedoresEnPlan = $plan->emprendedores()->pluck('emprendedores.id');
            
            // Obtener emprendedores disponibles
            $emprendedoresDisponibles = Emprendedor::select('id', 'nombre', 'ubicacion', 'telefono', 'email')
                                                  ->whereNotIn('id', $emprendedoresEnPlan)
                                                  ->where('estado', true)
                                                  ->orderBy('nombre')
                                                  ->get();
            
            return response()->json([
                'success' => true,
                'data' => $emprendedoresDisponibles,
                'meta' => [
                    'total_disponibles' => $emprendedoresDisponibles->count(),
                    'emprendedores_en_plan' => $emprendedoresEnPlan->count(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener emprendedores disponibles: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}