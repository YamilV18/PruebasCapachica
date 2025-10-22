<?php

namespace Tests\Unit\Models;

use App\Models\Evento;
use App\Models\Emprendedor;
use App\Models\Asociacion;
use App\Models\Municipalidad;
use App\Models\Slider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Carbon\Carbon;

class EventoTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected Emprendedor $emprendedor;
    protected Asociacion $asociacion;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear estructura básica
        $municipalidad = Municipalidad::factory()->create();
        $this->asociacion = Asociacion::factory()->create(['municipalidad_id' => $municipalidad->id]);
        $this->emprendedor = Emprendedor::factory()->create(['asociacion_id' => $this->asociacion->id]);
    }

    /**
     * Proporciona un array de datos válidos para crear un Evento.
     *
     * @param array $overrides
     * @return array
     */
    private function getValidEventoData(array $overrides = []): array
    {
        $data = [
            'nombre' => $this->faker->sentence,
            'descripcion' => $this->faker->paragraph,
            'tipo_evento' => 'Festival Cultural',
            'idioma_principal' => 'Español',
            'fecha_inicio' => $this->faker->date(),
            'hora_inicio' => $this->faker->time('H:i:s'),
            'fecha_fin' => $this->faker->date(),
            'hora_fin' => $this->faker->time('H:i:s'),
            'duracion_horas' => $this->faker->numberBetween(1, 24),
            'coordenada_x' => $this->faker->longitude,
            'coordenada_y' => $this->faker->latitude,
            'id_emprendedor' => $this->emprendedor->id,
            'que_llevar' => $this->faker->sentence,
        ];

        return array_merge($data, $overrides);
    }

    #[Test]
    public function puede_crear_evento_con_datos_validos()
    {
        // Arrange
        $data = [
            'nombre' => 'Festival de la Trucha del Titicaca',
            'descripcion' => 'Gran festival gastronómico que celebra la riqueza culinaria del Lago Titicaca con preparaciones tradicionales de trucha.',
            'tipo_evento' => 'Festival Cultural',
            'idioma_principal' => 'Español',
            'fecha_inicio' => '2024-08-15',
            'hora_inicio' => '10:00:00',
            'fecha_fin' => '2024-08-17',
            'hora_fin' => '20:00:00',
            'duracion_horas' => 8,
            'coordenada_x' => -69.8573, // Longitud del Lago Titicaca
            'coordenada_y' => -15.6123, // Latitud del Lago Titicaca
            'id_emprendedor' => $this->emprendedor->id,
            'que_llevar' => 'Ropa cómoda, protector solar, gorra, agua, cámara fotográfica'
        ];

        // Act
        $evento = Evento::create($data);

        // Assert
        $this->assertInstanceOf(Evento::class, $evento);
        $this->assertEquals($data['nombre'], $evento->nombre);
        $this->assertEquals($data['tipo_evento'], $evento->tipo_evento);
        $this->assertEquals($data['fecha_inicio'], $evento->fecha_inicio);
        $this->assertEquals($data['coordenada_x'], $evento->coordenada_x);
        $this->assertEquals($data['id_emprendedor'], $evento->id_emprendedor);

        $this->assertDatabaseHas('eventos', [
            'nombre' => $data['nombre'],
            'tipo_evento' => $data['tipo_evento'],
            'id_emprendedor' => $this->emprendedor->id
        ]);
    }

    #[Test]
    public function fillable_permite_campos_correctos()
    {
        // Arrange
        $evento = new Evento();
        $data = [
            'nombre' => 'Test Evento',
            'descripcion' => 'Descripción del evento',
            'tipo_evento' => 'Festival Cultural',
            'idioma_principal' => 'Español',
            'fecha_inicio' => '2024-08-15',
            'hora_inicio' => '10:00:00',
            'fecha_fin' => '2024-08-17',
            'hora_fin' => '18:00:00',
            'duracion_horas' => 6,
            'coordenada_x' => -69.8573,
            'coordenada_y' => -15.6123,
            'id_emprendedor' => $this->emprendedor->id,
            'que_llevar' => 'Artículos necesarios',
            'campo_no_permitido' => 'no debe ser asignado'
        ];

        // Act
        $evento->fill($data);

        // Assert
        $this->assertEquals('Test Evento', $evento->nombre);
        $this->assertEquals('Festival Cultural', $evento->tipo_evento);
        $this->assertEquals('2024-08-15', $evento->fecha_inicio);
        $this->assertEquals(-69.8573, $evento->coordenada_x);
        $this->assertEquals($this->emprendedor->id, $evento->id_emprendedor);
        $this->assertNull($evento->campo_no_permitido);
    }

    #[Test]
    public function relacion_emprendedor_funciona_correctamente()
    {
        // Arrange
        $evento = Evento::create($this->getValidEventoData());

        // Act
        $emprendedorRelacionado = $evento->emprendedor;

        // Assert
        $this->assertInstanceOf(Emprendedor::class, $emprendedorRelacionado);
        $this->assertEquals($this->emprendedor->id, $emprendedorRelacionado->id);
        $this->assertEquals($this->emprendedor->nombre, $emprendedorRelacionado->nombre);
    }

    #[Test]
    public function relacion_sliders_funciona_correctamente()
    {
        // Arrange
        $evento = Evento::create($this->getValidEventoData());

        // Crear sliders para el evento
        $sliders = Slider::factory()->count(3)->create([
            'entidad_id' => $evento->id,
            'tipo_entidad' => 'evento'
        ]);

        // Act
        $slidersRelacionados = $evento->sliders;

        // Assert
        $this->assertCount(3, $slidersRelacionados);
        foreach ($sliders as $slider) {
            $this->assertTrue(
                $slidersRelacionados->contains('id', $slider->id)
            );
        }

        // Verificar que están ordenados por 'orden'
        $ordenesActuales = $slidersRelacionados->pluck('orden')->toArray();
        $ordenesEsperados = $sliders->sortBy('orden')->pluck('orden')->toArray();
        $this->assertEquals($ordenesEsperados, $ordenesActuales);
    }

    #[Test]
    public function relacion_sliders_filtra_por_tipo_entidad()
    {
        // Arrange
        $evento = Evento::create($this->getValidEventoData());

        // Crear sliders del evento
        Slider::factory()->count(2)->create([
            'entidad_id' => $evento->id,
            'tipo_entidad' => 'evento'
        ]);

        // Crear sliders de otro tipo con el mismo entidad_id
        Slider::factory()->count(2)->create([
            'entidad_id' => $evento->id,
            'tipo_entidad' => 'servicio'
        ]);

        // Act
        $slidersDelEvento = $evento->sliders;

        // Assert
        $this->assertCount(2, $slidersDelEvento);
        foreach ($slidersDelEvento as $slider) {
            $this->assertEquals('evento', $slider->tipo_entidad);
            $this->assertEquals($evento->id, $slider->entidad_id);
        }
    }

    #[Test]
    public function tabla_correcta_es_utilizada()
    {
        // Arrange
        $evento = new Evento();

        // Act
        $tabla = $evento->getTable();

        // Assert
        $this->assertEquals('eventos', $tabla);
    }

    #[Test]
    public function primary_key_es_id_por_defecto()
    {
        // Arrange
        $evento = new Evento();

        // Act
        $primaryKey = $evento->getKeyName();

        // Assert
        $this->assertEquals('id', $primaryKey);
    }

    #[Test]
    public function timestamps_estan_habilitados()
    {
        // Arrange
        $evento = new Evento();

        // Act
        $timestamps = $evento->usesTimestamps();

        // Assert
        $this->assertTrue($timestamps);
    }

    #[Test]
    public function puede_actualizar_campos_individuales()
    {
        // Arrange
        $evento = Evento::create($this->getValidEventoData([
            'nombre' => 'Nombre Original',
            'tipo_evento' => 'Festival Cultural'
        ]));

        // Act
        $evento->update([
            'nombre' => 'Nombre Actualizado',
            'tipo_evento' => 'Ceremonia Tradicional',
            'duracion_horas' => 10
        ]);

        // Assert
        $this->assertEquals('Nombre Actualizado', $evento->fresh()->nombre);
        $this->assertEquals('Ceremonia Tradicional', $evento->fresh()->tipo_evento);
        $this->assertEquals(10, $evento->fresh()->duracion_horas);
    }

    #[Test]
    public function puede_eliminar_evento()
    {
        // Arrange
        $evento = Evento::create($this->getValidEventoData());
        $id = $evento->id;

        // Act
        $result = $evento->delete();

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('eventos', ['id' => $id]);
    }



    #[Test]
    public function created_at_y_updated_at_se_establecen_automaticamente()
    {
        // Arrange & Act
        $evento = Evento::create($this->getValidEventoData());

        // Assert
        $this->assertNotNull($evento->created_at);
        $this->assertNotNull($evento->updated_at);
        $this->assertInstanceOf(Carbon::class, $evento->created_at);
        $this->assertInstanceOf(Carbon::class, $evento->updated_at);
    }

    #[Test]
    public function puede_almacenar_coordenadas_geograficas_precisas()
    {
        // Arrange & Act
        $evento = Evento::create($this->getValidEventoData([
            'coordenada_x' => -69.857334, // Longitud con alta precisión
            'coordenada_y' => -15.612345  // Latitud con alta precisión
        ]));

        // Assert
        $this->assertEquals(-69.857334, $evento->coordenada_x);
        $this->assertEquals(-15.612345, $evento->coordenada_y);
        $this->assertIsFloat($evento->coordenada_x);
        $this->assertIsFloat($evento->coordenada_y);
    }

    #[Test]
    public function puede_manejar_fechas_y_horas_correctamente()
    {
        // Arrange & Act
        $evento = Evento::create($this->getValidEventoData([
            'fecha_inicio' => '2024-06-21',
            'hora_inicio' => '06:30:00',
            'fecha_fin' => '2024-06-21',
            'hora_fin' => '18:45:00',
            'duracion_horas' => 12
        ]));

        // Assert
        $this->assertEquals('2024-06-21', $evento->fecha_inicio);
        $this->assertEquals('06:30:00', $evento->hora_inicio);
        $this->assertEquals('2024-06-21', $evento->fecha_fin);
        $this->assertEquals('18:45:00', $evento->hora_fin);
        $this->assertEquals(12, $evento->duracion_horas);
        $this->assertIsInt($evento->duracion_horas);
    }

    #[Test]
    public function puede_almacenar_texto_largo_en_descripcion()
    {
        // Arrange
        $descripcionLarga = str_repeat('Este es un texto muy largo para probar el almacenamiento de descripciones extensas en eventos. ', 50);

        // Act
        $evento = Evento::create($this->getValidEventoData([
            'descripcion' => $descripcionLarga
        ]));

        // Assert
        $this->assertEquals($descripcionLarga, $evento->descripcion);
        $this->assertGreaterThan(1000, strlen($evento->descripcion));
    }

    #[Test]
    public function puede_almacenar_tipos_evento_diversos()
    {
        // Arrange
        $tiposEvento = [
            'Festival Cultural', 'Ceremonia Tradicional', 'Actividad Gastronómica',
            'Tour Temático', 'Actividad Acuática', 'Caminata Ecológica',
            'Evento Deportivo', 'Celebración Comunitaria'
        ];

        // Act & Assert
        foreach ($tiposEvento as $tipo) {
            $evento = Evento::create($this->getValidEventoData(['tipo_evento' => $tipo]));
            $this->assertEquals($tipo, $evento->tipo_evento);
            $this->assertDatabaseHas('eventos', ['id' => $evento->id, 'tipo_evento' => $tipo]);
        }
    }

    #[Test]
    public function puede_almacenar_idiomas_principales_diversos()
    {
        // Arrange
        $idiomas = [
            'Español', 'Quechua', 'Inglés',
            'Bilingüe (Español-Quechua)', 'Trilingüe (Español-Quechua-Inglés)'
        ];

        // Act & Assert
        foreach ($idiomas as $idioma) {
            $evento = Evento::create($this->getValidEventoData(['idioma_principal' => $idioma]));
            $this->assertEquals($idioma, $evento->idioma_principal);
        }
    }

    #[Test]
    public function puede_convertir_a_array()
    {
        // Arrange
        $evento = Evento::create($this->getValidEventoData());

        // Act
        $array = $evento->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('nombre', $array);
        $this->assertArrayHasKey('descripcion', $array);
        $this->assertArrayHasKey('tipo_evento', $array);
        $this->assertArrayHasKey('fecha_inicio', $array);
        $this->assertArrayHasKey('fecha_fin', $array);
        $this->assertArrayHasKey('coordenada_x', $array);
        $this->assertArrayHasKey('coordenada_y', $array);
        $this->assertArrayHasKey('id_emprendedor', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    #[Test]
    public function puede_convertir_a_json()
    {
        // Arrange
        $evento = Evento::create($this->getValidEventoData());

        // Act
        $json = $evento->toJson();
        $data = json_decode($json, true);

        // Assert
        $this->assertIsString($json);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('nombre', $data);
        $this->assertArrayHasKey('tipo_evento', $data);
        $this->assertArrayHasKey('fecha_inicio', $data);
    }

    #[Test]
    public function duracion_horas_es_entero_positivo()
    {
        // Arrange & Act
        $evento = Evento::create($this->getValidEventoData(['duracion_horas' => 8]));

        // Assert
        $this->assertIsInt($evento->duracion_horas);
        $this->assertGreaterThan(0, $evento->duracion_horas);
    }

    #[Test]
    public function puede_cargar_relaciones_eager()
    {
        // Arrange
        $evento = Evento::create($this->getValidEventoData());
        Slider::factory()->count(2)->create([
            'entidad_id' => $evento->id,
            'tipo_entidad' => 'evento'
        ]);

        // Act
        $eventoConRelaciones = Evento::with(['emprendedor', 'sliders'])->find($evento->id);

        // Assert
        $this->assertTrue($eventoConRelaciones->relationLoaded('emprendedor'));
        $this->assertTrue($eventoConRelaciones->relationLoaded('sliders'));
        $this->assertInstanceOf(Emprendedor::class, $eventoConRelaciones->emprendedor);
        $this->assertCount(2, $eventoConRelaciones->sliders);
    }

    #[Test]
    public function puede_filtrar_por_fechas()
    {
        // Arrange
        $fechaActual = now()->format('Y-m-d');
        $fechaFutura = now()->addDays(30)->format('Y-m-d');
        $fechaPasada = now()->subDays(30)->format('Y-m-d');

        $eventoFuturo = Evento::create($this->getValidEventoData([
            'fecha_inicio' => $fechaFutura,
            'fecha_fin' => $fechaFutura
        ]));

        $eventoPasado = Evento::create($this->getValidEventoData([
            'fecha_inicio' => $fechaPasada,
            'fecha_fin' => $fechaPasada
        ]));

        // Act
        $eventosActivos = Evento::where('fecha_fin', '>=', $fechaActual)->get();
        $eventosPasados = Evento::where('fecha_fin', '<', $fechaActual)->get();

        // Assert
        $this->assertTrue($eventosActivos->contains('id', $eventoFuturo->id));
        $this->assertFalse($eventosActivos->contains('id', $eventoPasado->id));

        $this->assertTrue($eventosPasados->contains('id', $eventoPasado->id));
        $this->assertFalse($eventosPasados->contains('id', $eventoFuturo->id));
    }

    #[Test]
    public function puede_ordenar_por_fecha_de_inicio()
    {
        // Arrange
        $evento1 = Evento::create($this->getValidEventoData(['fecha_inicio' => '2024-08-20']));
        $evento2 = Evento::create($this->getValidEventoData(['fecha_inicio' => '2024-08-15']));
        $evento3 = Evento::create($this->getValidEventoData(['fecha_inicio' => '2024-08-25']));

        // Act
        $eventosOrdenados = Evento::orderBy('fecha_inicio')->get();

        // Assert
        $idsOrdenados = $eventosOrdenados->pluck('id')->toArray();
        $this->assertEquals([$evento2->id, $evento1->id, $evento3->id], $idsOrdenados);
    }

    #[Test]
    public function puede_buscar_por_nombre_parcial()
    {
        // Arrange
        $evento1 = Evento::create($this->getValidEventoData(['nombre' => 'Festival de la Trucha del Titicaca']));
        $evento2 = Evento::create($this->getValidEventoData(['nombre' => 'Ceremonia del Inti Raymi']));

        // Act
        $eventosConTrucha = Evento::where('nombre', 'like', '%Trucha%')->get();
        $eventosConCeremonia = Evento::where('nombre', 'like', '%Ceremonia%')->get();

        // Assert
        $this->assertTrue($eventosConTrucha->contains('id', $evento1->id));
        $this->assertFalse($eventosConTrucha->contains('id', $evento2->id));

        $this->assertTrue($eventosConCeremonia->contains('id', $evento2->id));
        $this->assertFalse($eventosConCeremonia->contains('id', $evento1->id));
    }
}
