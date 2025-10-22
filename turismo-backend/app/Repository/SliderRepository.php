<?php

namespace App\Repository;

use App\Models\Slider;
use App\Models\SliderDescripcion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;

class SliderRepository
{
    protected $model;
    protected $descripcionModel;

    public function __construct(Slider $slider, SliderDescripcion $descripcion)
    {
        $this->model = $slider;
        $this->descripcionModel = $descripcion;
    }

    public function getAll(array $params = []): Collection
    {
        $query = $this->model->query();
        
        if (isset($params['tipo_entidad'])) {
            $query->where('tipo_entidad', $params['tipo_entidad']);
        }
        
        if (isset($params['entidad_id'])) {
            $query->where('entidad_id', $params['entidad_id']);
        }
        
        if (isset($params['es_principal'])) {
            $query->where('es_principal', $params['es_principal']);
        }
        
        if (isset($params['with_descripcion']) && $params['with_descripcion']) {
            $query->with('descripcion');
        }
        
        $query->orderBy('orden', 'asc')->orderBy('id', 'asc');
        
        return $query->get();
    }

    public function getPaginated(int $perPage = 15, array $params = []): LengthAwarePaginator
    {
        $query = $this->model->query();
        
        if (isset($params['tipo_entidad'])) {
            $query->where('tipo_entidad', $params['tipo_entidad']);
        }
        
        if (isset($params['entidad_id'])) {
            $query->where('entidad_id', $params['entidad_id']);
        }
        
        if (isset($params['es_principal'])) {
            $query->where('es_principal', $params['es_principal']);
        }
        
        if (isset($params['with_descripcion']) && $params['with_descripcion']) {
            $query->with('descripcion');
        }
        
        $query->orderBy('orden', 'asc')->orderBy('id', 'asc');
        
        return $query->paginate($perPage);
    }

    public function findById(int $id, bool $withDescripcion = false): ?Slider
    {
        $query = $this->model->newQuery();
        
        if ($withDescripcion) {
            $query->with('descripcion');
        }
        
        return $query->find($id);
    }

