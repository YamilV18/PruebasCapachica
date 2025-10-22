<?php

namespace App\Http\Controllers\API\Asociaciones;

use App\Http\Controllers\Controller;
use App\Services\AsociacionesService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AsociacionController extends Controller
{
    protected $asociacionService;

    public function __construct(AsociacionesService $asociacionService)
    {
        $this->asociacionService = $asociacionService;
    }

    /**
     * Mostrar todas las asociaciones
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 15);
        $asociaciones = $this->asociacionService->getAll($perPage);

        return response()->json([
            'success' => true,
            'data' => $asociaciones
        ]);
    }

    /**
     * Mostrar una asociación específica
     */
    public function show(int $id)
    {
        $asociacion = $this->asociacionService->getById($id);

        if (!$asociacion) {
            return response()->json([
                'success' => false,
                'message' => 'Asociación no encontrada'
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => $asociacion
        ]);
    }

    /**
     * Almacenar una nueva asociación
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'latitud' => 'nullable|numeric',
                'longitud' => 'nullable|numeric',
                'telefono' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'municipalidad_id' => 'required|exists:municipalidad,id',
                'estado' => 'boolean',
                'imagen' => 'nullable|image|max:2048', // 2MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $asociacion = $this->asociacionService->create(
                $request->except('imagen'),
                $request->hasFile('imagen') ? $request->file('imagen') : null
            );

            return response()->json([
                'success' => true,
                'message' => 'Asociación creada exitosamente',
                'data' => $asociacion
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::error('Error al crear asociación: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Actualizar una asociación
     */
    public function update(Request $request, int $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'sometimes|required|string|max:255',
                'descripcion' => 'nullable|string',
                'latitud' => 'nullable|numeric',
                'longitud' => 'nullable|numeric',
                'telefono' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'municipalidad_id' => 'sometimes|required|exists:municipalidad,id',
                'estado' => 'required|in:0,1,true,false',
                'imagen' => 'nullable|image|max:2048', // 2MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $asociacion = $this->asociacionService->update(
                $id,
                $request->except('imagen'),
                $request->hasFile('imagen') ? $request->file('imagen') : null
            );

            if (!$asociacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Asociación no encontrada'
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'message' => 'Asociación actualizada exitosamente',
                'data' => $asociacion
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar asociación: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Eliminar una asociación
     */
    public function destroy(int $id)
    {
        try {
            $deleted = $this->asociacionService->delete($id);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Asociación no encontrada'
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'message' => 'Asociación eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar asociación: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener emprendedores de una asociación
     */
    public function getEmprendedores(int $id)
    {
        try {
            $asociacion = $this->asociacionService->getWithEmprendedores($id);

            if (!$asociacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Asociación no encontrada'
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'data' => $asociacion->emprendedores
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener emprendedores: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener asociaciones por municipalidad
     */
    public function getByMunicipalidad(int $municipalidadId)
    {
        try {
            $asociaciones = $this->asociacionService->getByMunicipalidad($municipalidadId);

            return response()->json([
                'success' => true,
                'data' => $asociaciones
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener asociaciones por municipalidad: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Obtener asociaciones por ubicación geográfica
     */
    public function getByUbicacion(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'latitud' => 'required|numeric',
                'longitud' => 'required|numeric',
                'distancia' => 'nullable|numeric|min:0.1|max:100',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            
            $latitud = $request->latitud;
            $longitud = $request->longitud;
            $distancia = $request->distancia ?? 10;
            
            $asociaciones = $this->asociacionService->getByUbicacion($latitud, $longitud, $distancia);
            
            return response()->json([
                'success' => true,
                'data' => $asociaciones
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener asociaciones por ubicación: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}