<?php

namespace Tests\Unit\Models;

use App\Models\Servicio;
use App\Models\Emprendedor;
use App\Models\Categoria;
use App\Models\Asociacion;
use App\Models\Municipalidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServicioTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected Emprendedor $emprendedor;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear emprendedor con dependencias
        $municipalidad = Municipalidad::factory()->create();
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $municipalidad->id
        ]);
        $this->emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $asociacion->id
        ]);
    }

    #[Test]
    public function puede_crear_servicio_con_datos_validos()
    {
        // Arrange
        $data = [
            'nombre' => 'Tour en Kayak',
            'descripcion' => 'Experiencia única en el lago',
            'precio_referencial' => 45.50,
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => true,
            'capacidad' => 6,
            'latitud' => -15.8422,
            'longitud' => -70.0199,
            'ubicacion_referencia' => 'Muelle Principal'
        ];

        // Act
        $servicio = Servicio::create($data);

        // Assert
        $this->assertInstanceOf(Servicio::class, $servicio);
        $this->assertEquals($data['nombre'], $servicio->nombre);
        $this->assertEquals($data['precio_referencial'], $servicio->precio_referencial);
        $this->assertTrue($servicio->estado);
        $this->assertDatabaseHas('servicios', $data);
    }

    #[Test]
    public function fillable_permite_campos_correctos()
    {
        // Arrange
        $servicio = new Servicio();
        $data = [
            'nombre' => 'Test Servicio',
            'descripcion' => 'Test descripcion',
            'precio_referencial' => 25.50,
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => true,
            'capacidad' => 8,
            'latitud' => -15.8422,
            'longitud' => -70.0199,
            'ubicacion_referencia' => 'Test ubicación',
            'campo_no_permitido' => 'no debe ser asignado'
        ];

        // Act
        $servicio->fill($data);

        // Assert
        $this->assertEquals('Test Servicio', $servicio->nombre);
        $this->assertEquals(25.50, $servicio->precio_referencial);
        $this->assertTrue($servicio->estado);
        $this->assertNull($servicio->campo_no_permitido);
    }



    #[Test]
    public function relacion_emprendedor_funciona_correctamente()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $emprendedorRelacionado = $servicio->emprendedor;

        // Assert
        $this->assertInstanceOf(Emprendedor::class, $emprendedorRelacionado);
        $this->assertEquals($this->emprendedor->id, $emprendedorRelacionado->id);
        $this->assertEquals($this->emprendedor->nombre, $emprendedorRelacionado->nombre);
    }

    #[Test]
    public function relacion_categorias_funciona_correctamente()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        $categorias = Categoria::factory()->count(3)->create();

        // Asociar categorías al servicio
        $servicio->categorias()->attach($categorias->pluck('id'));

        // Act
        $categoriasRelacionadas = $servicio->categorias;

        // Assert
        $this->assertCount(3, $categoriasRelacionadas);
        foreach ($categorias as $categoria) {
            $this->assertTrue(
                $categoriasRelacionadas->contains('id', $categoria->id)
            );
        }
    }

    #[Test]
    public function relacion_categorias_incluye_timestamps()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);
        $categoria = Categoria::factory()->create();

        // Act
        $servicio->categorias()->attach($categoria->id);
        $pivot = $servicio->categorias()->first()->pivot;

        // Assert
        $this->assertNotNull($pivot->created_at);
        $this->assertNotNull($pivot->updated_at);
    }

    #[Test]
    public function relacion_horarios_existe_y_funciona()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $horariosRelation = $servicio->horarios();

        // Assert
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $horariosRelation);
        $this->assertEquals('servicio_id', $horariosRelation->getForeignKeyName());
    }

    #[Test]
    public function relacion_reservas_existe_y_funciona()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $reservasRelation = $servicio->reservas();

        // Assert
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $reservasRelation);
        $this->assertEquals('servicio_id', $reservasRelation->getForeignKeyName());
    }

    #[Test]
    public function relacion_sliders_existe_y_funciona()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $slidersRelation = $servicio->sliders();

        // Assert
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $slidersRelation);
        $this->assertEquals('entidad_id', $slidersRelation->getForeignKeyName());
    }

    #[Test]
    public function tabla_correcta_es_utilizada()
    {
        // Arrange
        $servicio = new Servicio();

        // Act
        $tabla = $servicio->getTable();

        // Assert
        $this->assertEquals('servicios', $tabla);
    }

    #[Test]
    public function primary_key_es_id_por_defecto()
    {
        // Arrange
        $servicio = new Servicio();

        // Act
        $primaryKey = $servicio->getKeyName();

        // Assert
        $this->assertEquals('id', $primaryKey);
    }

    #[Test]
    public function timestamps_estan_habilitados()
    {
        // Arrange
        $servicio = new Servicio();

        // Act
        $timestamps = $servicio->usesTimestamps();

        // Assert
        $this->assertTrue($timestamps);
    }

    #[Test]
    public function puede_actualizar_campos_individuales()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id,
            'nombre' => 'Nombre Original',
            'precio_referencial' => 25.00
        ]);

        // Act
        $servicio->update([
            'nombre' => 'Nombre Actualizado',
            'precio_referencial' => 50.00
        ]);

        // Assert
        $this->assertEquals('Nombre Actualizado', $servicio->fresh()->nombre);
        $this->assertEquals(50.00, $servicio->fresh()->precio_referencial);
    }

    #[Test]
    public function puede_eliminar_servicio()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);
        $id = $servicio->id;

        // Act
        $result = $servicio->delete();

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('servicios', ['id' => $id]);
    }

    #[Test]
    public function puede_asociar_y_desasociar_categorias()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);
        $categoria1 = Categoria::factory()->create();
        $categoria2 = Categoria::factory()->create();

        // Act - Asociar categorías
        $servicio->categorias()->attach([$categoria1->id, $categoria2->id]);

        // Assert - Verificar asociación
        $this->assertCount(2, $servicio->categorias);

        // Act - Desasociar una categoría
        $servicio->categorias()->detach($categoria1->id);

        // Assert - Verificar desasociación
        $servicio->refresh();
        $this->assertCount(1, $servicio->categorias);
        $this->assertTrue($servicio->categorias->contains('id', $categoria2->id));
        $this->assertFalse($servicio->categorias->contains('id', $categoria1->id));
    }

    #[Test]
    public function puede_sincronizar_categorias()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);
        $categoriasIniciales = Categoria::factory()->count(3)->create();
        $nuevasCategorias = Categoria::factory()->count(2)->create();

        // Asociar categorías iniciales
        $servicio->categorias()->attach($categoriasIniciales->pluck('id'));

        // Act - Sincronizar con nuevas categorías
        $servicio->categorias()->sync($nuevasCategorias->pluck('id'));

        // Assert
        $servicio->refresh();
        $this->assertCount(2, $servicio->categorias);
        foreach ($nuevasCategorias as $categoria) {
            $this->assertTrue($servicio->categorias->contains('id', $categoria->id));
        }
        foreach ($categoriasIniciales as $categoria) {
            $this->assertFalse($servicio->categorias->contains('id', $categoria->id));
        }
    }

    #[Test]
    public function maneja_valores_nulos_correctamente()
    {
        // Arrange & Act
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id,
            'descripcion' => null,
            'latitud' => null,
            'longitud' => null,
            'ubicacion_referencia' => null
        ]);

        // Assert
        $this->assertNull($servicio->descripcion);
        $this->assertNull($servicio->latitud);
        $this->assertNull($servicio->longitud);
        $this->assertNull($servicio->ubicacion_referencia);
    }

    #[Test]
    public function created_at_y_updated_at_se_establecen_automaticamente()
    {
        // Arrange & Act
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Assert
        $this->assertNotNull($servicio->created_at);
        $this->assertNotNull($servicio->updated_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $servicio->created_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $servicio->updated_at);
    }

    #[Test]
    public function precio_referencial_se_almacena_con_precision_decimal()
    {
        // Arrange & Act
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id,
            'precio_referencial' => 123.456 // Con más de 2 decimales
        ]);

        // Assert
        $this->assertEquals(123.46, $servicio->precio_referencial); // Debe redondear a 2 decimales
    }

    #[Test]
    public function estado_se_almacena_como_boolean()
    {
        // Arrange & Act
        $servicioActivo = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => true
        ]);

        $servicioInactivo = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => false
        ]);

        // Assert
        $this->assertIsBool($servicioActivo->estado);
        $this->assertIsBool($servicioInactivo->estado);
        $this->assertTrue($servicioActivo->estado);
        $this->assertFalse($servicioInactivo->estado);
    }

    #[Test]
    public function coordenadas_se_almacenan_con_precision_correcta()
    {
        // Arrange & Act
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id,
            'latitud' => -15.8422123456,
            'longitud' => -70.0199456789
        ]);

        // Assert
        $this->assertIsString($servicio->latitud);
        $this->assertIsString($servicio->longitud);
        // El cast decimal:7 debería mantener 7 dígitos después del punto decimal
        $this->assertEquals('-15.8422123', $servicio->latitud);
        $this->assertEquals('-70.0199457', $servicio->longitud);
    }

    #[Test]
    public function puede_convertir_a_array()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $array = $servicio->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('nombre', $array);
        $this->assertArrayHasKey('precio_referencial', $array);
        $this->assertArrayHasKey('emprendedor_id', $array);
        $this->assertArrayHasKey('estado', $array);
        $this->assertArrayHasKey('capacidad', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    #[Test]
    public function puede_convertir_a_json()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $json = $servicio->toJson();
        $data = json_decode($json, true);

        // Assert
        $this->assertIsString($json);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('nombre', $data);
        $this->assertArrayHasKey('precio_referencial', $data);
    }

    #[Test]
    public function capacidad_es_entero_positivo()
    {
        // Arrange & Act
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id,
            'capacidad' => 10
        ]);

        // Assert
        $this->assertIsInt($servicio->capacidad);
        $this->assertGreaterThan(0, $servicio->capacidad);
    }
}
