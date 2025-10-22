<?php

namespace Tests\Unit\Services;

use App\Models\Categoria;
use App\Models\Servicio;
use App\Repository\CategoriaRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CategoriaRepositoryTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected CategoriaRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new CategoriaRepository(new Categoria());
    }

    #[Test]
    public function puede_obtener_todas_las_categorias()
    {
        // Arrange
        Categoria::factory()->count(5)->create();

        // Act
        $result = $this->repository->getAll();

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(5, $result);
    }

    #[Test]
    public function puede_obtener_categorias_paginadas()
    {
        // Arrange
        Categoria::factory()->count(20)->create();

        // Act
        $result = $this->repository->getPaginated(10);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(10, $result->perPage());
        $this->assertEquals(20, $result->total());
        $this->assertCount(10, $result->items());
    }

    #[Test]
    public function puede_obtener_una_categoria_por_id()
    {
        // Arrange
        $categoria = Categoria::factory()->create();

        // Act
        $result = $this->repository->findById($categoria->id);

        // Assert
        $this->assertInstanceOf(Categoria::class, $result);
        $this->assertEquals($categoria->id, $result->id);
        $this->assertEquals($categoria->nombre, $result->nombre);
    }

    #[Test]
    public function retorna_null_cuando_categoria_no_existe()
    {
        // Act
        $result = $this->repository->findById(999);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function puede_obtener_categoria_con_servicios()
    {
        // Arrange
        $categoria = Categoria::factory()->create();

        // Act
        $result = $this->repository->findWithServicios($categoria->id);

        // Assert
        $this->assertInstanceOf(Categoria::class, $result);
        $this->assertTrue($result->relationLoaded('servicios'));
    }

    #[Test]
    public function retorna_null_al_obtener_categoria_con_servicios_inexistente()
    {
        // Act
        $result = $this->repository->findWithServicios(999);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function puede_crear_nueva_categoria()
    {
        // Arrange
        $data = [
            'nombre' => $this->faker->word,
            'descripcion' => $this->faker->text,
            'icono_url' => $this->faker->imageUrl()
        ];

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertInstanceOf(Categoria::class, $result);
        $this->assertEquals($data['nombre'], $result->nombre);
        $this->assertEquals($data['descripcion'], $result->descripcion);
        $this->assertEquals($data['icono_url'], $result->icono_url);
        $this->assertDatabaseHas('categorias', $data);
    }

    #[Test]
    public function puede_crear_categoria_con_datos_minimos()
    {
        // Arrange
        $data = [
            'nombre' => 'Categoría Mínima'
        ];

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertInstanceOf(Categoria::class, $result);
        $this->assertEquals($data['nombre'], $result->nombre);
        $this->assertNull($result->descripcion);
        $this->assertNull($result->icono_url);
        $this->assertDatabaseHas('categorias', [
            'nombre' => 'Categoría Mínima',
            'descripcion' => null,
            'icono_url' => null
        ]);
    }

    #[Test]
    public function puede_actualizar_categoria_existente()
    {
        // Arrange
        $categoria = Categoria::factory()->create();
        $data = [
            'nombre' => 'Nombre Actualizado',
            'descripcion' => 'Nueva descripción',
            'icono_url' => 'https://nuevo-icono.png'
        ];

        // Act
        $result = $this->repository->update($categoria->id, $data);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseHas('categorias', array_merge(['id' => $categoria->id], $data));
    }

    #[Test]
    public function puede_actualizar_campos_individuales()
    {
        // Arrange
        $categoria = Categoria::factory()->create([
            'nombre' => 'Nombre Original',
            'descripcion' => 'Descripción Original'
        ]);
        
        $data = [
            'nombre' => 'Nombre Actualizado'
            // Solo actualizar el nombre
        ];

        // Act
        $result = $this->repository->update($categoria->id, $data);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseHas('categorias', [
            'id' => $categoria->id,
            'nombre' => 'Nombre Actualizado',
            'descripcion' => 'Descripción Original' // Debe mantener el valor original
        ]);
    }

    #[Test]
    public function retorna_false_al_actualizar_categoria_inexistente()
    {
        // Arrange
        $data = [
            'nombre' => 'Test'
        ];

        // Act
        $result = $this->repository->update(999, $data);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function puede_eliminar_categoria_existente()
    {
        // Arrange
        $categoria = Categoria::factory()->create();

        // Act
        $result = $this->repository->delete($categoria->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('categorias', ['id' => $categoria->id]);
    }

    #[Test]
    public function retorna_false_al_eliminar_categoria_inexistente()
    {
        // Act
        $result = $this->repository->delete(999);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function puede_obtener_categoria_con_servicios_relacionados()
    {
        // Arrange
        $categoria = Categoria::factory()->create();
        $servicios = Servicio::factory()->count(3)->create();
        $categoria->servicios()->attach($servicios->pluck('id'));

        // Act
        $result = $this->repository->findWithServicios($categoria->id);

        // Assert
        $this->assertInstanceOf(Categoria::class, $result);
        $this->assertTrue($result->relationLoaded('servicios'));
        $this->assertCount(3, $result->servicios);
    }

    #[Test]
    public function metodo_getAll_no_carga_relaciones_por_defecto()
    {
        // Arrange
        $categoria = Categoria::factory()->create();
        $servicios = Servicio::factory()->count(2)->create();
        $categoria->servicios()->attach($servicios->pluck('id'));

        // Act
        $result = $this->repository->getAll();

        // Assert
        $this->assertFalse($result->first()->relationLoaded('servicios'));
    }

    #[Test]
    public function maneja_excepcion_en_creacion()
    {
        // Arrange
        $data = [
            'nombre' => null // Debe fallar por validación de base de datos
        ];

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->repository->create($data);
    }

    #[Test]
    public function puede_usar_paginacion_con_diferentes_tamaños()
    {
        // Arrange
        Categoria::factory()->count(25)->create();

        // Act
        $resultados5 = $this->repository->getPaginated(5);
        $resultados10 = $this->repository->getPaginated(10);
        $resultados15 = $this->repository->getPaginated(); // Default

        // Assert
        $this->assertEquals(5, $resultados5->perPage());
        $this->assertEquals(10, $resultados10->perPage());
        $this->assertEquals(15, $resultados15->perPage());
        
        $this->assertEquals(25, $resultados5->total());
        $this->assertEquals(25, $resultados10->total());
        $this->assertEquals(25, $resultados15->total());
    }

    #[Test]
    public function created_at_y_updated_at_se_manejan_automaticamente()
    {
        // Arrange
        $data = [
            'nombre' => 'Test Categoria'
        ];

        // Act
        $categoria = $this->repository->create($data);

        // Assert
        $this->assertNotNull($categoria->created_at);
        $this->assertNotNull($categoria->updated_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $categoria->created_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $categoria->updated_at);
    }

    #[Test]
    public function updated_at_se_actualiza_en_modificacion()
    {
        // Arrange
        $categoria = Categoria::factory()->create();
        $originalUpdatedAt = $categoria->updated_at;
        
        // Simular el paso del tiempo
        sleep(1);
        
        $data = [
            'nombre' => 'Nombre Modificado'
        ];

        // Act
        $this->repository->update($categoria->id, $data);

        // Assert
        $categoriaActualizada = $this->repository->findById($categoria->id);
        $this->assertNotEquals($originalUpdatedAt, $categoriaActualizada->updated_at);
    }
}