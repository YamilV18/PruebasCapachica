<?php

namespace Database\Seeders;

use App\Models\Asociacion;
use App\Models\Municipalidad;
use App\Models\Emprendedor;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    /**
     * Sembrar datos de prueba para tests
     */
    public function run(): void
    {
        // Crear municipalidades
        $municipalidades = Municipalidad::factory()->count(5)->create();

        // Crear asociaciones con diferentes estados
        foreach ($municipalidades as $municipalidad) {
            // Asociaciones activas
            Asociacion::factory()
                ->count(3)
                ->activa()
                ->create(['municipalidad_id' => $municipalidad->id]);

            // Asociaciones inactivas
            Asociacion::factory()
                ->count(1)
                ->inactiva()
                ->create(['municipalidad_id' => $municipalidad->id]);

            // Asociaciones con imagen
            Asociacion::factory()
                ->count(2)
                ->conImagen()
                ->create(['municipalidad_id' => $municipalidad->id]);
        }

        // Crear asociaciones con emprendedores
        $asociacionesConEmprendedores = Asociacion::factory()
            ->count(3)
            ->create();

        foreach ($asociacionesConEmprendedores as $asociacion) {
            Emprendedor::factory()
                ->count(rand(2, 5))
                ->create(['asociacion_id' => $asociacion->id]);
        }

        // Crear asociaciones en ubicaciones específicas para pruebas geográficas
        Asociacion::factory()
            ->count(3)
            ->enPuno()
            ->create();

        // Crear asociaciones sin coordenadas
        Asociacion::factory()
            ->count(2)
            ->sinCoordenadas()
            ->create();
    }
}