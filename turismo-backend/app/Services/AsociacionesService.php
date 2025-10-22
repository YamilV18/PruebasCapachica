<?php

namespace App\Services;

use App\Models\Asociacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Exception;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class AsociacionesService
{
    /**
     * Obtener todas las asociaciones paginadas
     */
    public function getAll(int $perPage = 15): LengthAwarePaginator
    {
        return Asociacion::with('municipalidad')->paginate($perPage);
    }

    /**
     * Obtener una asociación por su ID
     */
    public function getById(int $id): ?Asociacion
    {
        return Asociacion::with('municipalidad')->find($id);
    }

    /**
     * Obtener una asociación con sus emprendedores
     */
    public function getWithEmprendedores(int $id): ?Asociacion
    {
        return Asociacion::with('emprendedores')->find($id);
    }

    /**
     * Crear una nueva asociación
     */
    public function create(array $data, ?UploadedFile $imagen = null): Asociacion
    {
        try {
            DB::beginTransaction();

            // Eliminar la imagen del array de datos si está presente
            if (isset($data['imagen'])) {
                unset($data['imagen']);
            }

            $asociacion = new Asociacion();
            $asociacion->fill($data);
            
            // Procesar la imagen si se proporciona
            if ($imagen && $imagen->isValid()) {
                $asociacion->imagen = $imagen->store('asociaciones', 'public');
            }
            
            if (!$asociacion->save()) {
                throw new Exception('Error al guardar el registro en la base de datos');
            }
            
            DB::commit();
            return $asociacion;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Actualizar una asociación existente
     */
    public function update(int $id, array $data, ?UploadedFile $imagen = null): ?Asociacion
    {
        try {
            DB::beginTransaction();
            
            $asociacion = Asociacion::find($id);
            
            if (!$asociacion) {
                DB::rollBack();
                return null;
            }
            
            // Eliminar la imagen del array de datos si está presente
            if (isset($data['imagen'])) {
                unset($data['imagen']);
            }
            
            $asociacion->fill($data);
            
            // Procesar la imagen si se proporciona
            if ($imagen && $imagen->isValid()) {
                // Eliminar imagen anterior si existe
                if ($asociacion->imagen && !filter_var($asociacion->imagen, FILTER_VALIDATE_URL)) {
                    Storage::disk('public')->delete($asociacion->imagen);
                }
                
                $asociacion->imagen = $imagen->store('asociaciones', 'public');
            }
            
            if (!$asociacion->save()) {
                throw new Exception('Error al actualizar el registro en la base de datos');
            }
            
            DB::commit();
            return $asociacion;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Eliminar una asociación
     */
    public function delete(int $id): bool
    {
        try {
            DB::beginTransaction();
            
            $asociacion = $this->getById($id);
            
            if (!$asociacion) {
                DB::rollBack();
                return false;
            }
            
            // Eliminar imagen si existe y no es una URL externa
            if ($asociacion->imagen && !filter_var($asociacion->imagen, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($asociacion->imagen);
            }
            
            $deleted = $asociacion->delete();
            
            DB::commit();
            return $deleted;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Obtener asociaciones por municipalidad
     */
    public function getByMunicipalidad(int $municipalidadId): Collection
    {
        return Asociacion::where('municipalidad_id', $municipalidadId)->get();
    }
    
    /**
     * Obtener asociaciones cercanas por ubicación geográfica
     */
    public function getByUbicacion(float $latitud, float $longitud, float $distanciaKm = 10): Collection
    {
        // Fórmula haversine para cálculo de distancia
        $haversine = "(6371 * acos(cos(radians($latitud)) * cos(radians(latitud)) * cos(radians(longitud) - radians($longitud)) + sin(radians($latitud)) * sin(radians(latitud))))";
        
        return Asociacion::whereNotNull('latitud')
            ->whereNotNull('longitud')
            ->selectRaw("*, $haversine AS distancia")
            ->havingRaw("distancia < ?", [$distanciaKm])
            ->orderBy('distancia')
            ->get();
    }
}