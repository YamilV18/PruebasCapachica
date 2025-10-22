<?php
namespace App\Repository;

use App\Models\Municipalidad;
use Illuminate\Support\Facades\DB;

class MunicipalidadRepository
{
    protected $model;
    protected $sliderRepository;

    public function __construct(Municipalidad $municipalidad, SliderRepository $sliderRepository = null)
    {
        $this->model = $municipalidad;
        $this->sliderRepository = $sliderRepository ?: app(SliderRepository::class);
    }

    public function getAll()
    {
        return $this->model->with(['slidersPrincipales', 'slidersSecundarios'])->get();
    }

    public function getById($id)
    {
        return $this->model->with(['slidersPrincipales', 'slidersSecundarios'])->findOrFail($id);
    }

    public function create(array $data)
    {
        try {
            DB::beginTransaction();
            
            // Extraer datos de sliders si existen
            $slidersPrincipales = $data['sliders_principales'] ?? [];
            $slidersSecundarios = $data['sliders_secundarios'] ?? [];
            
            // Eliminar datos de sliders del array principal
            unset($data['sliders_principales']);
            unset($data['sliders_secundarios']);
            
            // Crear municipalidad
            $municipalidad = $this->model->create($data);
            
            // Crear sliders principales si existen
            if (!empty($slidersPrincipales)) {
                foreach ($slidersPrincipales as &$slider) {
                    $slider['es_principal'] = true;
                }
                $this->sliderRepository->createMultiple('municipalidad', $municipalidad->id, $slidersPrincipales);
            }
            
            // Crear sliders secundarios si existen
            if (!empty($slidersSecundarios)) {
                foreach ($slidersSecundarios as &$slider) {
                    $slider['es_principal'] = false;
                }
                $this->sliderRepository->createMultiple('municipalidad', $municipalidad->id, $slidersSecundarios);
            }
            
            DB::commit();
            return $municipalidad->fresh(['slidersPrincipales', 'slidersSecundarios']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function update($id, array $data)
    {
        try {
            DB::beginTransaction();
            
            $municipalidad = $this->getById($id);
            
            // Extraer datos de sliders
            $slidersPrincipales = $data['sliders_principales'] ?? [];
            $slidersSecundarios = $data['sliders_secundarios'] ?? [];
            $deletedSliderIds = $data['deleted_sliders'] ?? [];
            
            // Eliminar datos de sliders del array principal
            unset($data['sliders_principales']);
            unset($data['sliders_secundarios']);
            unset($data['deleted_sliders']);
            
            // Actualizar municipalidad
            $municipalidad->update($data);
            
            // Eliminar sliders marcados para eliminación
            if (!empty($deletedSliderIds)) {
                foreach ($deletedSliderIds as $sliderId) {
                    $this->sliderRepository->delete((int)$sliderId);
                }
            }
            
            // Actualizar sliders principales si existen
            if (!empty($slidersPrincipales)) {
                foreach ($slidersPrincipales as &$slider) {
                    $slider['es_principal'] = true;
                }
                $this->sliderRepository->updateEntitySliders('municipalidad', $municipalidad->id, $slidersPrincipales);
            }
            
            // Actualizar sliders secundarios si existen
            if (!empty($slidersSecundarios)) {
                foreach ($slidersSecundarios as &$slider) {
                    $slider['es_principal'] = false;
                }
                $this->sliderRepository->updateEntitySliders('municipalidad', $municipalidad->id, $slidersSecundarios);
            }
            
            DB::commit();
            return $municipalidad->fresh(['slidersPrincipales', 'slidersSecundarios']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function delete($id)
    {
        $municipalidad = $this->getById($id);
        
        // Al eliminar la municipalidad, debemos eliminar también sus sliders
        // Esto se puede hacer con eventos de modelo o manualmente aquí
        $municipalidad->sliders->each(function ($slider) {
            app(SliderRepository::class)->delete($slider->id);
        });
        
        return $municipalidad->delete();
    }

    public function getWithRelations($id)
    {
        return $this->model->with([
            'slidersPrincipales', 
            'slidersSecundarios',
            'descripcionesMunicipalidad',
            'sobreNosotros',
            'contactos'
        ])->findOrFail($id);
    }

    public function getWithAsociaciones($id)
    {
        return $this->model->with([
            'asociaciones',
            'slidersPrincipales', 
            'slidersSecundarios'
        ])->findOrFail($id);
    }

    public function getWithAsociacionesAndEmprendedores($id)
    {
        return $this->model->with([
            'asociaciones.emprendedores',
            'slidersPrincipales', 
            'slidersSecundarios'
        ])->findOrFail($id);
    }

    public function getWithAsociacionesBasic($id)
    {
        return $this->model->with([
            'asociaciones' => function($query) {
                $query->select('id', 'nombre', 'descripcion', 'municipalidad_id');
            },
            'slidersPrincipales', 
            'slidersSecundarios'
        ])->findOrFail($id);
    }
}