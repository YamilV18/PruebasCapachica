<?php
namespace App\Http\Controllers\API\Reservas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Reserva;
use App\Models\ReservaServicio;
use App\Repository\ReservaRepository;
use App\Repository\ServicioRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use App\Repository\ReservaServicioRepository;
use Illuminate\Support\Facades\Log;

class CarritoReservaController extends Controller
{
    protected $repository;
    protected $servicioRepository;

    public function __construct(ReservaRepository $repository, ServicioRepository $servicioRepository)
    {
        $this->repository = $repository;
        $this->servicioRepository = $servicioRepository;
    }

    /**
     * @OA\Get(
     *     path="/api/reservas/carrito",
     *     summary="Obtener el carrito de reservas del usuario actual",
     *     tags={"Carrito de Reservas"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Carrito de reservas del usuario"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Carrito no encontrado"
     *     )
     * )
     */
    public function obtenerCarrito(): JsonResponse
    {
        try {
            // Buscar reserva con estado 'en_carrito' para el usuario actual
            $carrito = Reserva::where('usuario_id', Auth::id())
                ->where('estado', Reserva::ESTADO_EN_CARRITO)
                ->with(['servicios.servicio', 'servicios.emprendedor'])
                ->first();
                
            if (!$carrito) {
                // Si no existe, crear un nuevo carrito vacío
                $carrito = new Reserva([
                    'usuario_id' => Auth::id(),
                    'codigo_reserva' => Reserva::generarCodigoReserva(),
                    'estado' => Reserva::ESTADO_EN_CARRITO,
                    'notas' => null
                ]);
                $carrito->save();
            }
            
            return response()->json([
                'success' => true,
                'data' => $carrito
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Error al obtener carrito: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/reservas/carrito/agregar",
     *     summary="Agregar un servicio al carrito de reservas",
     *     tags={"Carrito de Reservas"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="servicio_id", type="integer"),
     *             @OA\Property(property="emprendedor_id", type="integer"),
     *             @OA\Property(property="fecha_inicio", type="string", format="date"),
     *             @OA\Property(property="fecha_fin", type="string", format="date"),
     *             @OA\Property(property="hora_inicio", type="string", format="time"),
     *             @OA\Property(property="hora_fin", type="string", format="time"),
     *             @OA\Property(property="duracion_minutos", type="integer"),
     *             @OA\Property(property="cantidad", type="integer"),
     *             @OA\Property(property="notas_cliente", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Servicio agregado exitosamente"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function agregarAlCarrito(Request $request): JsonResponse
    {
        try {
            // Validar datos
            $validator = Validator::make($request->all(), [
                'servicio_id' => 'required|integer|exists:servicios,id',
                'emprendedor_id' => 'required|integer|exists:emprendedores,id',
                'fecha_inicio' => 'required|date_format:Y-m-d',
                'fecha_fin' => 'nullable|date_format:Y-m-d|after_or_equal:fecha_inicio',
                'hora_inicio' => 'required|date_format:H:i:s',
                'hora_fin' => 'required|date_format:H:i:s|after:hora_inicio',
                'duracion_minutos' => 'required|integer|min:1',
                'cantidad' => 'sometimes|integer|min:1',
                'notas_cliente' => 'nullable|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            
            // Verificar disponibilidad
            $disponibilidad = app(ReservaServicioRepository::class)->verificarDisponibilidad(
                $request->servicio_id,
                $request->fecha_inicio,
                $request->fecha_fin,
                $request->hora_inicio,
                $request->hora_fin
            );
            
            if (!$disponibilidad) {
                return response()->json([
                    'success' => false,
                    'message' => 'El servicio no está disponible en el horario seleccionado'
                ], Response::HTTP_CONFLICT);
            }
            
            DB::beginTransaction();
            
            // Obtener o crear carrito
            $carrito = Reserva::where('usuario_id', Auth::id())
                ->where('estado', Reserva::ESTADO_EN_CARRITO)
                ->first();
                
            if (!$carrito) {
                $carrito = new Reserva([
                    'usuario_id' => Auth::id(),
                    'codigo_reserva' => Reserva::generarCodigoReserva(),
                    'estado' => Reserva::ESTADO_EN_CARRITO,
                    'notas' => null
                ]);
                $carrito->save();
            }
            
            // CORRECCIÓN: Obtener precio_referencial del servicio
            $servicio = $this->servicioRepository->findById($request->servicio_id);
            $precio = $servicio ? $servicio->precio_referencial : 0;
            
            // Crear nuevo servicio en el carrito
            $servicioCarrito = new ReservaServicio([
                'reserva_id' => $carrito->id,
                'servicio_id' => $request->servicio_id,
                'emprendedor_id' => $request->emprendedor_id,
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_fin' => $request->fecha_fin,
                'hora_inicio' => $request->hora_inicio,
                'hora_fin' => $request->hora_fin,
                'duracion_minutos' => $request->duracion_minutos,
                'cantidad' => $request->cantidad ?? 1,
                'precio' => $precio, // Este es el precio específico de la reserva
                'estado' => ReservaServicio::ESTADO_EN_CARRITO,
                'notas_cliente' => $request->notas_cliente,
                'notas_emprendedor' => null
            ]);
            
            $servicioCarrito->save();
            
            DB::commit();
            
            // Retornar carrito actualizado
            $carrito = $carrito->fresh(['servicios.servicio:id,nombre,precio_referencial', 'servicios.emprendedor']);
            
            return response()->json([
                'success' => true,
                'message' => 'Servicio agregado al carrito exitosamente',
                'data' => $carrito
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al agregar al carrito: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/reservas/carrito/servicio/{id}",
     *     summary="Eliminar un servicio del carrito de reservas",
     *     tags={"Carrito de Reservas"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del servicio en el carrito",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Servicio eliminado exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Servicio no encontrado"
     *     )
     * )
     */
    public function eliminarDelCarrito(int $id): JsonResponse
    {
        try {
            // Buscar el servicio en el carrito
            $servicioCarrito = ReservaServicio::find($id);
            
            if (!$servicioCarrito) {
                return response()->json([
                    'success' => false,
                    'message' => 'Servicio no encontrado en el carrito'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Verificar que pertenezca al usuario actual
            if ($servicioCarrito->reserva->usuario_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para eliminar este servicio'
                ], Response::HTTP_FORBIDDEN);
            }
            
            // Verificar que esté en estado de carrito
            if ($servicioCarrito->reserva->estado !== Reserva::ESTADO_EN_CARRITO) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este servicio ya no está en el carrito'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Eliminar servicio
            $servicioCarrito->delete();
            
            // Verificar si quedan servicios en el carrito
            $carrito = $servicioCarrito->reserva->fresh();
            $carritoActualizado = $carrito->fresh(['servicios.servicio', 'servicios.emprendedor']);
            
            return response()->json([
                'success' => true,
                'message' => 'Servicio eliminado del carrito exitosamente',
                'data' => $carritoActualizado
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Error al eliminar del carrito: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/reservas/carrito/confirmar",
     *     summary="Confirmar y convertir el carrito en una reserva",
     *     tags={"Carrito de Reservas"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="notas", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Reserva creada exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Carrito no encontrado"
     *     )
     * )
     */
    public function confirmarCarrito(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            // Buscar el carrito del usuario
            $carrito = Reserva::where('usuario_id', Auth::id())
                ->where('estado', Reserva::ESTADO_EN_CARRITO)
                ->with('servicios')
                ->first();
                
            if (!$carrito) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró un carrito de reservas'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Verificar que el carrito tenga servicios
            if ($carrito->servicios->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El carrito está vacío. Agregue servicios antes de confirmar.'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Actualizar estado de la reserva
            $carrito->estado = Reserva::ESTADO_PENDIENTE;
            $carrito->notas = $request->notas ?? $carrito->notas;
            $carrito->save();
            
            // Actualizar estado de los servicios
            foreach ($carrito->servicios as $servicio) {
                $servicio->estado = ReservaServicio::ESTADO_PENDIENTE;
                $servicio->save();
            }
            
            DB::commit();
            
            // Retornar reserva confirmada
            $reservaConfirmada = $carrito->fresh(['servicios.servicio', 'servicios.emprendedor']);
            
            return response()->json([
                'success' => true,
                'message' => 'Reserva creada exitosamente',
                'data' => $reservaConfirmada
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al confirmar carrito: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/reservas/carrito/vaciar",
     *     summary="Vaciar el carrito de reservas",
     *     tags={"Carrito de Reservas"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Carrito vaciado exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Carrito no encontrado"
     *     )
     * )
     */
    public function vaciarCarrito(): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            // Buscar el carrito del usuario
            $carrito = Reserva::where('usuario_id', Auth::id())
                ->where('estado', Reserva::ESTADO_EN_CARRITO)
                ->first();
                
            if (!$carrito) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró un carrito de reservas'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Eliminar todos los servicios del carrito
            $carrito->servicios()->delete();
            
            DB::commit();
            
            // Retornar carrito vacío
            $carritoVacio = $carrito->fresh();
            
            return response()->json([
                'success' => true,
                'message' => 'Carrito vaciado exitosamente',
                'data' => $carritoVacio
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al vaciar carrito: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}