<?php

namespace Tests\Unit\Services;

use App\Models\Asociacion;
use App\Models\Municipalidad;
use App\Models\Emprendedor;
use App\Services\AsociacionesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Mockery;

class AsociacionesServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected AsociacionesService $service;
    protected Municipalidad $municipalidad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AsociacionesService();
        
        // Crear municipalidad de prueba
        $this->municipalidad = Municipalidad::factory()->create();
    }

    #[Test]
    public function puede_obtener_todas_las_asociaciones_paginadas()
    {
        // Arrange
        Asociacion::factory()->count(20)->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);

        // Act
        $result = $this->service->getAll(10);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(10, $result->perPage());
        $this->assertEquals(20, $result->total());
        $this->assertCount(10, $result->items());
    }

    #[Test]
    public function puede_obtener_una_asociacion_por_id()
    {
        // Arrange
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);

        // Act
        $result = $this->service->getById($asociacion->id);

        // Assert
        $this->assertInstanceOf(Asociacion::class, $result);
        $this->assertEquals($asociacion->id, $result->id);
        $this->assertTrue($result->relationLoaded('municipalidad'));
    }

    #[Test]
    public function retorna_null_cuando_asociacion_no_existe()
    {
        // Act
        $result = $this->service->getById(999);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function puede_obtener_asociacion_con_emprendedores()
    {
        // Arrange
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);
        
        Emprendedor::factory()->count(3)->create([
            'asociacion_id' => $asociacion->id
        ]);

        // Act
        $result = $this->service->getWithEmprendedores($asociacion->id);

        // Assert
        $this->assertInstanceOf(Asociacion::class, $result);
        $this->assertTrue($result->relationLoaded('emprendedores'));
        $this->assertCount(3, $result->emprendedores);
    }

    #[Test]
    public function puede_crear_nueva_asociacion_sin_imagen()
    {
        // Arrange
        $data = [
            'nombre' => $this->faker->company,
            'descripcion' => $this->faker->text,
            'latitud' => $this->faker->latitude,
            'longitud' => $this->faker->longitude,
            'telefono' => $this->faker->phoneNumber,
            'email' => $this->faker->email,
            'municipalidad_id' => $this->municipalidad->id,
            'estado' => true
        ];

        // Act
        $result = $this->service->create($data);

        // Assert
        $this->assertInstanceOf(Asociacion::class, $result);
        $this->assertEquals($data['nombre'], $result->nombre);
        $this->assertEquals($data['email'], $result->email);
        $this->assertDatabaseHas('asociaciones', [
            'id' => $result->id,
            'nombre' => $data['nombre']
        ]);
    }

    #[Test]
    public function puede_actualizar_asociacion_existente()
    {
        // Arrange
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);
        
        $updateData = [
            'nombre' => 'Nombre Actualizado',
            'descripcion' => 'Descripción actualizada',
            'estado' => false
        ];

        // Act
        $result = $this->service->update($asociacion->id, $updateData);

        // Assert
        $this->assertInstanceOf(Asociacion::class, $result);
        $this->assertEquals('Nombre Actualizado', $result->nombre);
        $this->assertEquals('Descripción actualizada', $result->descripcion);
        $this->assertFalse($result->estado);
        
        $this->assertDatabaseHas('asociaciones', [
            'id' => $asociacion->id,
            'nombre' => 'Nombre Actualizado'
        ]);
    }

    #[Test]
    public function retorna_null_al_actualizar_asociacion_inexistente()
    {
        // Arrange
        $updateData = ['nombre' => 'Test'];

        // Act
        $result = $this->service->update(999, $updateData);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function puede_eliminar_asociacion_existente()
    {
        // Arrange
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);

        // Act
        $result = $this->service->delete($asociacion->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('asociaciones', [
            'id' => $asociacion->id
        ]);
    }

    #[Test]
    public function retorna_false_al_eliminar_asociacion_inexistente()
    {
        // Act
        $result = $this->service->delete(999);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function puede_obtener_asociaciones_por_municipalidad()
    {
        // Arrange
        $otraMunicipalidad = Municipalidad::factory()->create();
        
        Asociacion::factory()->count(3)->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);
        
        Asociacion::factory()->count(2)->create([
            'municipalidad_id' => $otraMunicipalidad->id
        ]);

        // Act
        $result = $this->service->getByMunicipalidad($this->municipalidad->id);

        // Assert
        $this->assertCount(3, $result);
        foreach ($result as $asociacion) {
            $this->assertEquals($this->municipalidad->id, $asociacion->municipalidad_id);
        }
    }


    

    #[Test]
    public function maneja_excepcion_en_creacion()
    {
        // Arrange
        $data = [
            'nombre' => $this->faker->company,
            'municipalidad_id' => 999999 // ID inexistente para forzar error
        ];

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->service->create($data);
    }
}