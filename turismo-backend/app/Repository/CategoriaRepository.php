<?php

namespace App\Repository;

use App\Models\Categoria;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoriaRepository
{
    protected $model;

    public function __construct(Categoria $categoria)
    {
        $this->model = $categoria;
    }

    public function getAll(): Collection
    {
        return $this->model->all();
    }

    public function getPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->paginate($perPage);
    }

    public function findById(int $id): ?Categoria
    {
        return $this->model->find($id);
    }

    public function create(array $data): Categoria
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): bool
    {
        $categoria = $this->findById($id);
        if (!$categoria) {
            return false;
        }
        
        return $categoria->update($data);
    }

    public function delete(int $id): bool
    {
        $categoria = $this->findById($id);
        if (!$categoria) {
            return false;
        }
        
        return $categoria->delete();
    }

    public function findWithServicios(int $id): ?Categoria
    {
        return $this->model->with('servicios')->find($id);
    }
}