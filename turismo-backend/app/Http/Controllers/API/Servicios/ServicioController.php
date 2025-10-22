<?php

namespace App\Http\Controllers\API\Servicios;

use App\Http\Controllers\Concerns\HandlesImages;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServicioRequest;
use App\Repository\ServicioRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServicioController extends Controller
{
    use HandlesImages;

    protected $repository;

    public function __construct(ServicioRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @OA\Get(
     *     path="/api/servicios",
     *     summary="Obtener todos los servicios",
     *     tags={"Servicios"},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de servicios con paginación"
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $servicios = $this->repository->getPaginated();

        // Cargar relaciones
        foreach ($servicios as $servicio) {
            $servicio->load(['sliders', 'horarios']);
        }

        return response()->json([
            'success' => true,
            'data' => $servicios
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/servicios/{id}",
     *     summary="Obtener un servicio por ID",
     *     tags={"Servicios"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del servicio",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalles del servicio"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Servicio no encontrado"
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $servicio = $this->repository->findById($id);

        if (!$servicio) {
            return response()->json([
                'success' => false,
                'message' => 'Servicio no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $servicio
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/servicios",
     *     summary="Crear un nuevo servicio",
     *     tags={"Servicios"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/ServicioRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Servicio creado exitosamente"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function store(ServicioRequest $request): JsonResponse
    {
        try {
            // Obtener datos validados
            $data = $request->validated();

            // Extraer categorías y horarios
            $categorias = $data['categorias'] ?? [];
            $horarios = $data['horarios'] ?? [];

            // Convertir explícitamente los valores booleanos
            if (isset($data['estado'])) {
                $data['estado'] = filter_var($data['estado'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
            }

            // Procesar los horarios para convertir el campo activo a booleano
            if (!empty($horarios)) {
                foreach ($horarios as $key => $horario) {
                    if (isset($horario['activo'])) {
                        $horarios[$key]['activo'] = filter_var($horario['activo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
                    }
                }
            }

            // Procesar los sliders para manejar las imágenes binarias
            if (isset($data['sliders']) && is_array($data['sliders'])) {
                foreach ($data['sliders'] as $key => $slider) {
                    // Si la imagen es un archivo binario, procesarla adecuadamente
                    if (isset($slider['imagen']) && $request->hasFile("sliders.{$key}.imagen")) {
                        // Guardar el archivo en el almacenamiento y obtener la ruta
                        $path = $this->storeImage($request->file("sliders.{$key}.imagen"), "servicios/{$request->emprendedor_id}/sliders");
                        $data['sliders'][$key]['imagen'] = $path;
                    }
                }
            }

            // Crear servicio con sus relaciones
            $servicio = $this->repository->create($data, $categorias, $horarios);

            return response()->json([
                'success' => true,
                'data' => $servicio,
                'message' => 'Servicio creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el servicio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/servicios/{id}",
     *     summary="Actualizar un servicio existente",
     *     tags={"Servicios"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del servicio",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/ServicioRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Servicio actualizado exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Servicio no encontrado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function update(ServicioRequest $request, int $id): JsonResponse
    {
        // Obtener datos validados
        $data = $request->validated();

        // Extraer categorías y horarios
        $categorias = $data['categorias'] ?? [];
        $horarios = $data['horarios'] ?? [];

        // Convertir explícitamente el estado a booleano
        if (isset($data['estado'])) {
            $data['estado'] = filter_var($data['estado'], FILTER_VALIDATE_BOOLEAN);
        }

        // Actualizar servicio con sus relaciones
        $updated = $this->repository->update($id, $data, $categorias, $horarios);

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Servicio no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->repository->findById($id),
            'message' => 'Servicio actualizado exitosamente'
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/servicios/{id}",
     *     summary="Eliminar un servicio",
     *     tags={"Servicios"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del servicio",
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
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->repository->delete($id);

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Servicio no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Servicio eliminado exitosamente'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/servicios/emprendedor/{emprendedorId}",
     *     summary="Obtener servicios por emprendedor",
     *     tags={"Servicios"},
     *     @OA\Parameter(
     *         name="emprendedorId",
     *         in="path",
     *         required=true,
     *         description="ID del emprendedor",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de servicios del emprendedor"
     *     )
     * )
     */
    public function byEmprendedor(int $emprendedorId): JsonResponse
    {
        $servicios = $this->repository->getServiciosByEmprendedor($emprendedorId);

        return response()->json([
            'success' => true,
            'data' => $servicios
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/servicios/categoria/{categoriaId}",
     *     summary="Obtener servicios por categoría",
     *     tags={"Servicios"},
     *     @OA\Parameter(
     *         name="categoriaId",
     *         in="path",
     *         required=true,
     *         description="ID de la categoría",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de servicios por categoría"
     *     )
     * )
     */
    public function byCategoria(int $categoriaId): JsonResponse
    {
        $servicios = $this->repository->getServiciosByCategoria($categoriaId);

        return response()->json([
            'success' => true,
            'data' => $servicios
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/servicios/ubicacion",
     *     summary="Obtener servicios por ubicación geográfica",
     *     tags={"Servicios"},
     *     @OA\Parameter(
     *         name="latitud",
     *         in="query",
     *         required=true,
     *         description="Latitud de la ubicación",
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="longitud",
     *         in="query",
     *         required=true,
     *         description="Longitud de la ubicación",
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="distancia",
     *         in="query",
     *         required=false,
     *         description="Distancia en kilómetros",
     *         @OA\Schema(type="number", format="float", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de servicios cercanos"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function byUbicacion(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitud' => 'required|numeric',
            'longitud' => 'required|numeric',
            'distancia' => 'nullable|numeric|min:0.1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $latitud = $request->latitud;
        $longitud = $request->longitud;
        $distancia = $request->distancia ?? 10;

        $servicios = $this->repository->getServiciosByUbicacion($latitud, $longitud, $distancia);

        return response()->json([
            'success' => true,
            'data' => $servicios
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/servicios/verificar-disponibilidad",
     *     summary="Verificar disponibilidad de un servicio",
     *     tags={"Servicios"},
     *     @OA\Parameter(
     *         name="servicio_id",
     *         in="query",
     *         required=true,
     *         description="ID del servicio",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="fecha",
     *         in="query",
     *         required=true,
     *         description="Fecha en formato Y-m-d",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="hora_inicio",
     *         in="query",
     *         required=true,
     *         description="Hora de inicio en formato H:i:s",
     *         @OA\Schema(type="string", format="time")
     *     ),
     *     @OA\Parameter(
     *         name="hora_fin",
     *         in="query",
     *         required=true,
     *         description="Hora de fin en formato H:i:s",
     *         @OA\Schema(type="string", format="time")
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
        $validator = Validator::make($request->all(), [
            'servicio_id' => 'required|integer|exists:servicios,id',
            'fecha' => 'required|date_format:Y-m-d',
            'hora_inicio' => 'required|date_format:H:i:s',
            'hora_fin' => 'required|date_format:H:i:s|after:hora_inicio',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $disponible = $this->repository->verificarDisponibilidad(
            $request->servicio_id,
            $request->fecha,
            $request->hora_inicio,
            $request->hora_fin
        );

        return response()->json([
            'success' => true,
            'disponible' => $disponible
        ]);
    }
}
