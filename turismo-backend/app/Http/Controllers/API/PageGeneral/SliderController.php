<?php

namespace App\Http\Controllers\API\PageGeneral;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Repository\SliderRepository;

class SliderController extends Controller
{
    protected $repository;

    public function __construct(SliderRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Obtener sliders con filtros opcionales
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->query('per_page', 15);
            $filtros = [
                'tipo_entidad' => $request->query('tipo_entidad'),
                'entidad_id' => $request->query('entidad_id'),
                'es_principal' => $request->has('es_principal') ? filter_var($request->query('es_principal'), FILTER_VALIDATE_BOOLEAN) : null,
                'with_descripcion' => filter_var($request->query('with_descripcion', true), FILTER_VALIDATE_BOOLEAN),
            ];

            $sliders = $this->repository->getPaginated($perPage, array_filter($filtros));

            return response()->json([
                'success' => true,
                'data' => $sliders
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener sliders: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener todos los sliders de una entidad específica
     */
    public function getByEntidad(Request $request, string $tipo, int $id): JsonResponse
    {
        try {
            $tiposValidos = ['municipalidad', 'emprendedor', 'servicio', 'evento'];
            
            if (!in_array($tipo, $tiposValidos)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tipo de entidad no válido'
                ], 400);
            }
            
            $withDescripcion = filter_var($request->query('with_descripcion', true), FILTER_VALIDATE_BOOLEAN);
            
            $filtros = [
                'tipo_entidad' => $tipo,
                'entidad_id' => $id,
                'with_descripcion' => $withDescripcion
            ];

            $sliders = $this->repository->getAll($filtros);

            // Agrupamos por principales y secundarios
            $principales = $sliders->where('es_principal', true)->values();
            $secundarios = $sliders->where('es_principal', false)->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'principales' => $principales,
                    'secundarios' => $secundarios
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener sliders por entidad: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un slider específico
     */
    public function show(int $id): JsonResponse
    {
        try {
            $slider = $this->repository->findById($id, true);
            
            if (!$slider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Slider no encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $slider
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener slider: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear un nuevo slider
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:255',
                'tipo_entidad' => 'required|string|in:municipalidad,emprendedor,servicio,evento',
                'entidad_id' => 'required|integer|min:1',
                'es_principal' => 'required|boolean',
                'orden' => 'nullable|integer|min:0',
                'activo' => 'nullable|boolean',
                'imagen' => 'required|image|max:5120', // 5MB máximo
                'titulo' => 'nullable|required_if:es_principal,false|string|max:255',
                'descripcion' => 'nullable|required_if:es_principal,false|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $data = $validator->validated();
            
            // Preparar datos de descripción si es un slider secundario
            $descripcionData = null;
            if (!$data['es_principal']) {
                $descripcionData = [
                    'titulo' => $data['titulo'] ?? null,
                    'descripcion' => $data['descripcion'] ?? null,
                ];
                
                // Eliminar estos campos de los datos del slider
                unset($data['titulo']);
                unset($data['descripcion']);
            }
            
            $slider = $this->repository->create($data, $descripcionData);
            
            return response()->json([
                'success' => true,
                'message' => 'Slider creado exitosamente',
                'data' => $slider
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear slider: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un slider existente
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $slider = $this->repository->findById($id);
            
            if (!$slider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Slider no encontrado'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'nombre' => 'sometimes|string|max:255',
                'tipo_entidad' => 'sometimes|string|in:municipalidad,emprendedor,servicio',
                'entidad_id' => 'sometimes|integer|min:1',
                'es_principal' => 'sometimes|boolean',
                'orden' => 'nullable|integer|min:0',
                'activo' => 'nullable|boolean',
                'imagen' => 'nullable|image|max:5120', // 5MB máximo
                'titulo' => 'nullable|string|max:255',
                'descripcion' => 'nullable|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $data = $validator->validated();
            
            // Preparar datos de descripción si hay campos de descripción
            $descripcionData = null;
            if (isset($data['titulo']) || isset($data['descripcion'])) {
                $descripcionData = array_filter([
                    'titulo' => $data['titulo'] ?? null,
                    'descripcion' => $data['descripcion'] ?? null,
                ]);
                
                // Eliminar estos campos de los datos del slider
                unset($data['titulo']);
                unset($data['descripcion']);
            }
            
            $updatedSlider = $this->repository->update($id, $data, $descripcionData);
            
            return response()->json([
                'success' => true,
                'message' => 'Slider actualizado exitosamente',
                'data' => $updatedSlider
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar slider: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un slider
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->repository->delete($id);
            
            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Slider no encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Slider eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar slider: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear múltiples sliders para una entidad
     */
    public function storeMultiple(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'tipo_entidad' => 'required|string|in:municipalidad,emprendedor,servicio',
                'entidad_id' => 'required|integer|min:1',
                'sliders' => 'required|array|min:1',
                'sliders.*.nombre' => 'required|string|max:255',
                'sliders.*.es_principal' => 'required|boolean',
                'sliders.*.orden' => 'nullable|integer|min:0',
                'sliders.*.activo' => 'nullable|boolean',
                'sliders.*.imagen' => 'required|image|max:5120', // 5MB máximo
                'sliders.*.titulo' => 'nullable|required_if:sliders.*.es_principal,false|string|max:255',
                'sliders.*.descripcion' => 'nullable|required_if:sliders.*.es_principal,false|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $data = $validator->validated();
            
            $sliders = $this->repository->createMultiple(
                $data['tipo_entidad'],
                $data['entidad_id'],
                $data['sliders']
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Sliders creados exitosamente',
                'data' => $sliders
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear sliders múltiples: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }
}