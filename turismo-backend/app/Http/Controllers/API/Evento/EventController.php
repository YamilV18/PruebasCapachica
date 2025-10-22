<?php

namespace App\Http\Controllers\API\Evento;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesImages; // Importar el trait
use App\Repository\EventoRepository;
use App\Http\Requests\EventoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage; // Necesario para el método destroy

class EventController extends Controller
{
    use HandlesImages; // Usar el trait para gestión de imágenes

    protected $eventoRepository;

    public function __construct(EventoRepository $eventoRepository)
    {
        $this->eventoRepository = $eventoRepository;
    }

    /**
     * Obtener una lista paginada de eventos.
     */
    public function index(): JsonResponse
    {
        try {
            $eventos = $this->eventoRepository->getPaginated();

            return response()->json([
                'success' => true,
                'data' => $eventos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los eventos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un evento por ID.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $evento = $this->eventoRepository->getById($id);

            if (!$evento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Evento no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $evento
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el evento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear un nuevo evento (con gestión de sliders/imágenes).
     */
    public function store(EventoRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // 1. Separar los sliders/imágenes para crearlos después
            $sliders = $data['sliders'] ?? [];
            $dataSinSliders = collect($data)->except('sliders')->all();

            // 2. Crear el evento base. Asumo que el repositorio lo maneja.
            $evento = $this->eventoRepository->create($dataSinSliders);
            $eventoId = $evento->id;

            // 3. Procesar los sliders
            if (!empty($sliders)) {
                $processedSliders = [];
                foreach ($sliders as $key => $slider) {
                    $sliderData = $slider;

                    // Manejo de la carga de imagen usando HandlesImages
                    if ($request->hasFile("sliders.{$key}.imagen")) {
                        // Guardar la imagen en el directorio del evento
                        $path = $this->storeImage($request->file("sliders.{$key}.imagen"), "eventos/{$eventoId}");
                        $sliderData['url'] = $path;
                    }

                    // Asignar valores requeridos por el SliderRepository
                    $sliderData['tipo_entidad'] = 'evento';
                    $sliderData['entidad_id'] = $eventoId;
                    $sliderData['activo'] = $sliderData['activo'] ?? true;
                    $sliderData['orden'] = $sliderData['orden'] ?? ($key + 1);
                    $sliderData['es_principal'] = $sliderData['es_principal'] ?? true;

                    // Limpiar el campo 'imagen' antes de enviarlo al repositorio
                    unset($sliderData['imagen']);
                    $processedSliders[] = $sliderData;
                }

                // 4. Actualizar el evento con los sliders procesados (o usar un método específico del repo)
                $this->eventoRepository->attachSliders($eventoId, $processedSliders);
            }

            return response()->json([
                'success' => true,
                'data' => $evento->fresh(), // Devolver el evento con los sliders cargados
                'message' => 'Evento creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el evento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un evento existente (con gestión de sliders/imágenes).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'nombre' => 'sometimes|string|max:255',
                'descripcion' => 'sometimes|string',
                'tipo_evento' => 'sometimes|string|max:100',
                'idioma_principal' => 'sometimes|string|max:50',
                'fecha_inicio' => 'sometimes|date',
                'hora_inicio' => 'sometimes',
                'fecha_fin' => 'sometimes|date',
                'hora_fin' => 'sometimes',
                'duracion_horas' => 'sometimes|integer',
                'coordenada_x' => 'sometimes|numeric',
                'coordenada_y' => 'sometimes|numeric',
                'id_emprendedor' => 'sometimes|exists:emprendedores,id',
                'que_llevar' => 'nullable|string',
                'sliders' => 'sometimes|array',
                'sliders.*.id' => 'sometimes|integer|exists:sliders,id',
                'sliders.*.url' => 'sometimes|nullable|string',
                'sliders.*.nombre' => 'sometimes|string|max:255',
                'sliders.*.orden' => 'sometimes|integer',
                'sliders.*.activo' => 'sometimes|boolean',
                'sliders.*.es_principal' => 'nullable',
                'sliders.*.imagen' => 'sometimes|file|image',
                'deleted_sliders' => 'sometimes|array',
                'deleted_sliders.*' => 'required|integer|exists:sliders,id',
            ]);

            $sliders = $validated['sliders'] ?? [];
            $deletedSlidersIds = $validated['deleted_sliders'] ?? [];

            $validatedSinSliders = collect($validated)->except(['sliders', 'deleted_sliders'])->all();

            // 1. Procesar los sliders
            if (!empty($sliders)) {
                $processedSliders = [];
                foreach ($sliders as $key => $slider) {
                    $sliderData = $slider;

                    // Si se sube una nueva imagen
                    if ($request->hasFile("sliders.{$key}.imagen")) {

                        // Si existe un slider ID, asumimos que estamos actualizando y eliminamos la imagen antigua
                        if (isset($slider['id']) && isset($slider['url'])) {
                            $this->deleteImage($slider['url']);
                        }

                        // Guardar la nueva imagen
                        $path = $this->storeImage($request->file("sliders.{$key}.imagen"), "eventos/{$id}");
                        $sliderData['url'] = $path;
                    }

                    // Asignar valores requeridos para los nuevos sliders
                    if (!isset($slider['id'])) {
                        $sliderData['tipo_entidad'] = 'evento';
                        $sliderData['entidad_id'] = $id;
                        $sliderData['activo'] = $sliderData['activo'] ?? true;
                        $sliderData['orden'] = $sliderData['orden'] ?? ($key + 1);
                        $sliderData['es_principal'] = $sliderData['es_principal'] ?? true;
                    }

                    // Limpiar el campo 'imagen'
                    unset($sliderData['imagen']);
                    $processedSliders[] = $sliderData;
                }
                $validatedSinSliders['sliders'] = $processedSliders;
            }

            // 2. Eliminar sliders
            if (!empty($deletedSlidersIds)) {
                $slidersAEliminar = $this->eventoRepository->getSlidersByIds($deletedSlidersIds);
                foreach ($slidersAEliminar as $slider) {
                    $this->deleteImage($slider->url); // Eliminar el archivo físico
                }
                // La eliminación del registro de la DB la maneja el repositorio en el update
                $validatedSinSliders['deleted_sliders'] = $deletedSlidersIds;
            }

            // 3. Actualizar el evento
            $evento = $this->eventoRepository->update($id, $validatedSinSliders);

            return response()->json([
                'success' => true,
                'data' => $evento,
                'message' => 'Evento actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el evento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un evento (y todos sus archivos).
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            // Cargar el evento para obtener la ruta de los sliders
            $evento = $this->eventoRepository->getById($id);

            if (!$evento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Evento no encontrado'
                ], 404);
            }

            $deleted = $this->eventoRepository->delete($id);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al eliminar el evento en la base de datos'
                ], 500);
            }

            // === Lógica de Gestión de Archivos (Añadida) ===
            // Borrar carpeta completa asociada al evento
            $folder = "eventos/{$id}";
            if (Storage::disk('media')->exists($folder)) {
                Storage::disk('media')->deleteDirectory($folder);
            }
            // ===============================================

            return response()->json([
                'success' => true,
                'message' => 'Evento eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el evento: ' . $e->getMessage()
            ], 500);
        }
    }

    // ---

    /**
     * Obtener eventos por emprendedor.
     */
    public function byEmprendedor(int $emprendedorId): JsonResponse
    {
        try {
            $eventos = $this->eventoRepository->getEventosByEmprendedor($emprendedorId);

            return response()->json([
                'success' => true,
                'data' => $eventos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los eventos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener eventos activos.
     */
    public function eventosActivos(): JsonResponse
    {
        try {
            $eventos = $this->eventoRepository->getEventosActivos();

            return response()->json([
                'success' => true,
                'data' => $eventos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los eventos activos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener próximos eventos.
     */
    public function proximosEventos(Request $request): JsonResponse
    {
        try {
            $limite = $request->query('limite', 5);
            $eventos = $this->eventoRepository->getProximosEventos($limite);

            return response()->json([
                'success' => true,
                'data' => $eventos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los próximos eventos: ' . $e->getMessage()
            ], 500);
        }
    }
}
