<?php
namespace App\Http\Controllers\API\Reservas;

use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReservaRequest;
use App\Models\Reserva;
use App\Models\ReservaServicio;
use App\Repository\ReservaRepository;
use App\Repository\ServicioRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class ReservaController extends Controller
{
    protected $repository;

    public function __construct(ReservaRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @OA\Get(
     *     path="/api/reservas",
     *     summary="Obtener todas las reservas",
     *     tags={"Reservas"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de reservas"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error del servidor"
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            // Si es admin, mostrar todas las reservas, si no, solo las del usuario
            if (Auth::user()->hasRole('admin')) {
                $reservas = $this->repository->getPaginated();
            } else {
                $reservas = $this->repository->getByUsuario(Auth::id());
            }
            
            return response()->json([
                'success' => true,
                'data' => $reservas
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Error al obtener reservas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/reservas/{id}",
     *     summary="Obtener una reserva específica",
     *     tags={"Reservas"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la reserva",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalles de la reserva"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Reserva no encontrada"
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $reserva = $this->repository->findById($id);
            
            // Verificar que la reserva pertenece al usuario actual (a menos que sea admin)
            if (!Auth::user()->hasRole('admin') && $reserva->usuario_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para ver esta reserva'
                ], Response::HTTP_FORBIDDEN);
            }
            
            if (!$reserva) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reserva no encontrada'
                ], Response::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'success' => true,
                'data' => $reserva
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Error al obtener reserva: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
 * @OA\Post(
 *     path="/api/reservas",
 *     summary="Crear una nueva reserva para el usuario autenticado",
 *     tags={"Reservas"},
 *     security={{"sanctum": {}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/ReservaRequest")
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Reserva creada exitosamente"
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Error de validación"
 *     )
 * )
 */
public function store(ReservaRequest $request): JsonResponse
{
    try {
        // Obtener datos validados
        $data = $request->validated();
        
        // Siempre asignar el usuario autenticado actual
        $data['usuario_id'] = Auth::id();
        
        // Extraer servicios
        $servicios = $data['servicios'] ?? [];
        unset($data['servicios']);
        
        // Crear reserva con sus servicios
        $reserva = $this->repository->create($data, $servicios);
        
        return response()->json([
            'success' => true,
            'data' => $reserva,
            'message' => 'Reserva creada exitosamente'
        ], Response::HTTP_CREATED);
    } catch (\Exception $e) {
        Log::error('Error al crear reserva: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

    /**
     * @OA\Put(
     *     path="/api/reservas/{id}",
     *     summary="Actualizar una reserva existente",
     *     tags={"Reservas"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la reserva",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/ReservaRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reserva actualizada exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Reserva no encontrada"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function update(ReservaRequest $request, int $id): JsonResponse
    {
        try {
            // Verificar que la reserva pertenece al usuario actual (a menos que sea admin)
            $reserva = $this->repository->findById($id);
            
            if (!$reserva) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reserva no encontrada'
                ], Response::HTTP_NOT_FOUND);
            }
            
            if (!Auth::user()->hasRole('admin') && $reserva->usuario_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para modificar esta reserva'
                ], Response::HTTP_FORBIDDEN);
            }
            
            // Obtener datos validados
            $data = $request->validated();
            
            // Extraer servicios
            $servicios = $data['servicios'] ?? [];
            unset($data['servicios']);
            
            // Actualizar reserva con sus servicios
            $updated = $this->repository->update($id, $data, $servicios);
            
            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo actualizar la reserva'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->repository->findById($id),
                'message' => 'Reserva actualizada exitosamente'
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Error al actualizar reserva: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/reservas/{id}",
     *     summary="Eliminar una reserva",
     *     tags={"Reservas"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la reserva",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reserva eliminada exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Reserva no encontrada"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="No autorizado"
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            // Verificar que la reserva pertenece al usuario actual (a menos que sea admin)
            $reserva = $this->repository->findById($id);
            
            if (!$reserva) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reserva no encontrada'
                ], Response::HTTP_NOT_FOUND);
            }
            
            if (!Auth::user()->hasRole('admin') && $reserva->usuario_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para eliminar esta reserva'
                ], Response::HTTP_FORBIDDEN);
            }
            
            $deleted = $this->repository->delete($id);
            
            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo eliminar la reserva'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Reserva eliminada exitosamente'
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Error al eliminar reserva: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * @OA\Put(
     *     path="/api/reservas/{id}/estado",
     *     summary="Cambiar el estado de una reserva",
     *     tags={"Reservas"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la reserva",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="estado",
     *                 type="string",
     *                 enum={"pendiente", "confirmada", "cancelada", "completada"},
     *                 description="Nuevo estado de la reserva"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Estado actualizado exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Reserva no encontrada"
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
                'estado' => 'required|in:pendiente,confirmada,cancelada,completada',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            
            // Verificar permisos
            $reserva = $this->repository->findById($id);
            
            if (!$reserva) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reserva no encontrada'
                ], Response::HTTP_NOT_FOUND);
            }
            
            $estado = $request->input('estado');
            $updated = $this->repository->cambiarEstado($id, $estado);
            
            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo actualizar el estado de la reserva'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Estado de reserva actualizado exitosamente',
                'data' => $this->repository->findById($id)
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Error al cambiar estado de reserva: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * @OA\Get(
     *     path="/api/reservas/emprendedor/{emprendedorId}",
     *     summary="Obtener reservas por emprendedor",
     *     tags={"Reservas"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="emprendedorId",
     *         in="path",
     *         required=true,
     *         description="ID del emprendedor",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de reservas del emprendedor"
     *     )
     * )
     */
    public function byEmprendedor(int $emprendedorId): JsonResponse
    {
        try {
            // Verificar que el usuario es administrador del emprendedor o es admin global
            if (!Auth::user()->hasRole('admin') && 
                !Auth::user()->emprendedores()->where('emprendedor_id', $emprendedorId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para ver estas reservas'
                ], Response::HTTP_FORBIDDEN);
            }
            
            $reservas = $this->repository->getByEmprendedor($emprendedorId);
            
            return response()->json([
                'success' => true,
                'data' => $reservas
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Error al obtener reservas por emprendedor: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * @OA\Get(
     *     path="/api/reservas/servicio/{servicioId}",
     *     summary="Obtener reservas por servicio",
     *     tags={"Reservas"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="servicioId",
     *         in="path",
     *         required=true,
     *         description="ID del servicio",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de reservas para el servicio"
     *     )
     * )
     */
    public function byServicio(int $servicioId): JsonResponse
    {
        try {
            // Obtener información del servicio para verificar permisos
            $servicio = app(ServicioRepository::class)->findById($servicioId);
            
            if (!$servicio) {
                return response()->json([
                    'success' => false,
                    'message' => 'Servicio no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Verificar permisos
            if (!Auth::user()->hasRole('admin') && 
                !Auth::user()->emprendedores()->where('emprendedor_id', $servicio->emprendedor_id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para ver estas reservas'
                ], Response::HTTP_FORBIDDEN);
            }
            
            $reservas = $this->repository->getByServicio($servicioId);
            
            return response()->json([
                'success' => true,
                'data' => $reservas
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Error al obtener reservas por servicio: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    /**
     * @OA\Post(
     *     path="/api/reservas/mis-reservas",
     *     summary="Crear una reserva rápida para el usuario autenticado",
     *     tags={"Reservas"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="servicios",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="servicio_id", type="integer"),
     *                     @OA\Property(property="emprendedor_id", type="integer"),
     *                     @OA\Property(property="fecha_inicio", type="string", format="date"),
     *                     @OA\Property(property="fecha_fin", type="string", format="date"),
     *                     @OA\Property(property="hora_inicio", type="string", format="time"),
     *                     @OA\Property(property="hora_fin", type="string", format="time"),
     *                     @OA\Property(property="duracion_minutos", type="integer"),
     *                     @OA\Property(property="cantidad", type="integer"),
     *                     @OA\Property(property="notas_cliente", type="string")
     *                 )
     *             ),
     *             @OA\Property(property="notas", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Reserva creada exitosamente"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function createUserReservation(Request $request): JsonResponse
    {
        try {
            // Validar la solicitud
            $validator = Validator::make($request->all(), [
                'servicios' => 'required|array',
                'servicios.*.servicio_id' => 'required|integer|exists:servicios,id',
                'servicios.*.emprendedor_id' => 'required|integer|exists:emprendedores,id',
                'servicios.*.fecha_inicio' => 'required|date_format:Y-m-d',
                'servicios.*.fecha_fin' => 'nullable|date_format:Y-m-d|after_or_equal:servicios.*.fecha_inicio',
                'servicios.*.hora_inicio' => 'required|date_format:H:i:s',
                'servicios.*.hora_fin' => 'required|date_format:H:i:s|after:servicios.*.hora_inicio',
                'servicios.*.duracion_minutos' => 'required|integer|min:1',
                'servicios.*.cantidad' => 'sometimes|integer|min:1',
                'servicios.*.notas_cliente' => 'nullable|string',
                'notas' => 'nullable|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            
            // Crear array de datos con datos del usuario autenticado
            $data = [
                'usuario_id' => Auth::id(),
                'codigo_reserva' => Reserva::generarCodigoReserva(),
                'estado' => Reserva::ESTADO_PENDIENTE,
                'notas' => $request->notas
            ];
            
            // Obtener servicios de la solicitud
            $servicios = $request->servicios;
            
            // Establecer estado pendiente para todos los servicios
            foreach ($servicios as $key => $servicio) {
                $servicios[$key]['estado'] = ReservaServicio::ESTADO_PENDIENTE;
            }
            
            // Crear reserva con servicios
            $reserva = $this->repository->create($data, $servicios);
            
            return response()->json([
                'success' => true,
                'data' => $reserva,
                'message' => 'Reserva creada exitosamente'
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::error('Error al crear reserva para usuario: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/reservas/mis-reservas",
     *     summary="Obtener todas las reservas del usuario autenticado",
     *     tags={"Reservas"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de reservas del usuario autenticado"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error del servidor"
     *     )
     * )
     */
    public function misReservas(): JsonResponse
    {
        try {
            // Obtener las reservas del usuario autenticado utilizando el método existente del repositorio
            $reservas = $this->repository->getByUsuario(Auth::id());
            
            return response()->json([
                'success' => true,
                'data' => $reservas
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Error al obtener mis reservas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}