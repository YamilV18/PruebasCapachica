<?php

namespace App\Http\Controllers\API\PageGeneral;

use App\Http\Controllers\Controller;
use App\Repository\MunicipalidadRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MunicipalidadController extends Controller
{
    protected $municipalidadRepository;

    public function __construct(MunicipalidadRepository $municipalidadRepository)
    {
        $this->municipalidadRepository = $municipalidadRepository;
    }

    public function index()
    {
        try {
            $municipalidades = $this->municipalidadRepository->getAll();

            // Si necesitas relaciones:
            $municipalidades->each(function ($municipalidad) {
                $municipalidad->load(['slidersPrincipales', 'slidersSecundarios']);
            });

            return response()->json([
                'success' => true,
                'data'    => $municipalidades,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Error al obtener municipalidades: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        try {
            $municipalidad = $this->municipalidadRepository->getById($id);

            $municipalidad->load(['slidersPrincipales', 'slidersSecundarios']);

            return response()->json([
                'success' => true,
                'data'    => $municipalidad,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Municipalidad no encontrada: ' . $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre'       => 'required|string|max:255',
                'descripcion'  => 'required|string',
                'red_facebook' => 'nullable|string|max:255',
                'red_instagram'=> 'nullable|string|max:255',
                'red_youtube'  => 'nullable|string|max:255',
                'coordenadas_x'=> 'nullable|numeric',
                'coordenadas_y'=> 'nullable|numeric',
                'frase'        => 'nullable|string',
                'comunidades'  => 'nullable|string',
                'historiafamilias'   => 'nullable|string',
                'historiacapachica'  => 'nullable|string',
                'comite'       => 'nullable|string',
                'mision'       => 'nullable|string',
                'vision'       => 'nullable|string',
                'valores'      => 'nullable|string',
                'ordenanzamunicipal' => 'nullable|string',
                'alianzas'     => 'nullable|string',
                'correo'       => 'nullable|string|email',
                'horariodeatencion' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors'  => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $municipalidad = $this->municipalidadRepository->create($request->all());

            return response()->json([
                'success' => true,
                'data'    => $municipalidad,
                'message' => 'Municipalidad creada con éxito',
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::error('Error al crear municipalidad: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre'       => 'sometimes|string|max:255',
                'descripcion'  => 'sometimes|string',
                'red_facebook' => 'nullable|string|max:255',
                'red_instagram'=> 'nullable|string|max:255',
                'red_youtube'  => 'nullable|string|max:255',
                'coordenadas_x'=> 'nullable|numeric',
                'coordenadas_y'=> 'nullable|numeric',
                'frase'        => 'nullable|string',
                'comunidades'  => 'nullable|string',
                'historiafamilias'   => 'nullable|string',
                'historiacapachica'  => 'nullable|string',
                'comite'       => 'nullable|string',
                'mision'       => 'nullable|string',
                'vision'       => 'nullable|string',
                'valores'      => 'nullable|string',
                'ordenanzamunicipal' => 'nullable|string',
                'alianzas'     => 'nullable|string',
                'correo'       => 'nullable|string|email',
                'horariodeatencion' => 'nullable|string',
                'deleted_sliders'   => 'nullable|array',
                'deleted_sliders.*' => 'numeric|exists:sliders,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors'  => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $municipalidad = $this->municipalidadRepository->update($id, $request->all());

            return response()->json([
                'success' => true,
                'data'    => $municipalidad,
                'message' => 'Municipalidad actualizada con éxito',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Error al actualizar municipalidad: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar municipalidad: ' . $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    public function destroy($id)
    {
        try {
            $this->municipalidadRepository->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Municipalidad eliminada con éxito',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Error al eliminar municipalidad: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar municipalidad: ' . $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    public function getWithRelations($id)
    {
        try {
            $municipalidad = $this->municipalidadRepository->getWithRelations($id);

            return response()->json([
                'success' => true,
                'data'    => $municipalidad,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Error al obtener municipalidad con relaciones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener municipalidad: ' . $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    public function getWithAsociaciones($id)
    {
        try {
            $municipalidad = $this->municipalidadRepository->getWithAsociacionesBasic($id);

            return response()->json([
                'success' => true,
                'data'    => $municipalidad,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Error al obtener municipalidad con asociaciones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener municipalidad: ' . $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    public function getWithAsociacionesAndEmprendedores($id)
    {
        try {
            $municipalidad = $this->municipalidadRepository->getWithAsociacionesAndEmprendedores($id);

            return response()->json([
                'success' => true,
                'data'    => $municipalidad,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Error al obtener municipalidad completa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos completos: ' . $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }
    }
}
