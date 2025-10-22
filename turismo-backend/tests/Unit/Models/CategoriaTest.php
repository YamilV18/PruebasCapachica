<?php

namespace Tests\Unit\Models;

use App\Models\Categoria;
use App\Models\Servicio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoriaTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    #[Test]
    public function puede_crear_categoria_con_datos_validos()
    {
        // Arrange
        $data = [
            'nombre' => $this->faker->word,
            'descripcion' => $this->faker->text,
            'icono_url' => $this->faker->imageUrl()
        ];
        //hola

        // Act
        $categoria = Categoria::create($data);

        // Assert
        $this->assertInstanceOf(Categoria::class, $categoria);
        $this->assertEquals($data['nombre'], $categoria->nombre);
        $this->assertEquals($data['descripcion'], $categoria->descripcion);
        $this->assertEquals($data['icono_url'], $categoria->icono_url);
        $this->assertDatabaseHas('categorias', $data);
    }

    #[Test]
    public function fillable_permite_campos_correctos()
    {
        // Arrange
        $categoria = new Categoria();
        $data = [
            'nombre' => 'Test Categoria',
            'descripcion' => 'Test descripcion',
            'icono_url' => 'https://example.com/icon.png',
            'campo_no_permitido' => 'no debe ser asignado'
        ];

        // Act
        $categoria->fill($data);

        // Assert
        $this->assertEquals('Test Categoria', $categoria->nombre);
        $this->assertEquals('Test descripcion', $categoria->descripcion);
        $this->assertEquals('https://example.com/icon.png', $categoria->icono_url);
        $this->assertNull($categoria->campo_no_permitido);
    }

    #[Test]
    public function relacion_servicios_existe_y_es_many_to_many()
    {
        // Arrange
        $categoria = Categoria::factory()->create();

        // Act
        $serviciosRelation = $categoria->servicios();

        // Assert
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class, $serviciosRelation);
        $this->assertEquals('categoria_servicio', $serviciosRelation->getTable());
        $this->assertTrue($serviciosRelation->withTimestamps);
    }

    #[Test]
    public function puede_acceder_a_servicios_relacionados()
    {
        // Arrange
        $categoria = Categoria::factory()->create();

        // Act
        $servicios = $categoria->servicios;

        // Assert
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $servicios);
        $this->assertCount(0, $servicios); // Inicialmente vacía
    }

    #[Test]
    public function relacion_servicios_incluye_timestamps()
    {
        // Arrange
        $categoria = Categoria::factory()->create();

        // Act
        $relation = $categoria->servicios();

        // Assert
        $this->assertTrue($relation->withTimestamps);
    }

    #[Test]
    public function tabla_correcta_es_utilizada()
    {
        // Arrange
        $categoria = new Categoria();

        // Act
        $tabla = $categoria->getTable();

        // Assert
        $this->assertEquals('categorias', $tabla);
    }

    #[Test]
    public function primary_key_es_id_por_defecto()
    {
        // Arrange
        $categoria = new Categoria();

        // Act
        $primaryKey = $categoria->getKeyName();

        // Assert
        $this->assertEquals('id', $primaryKey);
    }

    #[Test]
    public function timestamps_estan_habilitados()
    {
        // Arrange
        $categoria = new Categoria();

        // Act
        $timestamps = $categoria->usesTimestamps();

        // Assert
        $this->assertTrue($timestamps);
    }

    #[Test]
    public function puede_actualizar_campos_individuales()
    {
        // Arrange
        $categoria = Categoria::factory()->create([
            'nombre' => 'Nombre Original'
        ]);

        // Act
        $categoria->update(['nombre' => 'Nombre Actualizado']);

        // Assert
        $this->assertEquals('Nombre Actualizado', $categoria->fresh()->nombre);
    }

    #[Test]
    public function puede_eliminar_categoria()
    {
        // Arrange
        $categoria = Categoria::factory()->create();
        $id = $categoria->id;

        // Act
        $result = $categoria->delete();

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('categorias', ['id' => $id]);
    }

    #[Test]
    public function eliminar_categoria_funciona_correctamente()
    {
        // Arrange
        $categoria = Categoria::factory()->create();
        $id = $categoria->id;

        // Act
        $categoria->delete();

        // Assert
        $this->assertDatabaseMissing('categorias', ['id' => $id]);
    }

    #[Test]
    public function puede_buscar_categorias_por_nombre()
    {
        // Arrange
        $categoria1 = Categoria::factory()->create(['nombre' => 'Tecnología']);
        $categoria2 = Categoria::factory()->create(['nombre' => 'Salud']);
        $categoria3 = Categoria::factory()->create(['nombre' => 'Educación']);

        // Act
        $resultado = Categoria::where('nombre', 'like', '%Tec%')->get();

        // Assert
        $this->assertCount(1, $resultado);
        $this->assertTrue($resultado->contains('id', $categoria1->id));
    }

    #[Test]
    public function maneja_valores_nulos_correctamente()
    {
        // Arrange & Act
        $categoria = Categoria::factory()->create([
            'nombre' => 'Test',
            'descripcion' => null,
            'icono_url' => null
        ]);

        // Assert
        $this->assertEquals('Test', $categoria->nombre);
        $this->assertNull($categoria->descripcion);
        $this->assertNull($categoria->icono_url);
    }

    #[Test]
    public function created_at_y_updated_at_se_establecen_automaticamente()
    {
        // Arrange & Act
        $categoria = Categoria::factory()->create();

        // Assert
        $this->assertNotNull($categoria->created_at);
        $this->assertNotNull($categoria->updated_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $categoria->created_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $categoria->updated_at);
    }

    #[Test]
    public function puede_obtener_cantidad_de_servicios_asociados()
    {
        // Arrange
        $categoria = Categoria::factory()->create();

        // Act
        $cantidadServicios = $categoria->servicios()->count();

        // Assert
        $this->assertEquals(0, $cantidadServicios); // Inicialmente no tiene servicios
    }

    #[Test]
    public function puede_verificar_si_tiene_servicios_asociados()
    {
        // Arrange
        $categoria = Categoria::factory()->create();

        // Act & Assert
        $this->assertFalse($categoria->servicios()->exists()); // No debe tener servicios inicialmente
    }

    #[Test]
    public function nombre_es_requerido_para_creacion()
    {
        // Arrange & Act & Assert
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        Categoria::factory()->create([
            'nombre' => null
        ]);
    }

    #[Test]
    public function puede_convertir_a_array()
    {
        // Arrange
        $categoria = Categoria::factory()->create([
            'nombre' => 'Test',
            'descripcion' => 'Test descripcion',
            'icono_url' => 'https://example.com/icon.png'
        ]);

        // Act
        $array = $categoria->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('nombre', $array);
        $this->assertArrayHasKey('descripcion', $array);
        $this->assertArrayHasKey('icono_url', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    #[Test]
    public function puede_convertir_a_json()
    {
        // Arrange
        $categoria = Categoria::factory()->create();

        // Act
        $json = $categoria->toJson();
        $data = json_decode($json, true);

        // Assert
        $this->assertIsString($json);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('nombre', $data);
    }
}