    public function create(array $data, ?array $descripcionData = null): Slider
    {
        try {
            DB::beginTransaction();
            
            // Manejo del archivo de imagen
            if (isset($data['imagen']) && $data['imagen']) {
                $path = $this->storeImage($data['imagen'], $data['tipo_entidad']);
                $data['url'] = $path;
                unset($data['imagen']);
            }
            
            $slider = $this->model->create($data);
            
            // Si hay datos de descripción y no es un slider principal
            if (!$data['es_principal'] && $descripcionData) {
                $descripcionData['slider_id'] = $slider->id;
                $this->descripcionModel->create($descripcionData);
            }
            
            DB::commit();
            return $slider->fresh(['descripcion']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data, ?array $descripcionData = null): ?Slider
    {
        try {
            DB::beginTransaction();
            
            $slider = $this->findById($id, true);
            
            if (!$slider) {
                DB::rollBack();
                return null;
            }
            
            // Manejo del archivo de imagen si se proporciona una nueva
            if (isset($data['imagen']) && $data['imagen']) {
                // Eliminar imagen anterior si existe
                if ($slider->url && !filter_var($slider->url, FILTER_VALIDATE_URL)) {
                    Storage::delete($slider->url);
                }
                
                $path = $this->storeImage($data['imagen'], $data['tipo_entidad'] ?? $slider->tipo_entidad);
                $data['url'] = $path;
                unset($data['imagen']);
            }
            
            $slider->update($data);
            
            // Actualizar o crear descripción si se proporciona
            if ($descripcionData) {
                if ($slider->descripcion) {
                    $slider->descripcion->update($descripcionData);
                } else {
                    $descripcionData['slider_id'] = $slider->id;
                    $this->descripcionModel->create($descripcionData);
                }
            }
            
            DB::commit();
            return $slider->fresh(['descripcion']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        try {
            DB::beginTransaction();
            
            $slider = $this->findById($id);
            
            if (!$slider) {
                DB::rollBack();
                return false;
            }
            
            // Eliminar archivo de imagen si no es una URL externa
            if ($slider->url && !filter_var($slider->url, FILTER_VALIDATE_URL)) {
                Storage::delete($slider->url);
            }
            
            $slider->delete();
            
            DB::commit();
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // Crear múltiples sliders para una entidad
    public function createMultiple(string $tipoEntidad, int $entidadId, array $slidersData): Collection
    {
        $createdSliders = collect();
        
        try {
            DB::beginTransaction();
            
            foreach ($slidersData as $sliderData) {
                $sliderData['tipo_entidad'] = $tipoEntidad;
                $sliderData['entidad_id'] = $entidadId;
                
                $descripcionData = null;
                if (!$sliderData['es_principal'] && isset($sliderData['descripcion'])) {
                    $descripcionData = [
                        'titulo' => $sliderData['titulo'] ?? null,
                        'descripcion' => $sliderData['descripcion']
                    ];
                    
                    unset($sliderData['titulo']);
                    unset($sliderData['descripcion']);
                }
                
                $slider = $this->create($sliderData, $descripcionData);
                $createdSliders->push($slider);
            }
            
            DB::commit();
            return $createdSliders;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // Actualizar sliders de una entidad
    public function updateEntitySliders(string $tipoEntidad, int $entidadId, array $slidersData): Collection
    {
        $updatedSliders = collect();
        
        try {
            DB::beginTransaction();
            
            $existingIds = collect();
            
            foreach ($slidersData as $sliderData) {
                // Si tiene ID, actualizamos
                if (isset($sliderData['id'])) {
                    $existingIds->push($sliderData['id']);
                    
                    $descripcionData = null;
                    if (!$sliderData['es_principal'] && isset($sliderData['descripcion'])) {
                        $descripcionData = [
                            'titulo' => $sliderData['titulo'] ?? null,
                            'descripcion' => $sliderData['descripcion']
                        ];
                        
                        unset($sliderData['titulo']);
                        unset($sliderData['descripcion']);
                    }
                    
                    $slider = $this->update($sliderData['id'], $sliderData, $descripcionData);
                    $updatedSliders->push($slider);
                } else {
                    // Si no tiene ID, creamos nuevo
                    $sliderData['tipo_entidad'] = $tipoEntidad;
                    $sliderData['entidad_id'] = $entidadId;
                    
                    $descripcionData = null;
                    if (!$sliderData['es_principal'] && isset($sliderData['descripcion'])) {
                        $descripcionData = [
                            'titulo' => $sliderData['titulo'] ?? null,
                            'descripcion' => $sliderData['descripcion']
                        ];
                        
                        unset($sliderData['titulo']);
                        unset($sliderData['descripcion']);
                    }
                    
                    $slider = $this->create($sliderData, $descripcionData);
                    $updatedSliders->push($slider);
                    $existingIds->push($slider->id);
                }
            }
            
            // Eliminar sliders que no estén en la lista actualizada
            if (isset($slidersData['eliminar_no_incluidos']) && $slidersData['eliminar_no_incluidos']) {
                $this->model->where('tipo_entidad', $tipoEntidad)
                        ->where('entidad_id', $entidadId)
                        ->whereNotIn('id', $existingIds)
                        ->get()
                        ->each(function ($slider) {
                            $this->delete($slider->id);
                        });
            }
            
            DB::commit();
            return $updatedSliders;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function storeImage($imagen, string $tipoEntidad): string
    {
        // Verificar si $imagen es un string o un objeto UploadedFile
        if (is_string($imagen)) {
            // Si es un string, asumimos que es una URL o ruta existente
            // Simplemente devolvemos el string tal cual
            return $imagen;
        }
        
        // Si es un objeto UploadedFile, guardamos la imagen
        $carpeta = 'sliders/' . $tipoEntidad;
        return $imagen->store($carpeta, 'public');
    }

}