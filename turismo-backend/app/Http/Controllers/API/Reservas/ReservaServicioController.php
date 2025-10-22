<?php

namespace App\Http\Controllers\API\Reservas;

use App\Http\Controllers\Controller;
use App\Repository\ReservaRepository;
use App\Repository\ReservaServicioRepository;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ReservaServicioController extends Controller
{
    protected $repository;

    public function __construct(ReservaServicioRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @OA\Get(
     *     path="/api/reserva-servicios/reserva/{reservaId}",
     *     summary="Obtener servicios de una reserva",
     *     tags={"ReservaServicios"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="reservaId",
     *         in="path",
     *         required=true,
     *         description="ID de la reserva",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de servicios de la reserva"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="No autorizado"
     *     )
     * )
     */
    public function byReserva(int $reservaId): JsonResponse
    {
        try {
            // Verificar permisos
            $reserva = app(ReservaRepository::class)->findById($reservaId);
            
            if (!$reserva) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reserva no encontrada'
                ], Response::HTTP_NOT_FOUND);
            }
            
            if (!Auth::user()->hasRole('admin') && $reserva->usuario_id !== Auth::id()) {
                // Verificar si el usuario es administrador de algún emprendedor con servicios en esta reserva
                $esAdminEmprendedor = $reserva->servicios()->whereIn('emprendedor_id', 
                    Auth::user()->emprendedores->pluck('id')->toArray()
                )->exists();
                
                if (!$esAdminEmprendedor) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No tienes permiso para ver estos servicios'
                    ], Response::HTTP_FORBIDDEN);
                }
            }
            
            $servicios = $this->repository->getByReserva($reservaId);
            
            return response()->json([
                'success' => true,
                'data' => $servicios
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Error al obtener servicios de reserva: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/reserva-servicios/{id}/estado",
     *     summary="Cambiar el estado de un servicio reservado",
     *     tags={"ReservaServicios"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del servicio reservado",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="estado",
     *                 type="string",
     *                 enum={"pendiente", "confirmado", "cancelado", "completado"},
     *                 description="Nuevo estado del servicio reservado"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Estado actualizado exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Servicio reservado no encontrado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function cambiarEstado(Request $request, int $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'estado' => 'required|in:pendiente,confirmado,cancelado,completado',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            
            // Verificar permisos
            $servicio = $this->repository->findById($id);
            
            if (!$servicio) {
                return response()->json([
                    'success' => false,
                    'message' => 'Servicio reservado no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Verificar si el usuario es admin o dueño de la reserva o admin del emprendedor
            if (!Auth::user()->hasRole('admin') && 
                $servicio->reserva->usuario_id !== Auth::id() &&
                !Auth::user()->emprendedores()->where('emprendedor_id', $servicio->emprendedor_id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para modificar este servicio'
                ], Response::HTTP_FORBIDDEN);
            }
            
            $estado = $request->input('estado');
            $updated = $this->repository->cambiarEstado($id, $estado);
            
            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo actualizar el estado del servicio'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Estado del servicio actualizado exitosamente',
                'data' => $this->repository->findById($id)
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Error al cambiar estado de servicio reservado: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * @OA\Get(
     *     path="/api/reserva-servicios/calendario",
     *     summary="Obtener servicios reservados para el calendario",
     *     tags={"ReservaServicios"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="fecha_inicio",
     *         in="query",
     *         required=true,
     *         description="Fecha de inicio (Y-m-d)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="fecha_fin",
     *         in="query",
     *         required=true,
     *         description="Fecha de fin (Y-m-d)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="emprendedor_id",
     *         in="query",
     *         required=false,
     *         description="ID del emprendedor (opcional)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de servicios para el calendario"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function calendario(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'fecha_inicio' => 'required|date_format:Y-m-d',
                'fecha_fin' => 'required|date_format:Y-m-d|after_or_equal:fecha_inicio',
                'emprendedor_id' => 'nullable|integer|exists:emprendedores,id',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            
            $fechaInicio = $request->fecha_inicio;
            $fechaFin = $request->fecha_fin;
            
            // Obtener servicios por rango de fechas
            $servicios = $this->repository->getByRangoFechas($fechaInicio, $fechaFin);
            
            // Filtrar por emprendedor si se especifica
            if ($request->has('emprendedor_id')) {
                $emprendedorId = $request->emprendedor_id;
                $servicios = $servicios->filter(function ($servicio) use ($emprendedorId) {
                    return $servicio->emprendedor_id == $emprendedorId;
                })->values();
            }
            
            // Si no es admin, solo mostrar servicios propios o de sus emprendedores
            if (!Auth::user()->hasRole('admin')) {
                $servicios = $servicios->filter(function ($servicio) {
                    return $servicio->reserva->usuario_id === Auth::id() || 
                           Auth::user()->emprendedores()->where('emprendedor_id', $servicio->emprendedor_id)->exists();
                })->values();
            }
            
            return response()->json([
                'success' => true,
                'data' => $servicios
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Error al obtener calendario: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * @OA\Get(
     *     path="/api/reserva-servicios/verificar-disponibilidad",
     *     summary="Verificar disponibilidad de un servicio para reserva",
     *     tags={"ReservaServicios"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="servicio_id",
     *         in="query",
     *         required=true,
     *         description="ID del servicio",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="fecha_inicio",
     *         in="query",
     *         required=true,
     *         description="Fecha de inicio (Y-m-d)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="fecha_fin",
     *         in="query",
     *         required=false,
     *         description="Fecha de fin (Y-m-d)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="hora_inicio",
     *         in="query",
     *         required=true,
     *         description="Hora de inicio (H:i:s)",
     *         @OA\Schema(type="string", format="time")
     *     ),
     *     @OA\Parameter(
     *         name="hora_fin",
     *         in="query",
     *         required=true,
     *         description="Hora de fin (H:i:s)",
     *         @OA\Schema(type="string", format="time")
     *     ),
     *     @OA\Parameter(
     *         name="reserva_servicio_id",
     *         in="query",
     *         required=false,
     *         description="ID del servicio reservado (para excluir al verificar)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Resultado de disponibilidad"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function verificarDisponibilidad(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'servicio_id' => 'required|integer|exists:servicios,id',
                'fecha_inicio' => 'required|date_format:Y-m-d',
                'fecha_fin' => 'nullable|date_format:Y-m-d|after_or_equal:fecha_inicio',
                'hora_inicio' => 'required|date_format:H:i:s',
                'hora_fin' => 'required|date_format:H:i:s|after:hora_inicio',
                'reserva_servicio_id' => 'nullable|integer|exists:reserva_servicios,id',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            
            $disponible = $this->repository->verificarDisponibilidad(
                $request->servicio_id,
                $request->fecha_inicio,
                $request->fecha_fin,
                $request->hora_inicio,
                $request->hora_fin,
                $request->reserva_servicio_id
            );
            
            return response()->json([
                'success' => true,
                'disponible' => $disponible
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Error al verificar disponibilidad: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}