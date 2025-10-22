<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Configurar base de datos de prueba
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Configurar almacenamiento fake para tests con imágenes
        \Illuminate\Support\Facades\Storage::fake('public');
    }

    /**
     * Helper para crear datos de prueba básicos
     */
    protected function createBasicTestData(): array
    {
        $municipalidad = \App\Models\Municipalidad::factory()->create();
        
        $asociaciones = \App\Models\Asociacion::factory()
            ->count(3)
            ->create(['municipalidad_id' => $municipalidad->id]);

        return [
            'municipalidad' => $municipalidad,
            'asociaciones' => $asociaciones
        ];
    }

    /**
     * Helper para crear asociación con emprendedores
     */
    protected function createAsociacionWithEmprendedores(int $emprendedoresCount = 3): \App\Models\Asociacion
    {
        $asociacion = \App\Models\Asociacion::factory()->create();
        
        \App\Models\Emprendedor::factory()
            ->count($emprendedoresCount)
            ->create(['asociacion_id' => $asociacion->id]);

        return $asociacion;
    }

    /**
     * Helper para assertions de estructura JSON de asociación
     */
    protected function assertAsociacionJsonStructure(): array
    {
        return [
            'id',
            'nombre',
            'descripcion',
            'ubicacion',
            'latitud',
            'longitud',
            'telefono',
            'email',
            'municipalidad_id',
            'estado',
            'imagen',
            'imagen_url',
            'created_at',
            'updated_at'
        ];
    }

    /**
     * Helper para assertions de respuesta exitosa de API
     */
    protected function assertSuccessfulApiResponse($response, int $statusCode = 200): void
    {
        $response->assertStatus($statusCode)
                ->assertJson(['success' => true])
                ->assertJsonStructure(['success']);
    }

    /**
     * Helper para assertions de respuesta de error de API
     */
    protected function assertErrorApiResponse($response, int $statusCode = 400): void
    {
        $response->assertStatus($statusCode)
                ->assertJson(['success' => false])
                ->assertJsonStructure(['success', 'message']);
    }
}