<?php

namespace App\Http\Controllers\API\Planes;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesImages; // Asumimos la existencia de este trait
use App\Services\PlanService;
use App\Http\Requests\PlanRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage; // Necesario para el método destroy

// Asumimos que el modelo Plan debe importarse, aunque no estaba en el original.
// use App\Models\Plan;

class PlanController extends Controller
{
    use HandlesImages; // Usar el trait para gestión de imágenes

    protected $planService;

    public function __construct(PlanService $planService)
    {
        $this->planService = $planService;
    }

    /**
     * Obtener todos los planes con filtros opcionales
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = $request->only([
                'emprendedor_id',
                'estado',
                'es_publico',
                'dificultad',
                'buscar',
                'con_cupos',
                'duracion_min',
                'duracion_max',
                'precio_min',
                'precio_max',
                'todos'
            ]);

            $perPage = $request->get('per_page', 15);
            $planes = $this->planService->getAll($filtros, $perPage);

            return response()->json([
                'success' => true,
                'data' => $planes
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener planes: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Crear un nuevo plan (con gestión de imágenes)
     */
    public function store(PlanRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['creado_por_usuario_id'] = Auth::id();

            // 1. Crear el plan primero sin las rutas de archivo
            $dataSinArchivos = collect($data)->except(['imagen_principal', 'imagenes_galeria'])->all();
            $plan = $this->planService->create($dataSinArchivos);

            $datosArchivos = [];

            // 2. Gestión de Imagen Principal
            if ($request->hasFile('imagen_principal')) {
                $datosArchivos['imagen_principal'] = $this->storeImage($request->file('imagen_principal'), "planes/{$plan->id}");
            }

            // 3. Gestión de Galería
            $gal = [];
            if ($request->hasFile('imagenes_galeria')) {
                foreach ($request->file('imagenes_galeria') as $file) {
                    $gal[] = $this->storeImage($file, "planes/{$plan->id}");
                }
            }
            if ($gal) {
                $datosArchivos['imagenes_galeria'] = $gal;
            }

            // 4. Actualizar el plan con las rutas
            if (!empty($datosArchivos)) {
                $this->planService->update($plan->id, $datosArchivos);
            }

            return response()->json([
                'success' => true,
                'message' => 'Plan creado correctamente',
                'data' => $plan->fresh() // Devolvemos el plan con las rutas de imágenes
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::error('Error al crear plan: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el plan: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener un plan específico
     */
    public function show($id): JsonResponse
    {
        try {
            $id = (int) $id;
            $plan = $this->planService->getById($id);

            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'data' => $plan
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener plan: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Actualizar un plan existente (con gestión de imágenes)
     */
    public function update(PlanRequest $request, $id): JsonResponse
    {
        try {
            $id = (int) $id;
            $data = $request->validated();

            // Primero, obtener el plan para la gestión de archivos
            $plan = $this->planService->getById($id);

            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }

            $datosActualizacion = collect($data)->except(['imagen_principal', 'imagenes_galeria', 'eliminar_galeria'])->all();

            // 1. Gestión de Imagen Principal
            if ($request->hasFile('imagen_principal')) {
                $this->deleteImage($plan->imagen_principal); // Eliminar la anterior
                $datosActualizacion['imagen_principal'] = $this->storeImage($request->file('imagen_principal'), "planes/{$plan->id}");
            }

            // 2. Gestión de Galería (añadir nuevas)
            if ($request->hasFile('imagenes_galeria')) {
                $gal = $plan->imagenes_galeria ?? [];
                foreach ($request->file('imagenes_galeria') as $file) {
                    $gal[] = $this->storeImage($file, "planes/{$plan->id}");
                }
                $datosActualizacion['imagenes_galeria'] = array_values(array_unique($gal));
            }

            // 3. Actualizar el plan con los datos procesados
            $planActualizado = $this->planService->update($id, $datosActualizacion);

            // 4. Gestión de Galería (eliminar puntuales)
            if ($del = $request->input('eliminar_galeria', [])) {
                // Se necesita la instancia actualizada del plan
                $plan->refresh();
                $gal = collect($plan->imagenes_galeria ?? []);

                foreach ($del as $path) {
                    $this->deleteImage($path);
                    $gal = $gal->reject(fn($p) => $p === $path);
                }

                // Actualizar la columna 'imagenes_galeria' directamente en el modelo/servicio
                $planActualizado = $this->planService->update($id, ['imagenes_galeria' => $gal->values()->all()]);
            }

            if (!$planActualizado) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan no encontrado o error en el servicio de actualización'
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'message' => 'Plan actualizado correctamente',
                'data' => $planActualizado->fresh()
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar plan: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el plan: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Eliminar un plan (con eliminación de archivos)
     */
    public function destroy($id): JsonResponse
    {
        try {
            $id = (int) $id;

            $deleted = $this->planService->delete($id);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }

            // === Lógica de Gestión de Archivos (Añadida) ===
            // Borrar carpeta completa asociada al plan
            $folder = "planes/{$id}";
            if (Storage::disk('media')->exists($folder)) {
                Storage::disk('media')->deleteDirectory($folder);
            }
            // ===============================================

            return response()->json([
                'success' => true,
                'message' => 'Plan eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar plan: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el plan: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Buscar planes
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $termino = $request->get('q', '');
            $filtros = $request->only(['dificultad', 'emprendedor_id']);

            if (empty($termino)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Término de búsqueda requerido'
                ], Response::HTTP_BAD_REQUEST);
            }

            $planes = $this->planService->buscar($termino, $filtros);

            return response()->json([
                'success' => true,
                'data' => $planes
            ]);
        } catch (\Exception $e) {
            Log::error('Error al buscar planes: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la búsqueda: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener estadísticas de un plan
     */
    public function estadisticas($id): JsonResponse
    {
        try {
            $id = (int) $id;
            $estadisticas = $this->planService->getEstadisticas($id);

            if (empty($estadisticas)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }

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
     * Cambiar estado de un plan
     */
    public function cambiarEstado(Request $request, $id): JsonResponse
    {
        try {
            $id = (int) $id;
            $nuevoEstado = $request->get('estado');

            if (!in_array($nuevoEstado, ['activo', 'inactivo', 'borrador'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Estado no válido'
                ], Response::HTTP_BAD_REQUEST);
            }

            $plan = $this->planService->getById($id);

            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }

            // Verificar permisos
            if (!Auth::user()->hasRole('admin') &&
                $plan->creado_por_usuario_id !== Auth::id() &&
                (!$plan->emprendedor || !$plan->emprendedor->administradores()->where('users.id', Auth::id())->exists())) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para modificar este plan'
                ], Response::HTTP_FORBIDDEN);
            }

            $planActualizado = $this->planService->update($id, ['estado' => $nuevoEstado]);

            return response()->json([
                'success' => true,
                'message' => 'Estado del plan actualizado correctamente',
                'data' => $planActualizado
            ]);
        } catch (\Exception $e) {
            Log::error('Error al cambiar estado del plan: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener planes públicos con filtros específicos
     */
    public function publicos(Request $request): JsonResponse
    {
        try {
            $filtros = $request->only([
                'con_cupos',
                'dificultad',
                'duracion_min',
                'duracion_max',
                'precio_min',
                'precio_max',
                'buscar',
                'emprendedor_id',
                'organizador_id'
            ]);

            // Limpiar filtros vacíos
            $filtros = array_filter($filtros, function($value) {
                return $value !== null && $value !== '';
            });

            $perPage = min($request->get('per_page', 15), 50); // Máximo 50 por página

            // Usar el método simple si hay problemas con relaciones complejas
            $planes = $this->planService->getPlanesPublicos($filtros, $perPage);

            return response()->json([
                'success' => true,
                'data' => $planes,
                'meta' => [
                    'filtros_aplicados' => $filtros,
                    'per_page' => $perPage,
                    'current_page' => $planes->currentPage(),
                    'total' => $planes->total(),
                    'last_page' => $planes->lastPage(),
                    'has_more_pages' => $planes->hasMorePages()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener planes públicos: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
