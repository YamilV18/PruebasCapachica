<?php
namespace App\Repository;

use App\Models\Evento;
use App\Models\Slider;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EventoRepository
{
    protected $model;
    protected $sliderRepository;

    public function __construct(Evento $evento, SliderRepository $sliderRepository = null)
    {
        $this->model = $evento;
        $this->sliderRepository = $sliderRepository ?: app(SliderRepository::class);
    }

    public function getAll(): Collection
    {
        return $this->model->with(['emprendedor', 'sliders'])->get();
    }

    public function getPaginated(int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->with(['emprendedor', 'sliders'])->paginate($perPage);
    }

    public function getById(int $id): ?Evento
    {
        return $this->model->with(['emprendedor', 'sliders'])->find($id);
    }

    public function create(array $data): Evento
    {
        try {
            DB::beginTransaction();
            
            // Extraer datos de sliders si existen
            $sliders = $data['sliders'] ?? [];
            
            // Eliminar datos de sliders del array principal
            unset($data['sliders']);
            
            $evento = $this->model->create($data);
            
            // Crear sliders si existen
            if (!empty($sliders)) {
                $this->sliderRepository->createMultiple('evento', $evento->id, $sliders);
            }
            
            DB::commit();
            return $evento->fresh(['emprendedor', 'sliders']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data): Evento
    {
        try {
            DB::beginTransaction();
            
            $evento = $this->getById($id);
            if (!$evento) {
                DB::rollBack();
                throw new \Exception('Evento no encontrado');
            }
            
            // Extraer datos de sliders si existen
            $sliders = $data['sliders'] ?? [];
            $deletedSliderIds = $data['deleted_sliders'] ?? [];
            
            // Eliminar datos de sliders del array principal
            unset($data['sliders']);
            unset($data['deleted_sliders']);
            
            $evento->update($data);
            
            // Eliminar sliders especificados
            if (!empty($deletedSliderIds)) {
                foreach ($deletedSliderIds as $sliderId) {
                    $this->sliderRepository->delete($sliderId);
                }
            }
            
            // Actualizar sliders si existen
            if (!empty($sliders)) {
                $this->sliderRepository->updateEntitySliders('evento', $evento->id, $sliders);
            }
            
            DB::commit();
            return $evento->fresh(['emprendedor', 'sliders']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        try {
            DB::beginTransaction();
            
            $evento = $this->getById($id);
            if (!$evento) {
                DB::rollBack();
                return false;
            }
            
            // Eliminar sliders asociados
            $evento->sliders->each(function ($slider) {
                app(SliderRepository::class)->delete($slider->id);
            });
            
            $deleted = $evento->delete();
            
            DB::commit();
            return $deleted;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getEventosByEmprendedor(int $emprendedorId): Collection
    {
        return $this->model->where('id_emprendedor', $emprendedorId)
            ->with(['sliders'])
            ->get();
    }

    public function getEventosActivos(): Collection
    {
        $fechaActual = now()->format('Y-m-d');
        
        return $this->model->where('fecha_fin', '>=', $fechaActual)
            ->with(['emprendedor', 'sliders'])
            ->orderBy('fecha_inicio')
            ->get();
    }

    public function getProximosEventos(int $limite = 5): Collection
    {
        $fechaActual = now()->format('Y-m-d');
        
        return $this->model->where('fecha_inicio', '>=', $fechaActual)
            ->with(['emprendedor', 'sliders'])
            ->orderBy('fecha_inicio')
            ->limit($limite)
            ->get();
    }
}