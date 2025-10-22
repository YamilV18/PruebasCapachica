<?php

namespace Tests\Unit\Models;

use App\Models\Asociacion;
use App\Models\Municipalidad;
use App\Models\Emprendedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AsociacionTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected Municipalidad $municipalidad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->municipalidad = Municipalidad::factory()->create();
    }

    #[Test]
    public function puede_crear_asociacion_con_datos_validos()
    {
        // Arrange
        $data = [
            'nombre' => $this->faker->company,
            'descripcion' => $this->faker->text,
            'telefono' => $this->faker->phoneNumber,
            'email' => $this->faker->email,
            'municipalidad_id' => $this->municipalidad->id,
            'estado' => true,
            'imagen' => 'test-image.jpg'
        ];

        // Act
        $asociacion = Asociacion::create($data);

        // Assert
        $this->assertInstanceOf(Asociacion::class, $asociacion);
        $this->assertEquals($data['nombre'], $asociacion->nombre);
        $this->assertEquals($data['email'], $asociacion->email);
        $this->assertTrue($asociacion->estado);
        $this->assertDatabaseHas('asociaciones', $data);
    }

    #[Test]
    public function fillable_permite_campos_correctos()
    {
        // Arrange
        $asociacion = new Asociacion();
        $data = [
            'nombre' => 'Test Asociacion',
            'descripcion' => 'Test descripcion',
            'telefono' => '123456789',
            'email' => 'test@test.com',
            'municipalidad_id' => $this->municipalidad->id,
            'estado' => true,
            'imagen' => 'test.jpg',
            'campo_no_permitido' => 'no debe ser asignado'
        ];

        // Act
        $asociacion->fill($data);

        // Assert
        $this->assertEquals('Test Asociacion', $asociacion->nombre);
        $this->assertEquals('test@test.com', $asociacion->email);
        $this->assertNull($asociacion->campo_no_permitido);
    }

    #[Test]
    public function casts_convierte_tipos_correctamente()
    {
        // Arrange & Act
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id,
            'estado' => '1', // String
            'latitud' => '12.345', // String
            'longitud' => '-67.890' // String
        ]);

        // Assert
        $this->assertIsBool($asociacion->estado);
        $this->assertTrue($asociacion->estado);
        $this->assertIsFloat($asociacion->latitud);
        $this->assertIsFloat($asociacion->longitud);
        $this->assertEquals(12.345, $asociacion->latitud);
        $this->assertEquals(-67.890, $asociacion->longitud);
    }

    #[Test]
    public function relacion_municipalidad_funciona_correctamente()
    {
        // Arrange
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);

        // Act
        $municipalidadRelacionada = $asociacion->municipalidad;

        // Assert
        $this->assertInstanceOf(Municipalidad::class, $municipalidadRelacionada);
        $this->assertEquals($this->municipalidad->id, $municipalidadRelacionada->id);
        $this->assertEquals($this->municipalidad->nombre, $municipalidadRelacionada->nombre);
    }

    #[Test]
    public function relacion_emprendedores_funciona_correctamente()
    {
        // Arrange
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);

        $emprendedores = Emprendedor::factory()->count(3)->create([
            'asociacion_id' => $asociacion->id
        ]);

        // Act
        $emprendedoresRelacionados = $asociacion->emprendedores;

        // Assert
        $this->assertCount(3, $emprendedoresRelacionados);
        foreach ($emprendedores as $emprendedor) {
            $this->assertTrue(
                $emprendedoresRelacionados->contains('id', $emprendedor->id)
            );
        }
    }

    #[Test]
    public function imagen_url_attribute_retorna_null_cuando_no_hay_imagen()
    {
        // Arrange
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id,
            'imagen' => null
        ]);

        // Act
        $imagenUrl = $asociacion->imagen_url;

        // Assert
        $this->assertNull($imagenUrl);
    }

    #[Test]
    public function imagen_url_attribute_retorna_url_completa_si_es_url_externa()
    {
        // Arrange
        $urlExterna = 'https://example.com/imagen.jpg';
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id,
            'imagen' => $urlExterna
        ]);

        // Act
        $imagenUrl = $asociacion->imagen_url;

        // Assert
        $this->assertEquals($urlExterna, $imagenUrl);
    }

    #[Test]
    public function appends_incluye_imagen_url_en_array()
    {
        // Arrange
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id,
            'imagen' => 'test.jpg'
        ]);

        // Act
        $array = $asociacion->toArray();

        // Assert
        $this->assertArrayHasKey('imagen_url', $array);
    }

    #[Test]
    public function appends_incluye_imagen_url_en_json()
    {
        // Arrange
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id,
            'imagen' => 'test.jpg'
        ]);

        // Act
        $json = $asociacion->toJson();
        $data = json_decode($json, true);

        // Assert
        $this->assertArrayHasKey('imagen_url', $data);
    }

    #[Test]
    public function tabla_correcta_es_utilizada()
    {
        // Arrange
        $asociacion = new Asociacion();

        // Act
        $tabla = $asociacion->getTable();

        // Assert
        $this->assertEquals('asociaciones', $tabla);
    }

    #[Test]
    public function primary_key_es_id_por_defecto()
    {
        // Arrange
        $asociacion = new Asociacion();

        // Act
        $primaryKey = $asociacion->getKeyName();

        // Assert
        $this->assertEquals('id', $primaryKey);
    }

    #[Test]
    public function timestamps_estan_habilitados()
    {
        // Arrange
        $asociacion = new Asociacion();

        // Act
        $timestamps = $asociacion->usesTimestamps();

        // Assert
        $this->assertTrue($timestamps);
    }

    #[Test]
    public function puede_actualizar_campos_individuales()
    {
        // Arrange
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id,
            'nombre' => 'Nombre Original'
        ]);

        // Act
        $asociacion->update(['nombre' => 'Nombre Actualizado']);

        // Assert
        $this->assertEquals('Nombre Actualizado', $asociacion->fresh()->nombre);
    }

    #[Test]
    public function puede_eliminar_asociacion()
    {
        // Arrange
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);
        $id = $asociacion->id;

        // Act
        $result = $asociacion->delete();

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('asociaciones', ['id' => $id]);
    }

    #[Test]
    public function scope_queries_funcionan_correctamente()
    {
        // Arrange
        $asociacionActiva = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id,
            'estado' => true
        ]);

        $asociacionInactiva = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id,
            'estado' => false
        ]);

        // Act
        $asociacionesActivas = Asociacion::where('estado', true)->get();
        $asociacionesInactivas = Asociacion::where('estado', false)->get();

        // Assert
        $this->assertCount(1, $asociacionesActivas);
        $this->assertCount(1, $asociacionesInactivas);
        $this->assertTrue($asociacionesActivas->contains('id', $asociacionActiva->id));
        $this->assertTrue($asociacionesInactivas->contains('id', $asociacionInactiva->id));
    }

    #[Test]
    public function maneja_valores_nulos_correctamente()
    {
        // Arrange & Act
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id,
            'descripcion' => null,
            'telefono' => null,
            'email' => null,
            'latitud' => null,
            'longitud' => null,
            'imagen' => null
        ]);

        // Assert
        $this->assertNull($asociacion->descripcion);
        $this->assertNull($asociacion->telefono);
        $this->assertNull($asociacion->email);
        $this->assertNull($asociacion->latitud);
        $this->assertNull($asociacion->longitud);
        $this->assertNull($asociacion->imagen);
        $this->assertNull($asociacion->imagen_url);
    }

    #[Test]
    public function validacion_email_formato_en_base_de_datos()
    {
        // Arrange
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id,
            'email' => 'test@example.com'
        ]);

        // Act & Assert
        $this->assertEquals('test@example.com', $asociacion->email);
        $this->assertTrue(filter_var($asociacion->email, FILTER_VALIDATE_EMAIL) !== false);
    }

    #[Test]
    public function coordenadas_se_almacenan_como_float()
    {
        // Arrange & Act
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id,
            'latitud' => -15.8422,
            'longitud' => -70.0199
        ]);

        // Assert
        $this->assertIsFloat($asociacion->latitud);
        $this->assertIsFloat($asociacion->longitud);
        $this->assertEquals(-15.8422, $asociacion->latitud);
        $this->assertEquals(-70.0199, $asociacion->longitud);
    }

    #[Test]
    public function estado_se_almacena_como_boolean()
    {
        // Arrange & Act
        $asociacionActiva = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id,
            'estado' => true
        ]);

        $asociacionInactiva = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id,
            'estado' => false
        ]);

        // Assert
        $this->assertIsBool($asociacionActiva->estado);
        $this->assertIsBool($asociacionInactiva->estado);
        $this->assertTrue($asociacionActiva->estado);
        $this->assertFalse($asociacionInactiva->estado);
    }

    #[Test]
    public function created_at_y_updated_at_se_establecen_automaticamente()
    {
        // Arrange & Act
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);

        // Assert
        $this->assertNotNull($asociacion->created_at);
        $this->assertNotNull($asociacion->updated_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $asociacion->created_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $asociacion->updated_at);
    }
}