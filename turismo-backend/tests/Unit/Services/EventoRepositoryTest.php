<?php

namespace Tests\Unit\Services;

use App\Models\Evento;
use App\Models\Emprendedor;
use App\Models\Asociacion;
use App\Models\Municipalidad;
use App\Models\Slider;
use App\Repository\EventoRepository;
use App\Repository\SliderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Mockery;

class EventoRepositoryTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected EventoRepository $repository;
    protected Emprendedor $emprendedor;
    protected Asociacion $asociacion;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock SliderRepository para evitar dependencias
        $sliderRepositoryMock = Mockery::mock(SliderRepository::class);
        $sliderRepositoryMock->shouldReceive('createMultiple')->andReturn(new \Illuminate\Support\Collection());
        $sliderRepositoryMock->shouldReceive('updateEntitySliders')->andReturn(new \Illuminate\Support\Collection());
        $sliderRepositoryMock->shouldReceive('delete')->andReturn(true);

        $this->repository = new EventoRepository(new Evento(), $sliderRepositoryMock);

        // Crear estructura básica
        $municipalidad = Municipalidad::factory()->create();
        $this->asociacion = Asociacion::factory()->create(['municipalidad_id' => $municipalidad->id]);
        $this->emprendedor = Emprendedor::factory()->create(['asociacion_id' => $this->asociacion->id]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function puede_obtener_todos_los_eventos()
    {
        // Arrange
        $now = now()->format('Y-m-d H:i:s');
        $eventosData = [];
        for ($i = 1; $i <= 5; $i++) {
            $eventosData[] = [
                'nombre' => 'Evento Test ' . $i,
                'descripcion' => 'Descripción ' . $i,
                'tipo_evento' => 'Test',
                'idioma_principal' => 'Español',
                'fecha_inicio' => now()->addDays($i)->format('Y-m-d'),
                'hora_inicio' => '10:00:00',
                'fecha_fin' => now()->addDays($i + 1)->format('Y-m-d'),
                'hora_fin' => '18:00:00',
                'duracion_horas' => 8,
                'coordenada_x' => -69.8573,
                'coordenada_y' => -15.6123,
                'id_emprendedor' => $this->emprendedor->id,
                'que_llevar' => 'Nada',
                'created_at' => $now,
                'updated_at' => $now
            ];
        }
        DB::table('eventos')->insert($eventosData);

        // Act
        $result = $this->repository->getAll();

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(5, $result);

        // Verificar que las relaciones están cargadas
        $this->assertTrue($result->first()->relationLoaded('emprendedor'));
        $this->assertTrue($result->first()->relationLoaded('sliders'));
    }

    #[Test]
    public function puede_obtener_eventos_paginados()
    {
        for ($i = 0; $i < 25; $i++) {
            \App\Models\Evento::create([
                'nombre' => "Evento Paginado $i",
                'descripcion' => "Descripción del evento paginado $i",
                'tipo_evento' => 'Conferencia',
                'idioma_principal' => 'Español',
                'fecha_inicio' => now()->addDays($i)->format('Y-m-d'),
                'hora_inicio' => '09:00:00',
                'fecha_fin' => now()->addDays($i + 1)->format('Y-m-d'),
                'hora_fin' => '17:00:00',
                'duracion_horas' => 8,
                'coordenada_x' => -70.0000 + $i,
                'coordenada_y' => -15.0000 + $i,
                'id_emprendedor' => $this->emprendedor->id
            ]);
        }

        // Act
        $result = $this->repository->getPaginated(10);

        // Assert
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        $this->assertEquals(10, $result->perPage());
        $this->assertEquals(25, $result->total());
    }

    #[Test]
    public function puede_obtener_evento_por_id()
    {
        // Arrange
        $evento = \App\Models\Evento::create([
            'nombre' => "Evento Paginado",
            'descripcion' => "Descripción del evento",
            'tipo_evento' => 'Conferencia',
            'idioma_principal' => 'Español',
            'fecha_inicio' => now()->addDays(1)->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'fecha_fin' => now()->addDays(2)->format('Y-m-d'),
            'hora_fin' => '17:00:00',
            'duracion_horas' => 8,
            'coordenada_x' => -70.0000,
            'coordenada_y' => -15.0000,
            'id_emprendedor' => $this->emprendedor->id
        ]);

        // Act
        $result = $this->repository->getById($evento->id);

        // Assert
        $this->assertInstanceOf(Evento::class, $result);
        $this->assertEquals($evento->id, $result->id);
        $this->assertEquals($evento->nombre, $result->nombre);

        // Verificar que las relaciones están cargadas
        $this->assertTrue($result->relationLoaded('emprendedor'));
        $this->assertTrue($result->relationLoaded('sliders'));
    }

    #[Test]
    public function retorna_null_cuando_evento_no_existe()
    {
        // Act
        $result = $this->repository->getById(999);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function puede_crear_evento_sin_sliders()
    {
        // Arrange
        $data = [
            'nombre' => 'Festival de la Trucha 2024',
            'descripcion' => 'Gran festival gastronómico del Lago Titicaca',
            'tipo_evento' => 'Festival Cultural',
            'idioma_principal' => 'Español',
            'fecha_inicio' => '2024-08-15',
            'hora_inicio' => '10:00:00',
            'fecha_fin' => '2024-08-17',
            'hora_fin' => '18:00:00',
            'duracion_horas' => 8,
            'coordenada_x' => -69.8573,
            'coordenada_y' => -15.6123,
            'id_emprendedor' => $this->emprendedor->id,
            'que_llevar' => 'Ropa cómoda, protector solar'
        ];

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertInstanceOf(Evento::class, $result);
        $this->assertEquals($data['nombre'], $result->nombre);
        $this->assertEquals($data['tipo_evento'], $result->tipo_evento);
        $this->assertEquals($data['id_emprendedor'], $result->id_emprendedor);

        $this->assertDatabaseHas('eventos', [
            'nombre' => $data['nombre'],
            'tipo_evento' => $data['tipo_evento'],
            'id_emprendedor' => $this->emprendedor->id
        ]);
    }

    #[Test]
    public function puede_crear_evento_con_sliders()
    {
        // Arrange
        $data = [
            'nombre' => 'Ceremonia del Inti Raymi',
            'descripcion' => 'Celebración tradicional inca en honor al dios Sol',
            'tipo_evento' => 'Ceremonia Tradicional',
            'idioma_principal' => 'Español',
            'fecha_inicio' => '2024-06-21',
            'duracion_horas' => 3,
            'hora_inicio' => '09:00:00',
            'fecha_fin' => '2024-06-21', // Campo añadido para evitar el error 1364
            'hora_fin' => '12:00:00',    // Puede que falte también si es NOT NULL
            'coordenada_x' => -70.0000,
            'coordenada_y' => -15.0000,
            'id_emprendedor' => $this->emprendedor->id,
            'sliders' => [
                [
                    'url' => 'imagen1.jpg',
                    'nombre' => 'Ceremonia Principal',
                    'orden' => 1,
                    'activo' => true,
                    'es_principal' => true
                ],
                [
                    'url' => 'imagen2.jpg',
                    'nombre' => 'Danzas Tradicionales',
                    'orden' => 2,
                    'activo' => true,
                    'es_principal' => true
                ]
            ]
        ];

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertInstanceOf(Evento::class, $result);
        $this->assertEquals($data['nombre'], $result->nombre);
        $this->assertDatabaseHas('eventos', [
            'nombre' => $data['nombre'],
            'id_emprendedor' => $this->emprendedor->id
        ]);
    }

    #[Test]
    public function puede_actualizar_evento_existente()
    {
        // Arrange
        $evento = \App\Models\Evento::create([
            'nombre' => "Evento Paginado",
            'descripcion' => "Descripción del evento",
            'tipo_evento' => 'Conferencia',
            'idioma_principal' => 'Español',
            'fecha_inicio' => now()->addDays(1)->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'fecha_fin' => now()->addDays(2)->format('Y-m-d'),
            'hora_fin' => '17:00:00',
            'duracion_horas' => 8,
            'coordenada_x' => -70.0000,
            'coordenada_y' => -15.0000,
            'id_emprendedor' => $this->emprendedor->id
        ]);

        $data = [
            'nombre' => 'Evento Actualizado',
            'descripcion' => 'Descripción actualizada',
            'tipo_evento' => 'Actividad Gastronómica',
            'duracion_horas' => 10
        ];

        // Act
        $result = $this->repository->update($evento->id, $data);

        // Assert
        $this->assertInstanceOf(Evento::class, $result);
        $this->assertEquals($data['nombre'], $result->nombre);
        $this->assertEquals($data['tipo_evento'], $result->tipo_evento);
        $this->assertEquals($data['duracion_horas'], $result->duracion_horas);

        $this->assertDatabaseHas('eventos', [
            'id' => $evento->id,
            'nombre' => $data['nombre'],
            'tipo_evento' => $data['tipo_evento']
        ]);
    }

    #[Test]
    public function puede_actualizar_evento_con_sliders()
    {
        // Arrange
        $evento = \App\Models\Evento::create([
            'nombre' => "Evento Paginado",
            'descripcion' => "Descripción del evento",
            'tipo_evento' => 'Conferencia',
            'idioma_principal' => 'Español',
            'fecha_inicio' => now()->addDays(1)->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'fecha_fin' => now()->addDays(2)->format('Y-m-d'),
            'hora_fin' => '17:00:00',
            'duracion_horas' => 8,
            'coordenada_x' => -70.0000,
            'coordenada_y' => -15.0000,
            'id_emprendedor' => $this->emprendedor->id
        ]);

        $data = [
            'nombre' => 'Evento con Nuevos Sliders',
            'sliders' => [
                [
                    'url' => 'nueva_imagen.jpg',
                    'nombre' => 'Nueva Imagen',
                    'orden' => 1,
                    'activo' => true,
                    'es_principal' => true
                ]
            ]
        ];

        // Act
        $result = $this->repository->update($evento->id, $data);

        // Assert
        $this->assertInstanceOf(Evento::class, $result);
        $this->assertEquals($data['nombre'], $result->nombre);
    }

    #[Test]
    public function puede_actualizar_evento_eliminando_sliders()
    {
        // Arrange
        $evento = \App\Models\Evento::create([
            'nombre' => "Evento Paginado",
            'descripcion' => "Descripción del evento",
            'tipo_evento' => 'Conferencia',
            'idioma_principal' => 'Español',
            'fecha_inicio' => now()->addDays(1)->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'fecha_fin' => now()->addDays(2)->format('Y-m-d'),
            'hora_fin' => '17:00:00',
            'duracion_horas' => 8,
            'coordenada_x' => -70.0000,
            'coordenada_y' => -15.0000,
            'id_emprendedor' => $this->emprendedor->id
        ]);
        $slider1 = Slider::factory()->create([
            'entidad_id' => $evento->id,
            'tipo_entidad' => 'evento'
        ]);
        $slider2 = Slider::factory()->create([
            'entidad_id' => $evento->id,
            'tipo_entidad' => 'evento'
        ]);

        $data = [
            'nombre' => 'Evento Actualizado',
            'deleted_sliders' => [$slider1->id]
        ];

        // Act
        $result = $this->repository->update($evento->id, $data);

        // Assert
        $this->assertInstanceOf(Evento::class, $result);
        $this->assertEquals($data['nombre'], $result->nombre);
    }

    #[Test]
    public function lanza_excepcion_al_actualizar_evento_inexistente()
    {
        // Arrange
        $data = ['nombre' => 'Test'];

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Evento no encontrado');

        $this->repository->update(999, $data);
    }

    #[Test]
    public function puede_eliminar_evento_existente()
    {
        // Arrange
        $evento = \App\Models\Evento::create([
            'nombre' => "Evento Paginado",
            'descripcion' => "Descripción del evento",
            'tipo_evento' => 'Conferencia',
            'idioma_principal' => 'Español',
            'fecha_inicio' => now()->addDays(1)->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'fecha_fin' => now()->addDays(2)->format('Y-m-d'),
            'hora_fin' => '17:00:00',
            'duracion_horas' => 8,
            'coordenada_x' => -70.0000,
            'coordenada_y' => -15.0000,
            'id_emprendedor' => $this->emprendedor->id
        ]);

        // Act
        $result = $this->repository->delete($evento->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('eventos', ['id' => $evento->id]);
    }

    #[Test]
    public function puede_eliminar_evento_con_sliders()
    {
        // Arrange
        $evento = \App\Models\Evento::create([
            'nombre' => "Evento Paginado",
            'descripcion' => "Descripción del evento",
            'tipo_evento' => 'Conferencia',
            'idioma_principal' => 'Español',
            'fecha_inicio' => now()->addDays(1)->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'fecha_fin' => now()->addDays(2)->format('Y-m-d'),
            'hora_fin' => '17:00:00',
            'duracion_horas' => 8,
            'coordenada_x' => -70.0000,
            'coordenada_y' => -15.0000,
            'id_emprendedor' => $this->emprendedor->id
        ]);
        $sliders = Slider::factory()->count(2)->create([
            'entidad_id' => $evento->id,
            'tipo_entidad' => 'evento'
        ]);

        // Act
        $result = $this->repository->delete($evento->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('eventos', ['id' => $evento->id]);
    }

    #[Test]
    public function retorna_false_al_eliminar_evento_inexistente()
    {
        // Act
        $result = $this->repository->delete(999);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function puede_obtener_eventos_por_emprendedor()
    {
        // Arrange
        // Se asume que Emprendedor::factory() funciona correctamente
        $otroEmprendedor = Emprendedor::factory()->create(['asociacion_id' => $this->asociacion->id]);

        $eventosEmprendedor1 = collect();
        $eventosEmprendedor2 = collect();

        // Creación de eventos para el emprendedor principal (3 eventos)
        for ($i = 0; $i < 3; $i++) {
            $evento = \App\Models\Evento::create([
                'nombre' => "Evento Principal " . ($i + 1),
                'descripcion' => "Descripción del evento principal",
                'tipo_evento' => 'Conferencia',
                'idioma_principal' => 'Español',
                'fecha_inicio' => now()->addDays(1)->format('Y-m-d'),
                'hora_inicio' => '09:00:00',
                'fecha_fin' => now()->addDays(2)->format('Y-m-d'),
                'hora_fin' => '17:00:00',
                'duracion_horas' => 8,
                'coordenada_x' => -70.0000 + $i,
                'coordenada_y' => -15.0000 + $i,
                'id_emprendedor' => $this->emprendedor->id // ID del emprendedor principal
            ]);
            $eventosEmprendedor1->push($evento);
        }

        // Creación de eventos para el otro emprendedor (2 eventos)
        for ($i = 0; $i < 2; $i++) {
            $evento = \App\Models\Evento::create([
                'nombre' => "Otro Evento " . ($i + 1),
                'descripcion' => "Descripción del otro evento",
                'tipo_evento' => 'Taller',
                'idioma_principal' => 'Inglés',
                'fecha_inicio' => now()->addDays(3)->format('Y-m-d'),
                'hora_inicio' => '10:00:00',
                'fecha_fin' => now()->addDays(4)->format('Y-m-d'),
                'hora_fin' => '18:00:00',
                'duracion_horas' => 8,
                'coordenada_x' => -71.0000 + $i,
                'coordenada_y' => -16.0000 + $i,
                'id_emprendedor' => $otroEmprendedor->id // ID del otro emprendedor
            ]);
            $eventosEmprendedor2->push($evento);
        }

        // Act
        $result = $this->repository->getEventosByEmprendedor($this->emprendedor->id);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);

        foreach ($result as $evento) {
            $this->assertEquals($this->emprendedor->id, $evento->id_emprendedor);
        }

        // Verificar que las relaciones están cargadas
        // Se usa ->first() de la colección para acceder al primer elemento creado.
        $this->assertTrue($result->first()->relationLoaded('sliders'));
    }

    #[Test]
    public function puede_obtener_eventos_activos()
    {
        // Arrange
        $fechaActual = now()->format('Y-m-d');
        $fechaFutura = now()->addDays(30);
        $fechaPasada = now()->subDays(30);

        // Colecciones para almacenar los modelos creados
        $eventosActivos = collect();
        $eventosPasados = collect();

        // Eventos activos (futuros) - 3 eventos
        for ($i = 0; $i < 3; $i++) {
            $evento = \App\Models\Evento::create([
                'nombre' => "Evento Activo " . ($i + 1),
                'descripcion' => "Descripción del evento activo",
                'tipo_evento' => 'Conferencia',
                'idioma_principal' => 'Español',
                // Usamos una fecha de inicio progresiva para asegurar el orden
                'fecha_inicio' => now()->addDays(5 + $i)->format('Y-m-d'),
                'hora_inicio' => '09:00:00',
                // Aseguramos que fecha_fin es futuro
                'fecha_fin' => $fechaFutura->addDays($i)->format('Y-m-d'),
                'hora_fin' => '17:00:00',
                'duracion_horas' => 8,
                'coordenada_x' => -70.0000 + $i,
                'coordenada_y' => -15.0000 + $i,
                'id_emprendedor' => $this->emprendedor->id
            ]);
            $eventosActivos->push($evento);
        }

        // Eventos pasados - 2 eventos
        for ($i = 0; $i < 2; $i++) {
            $evento = \App\Models\Evento::create([
                'nombre' => "Evento Pasado " . ($i + 1),
                'descripcion' => "Descripción del evento pasado",
                'tipo_evento' => 'Taller',
                'idioma_principal' => 'Inglés',
                'fecha_inicio' => $fechaPasada->subDays(10 + $i)->format('Y-m-d'),
                'hora_inicio' => '10:00:00',
                // Aseguramos que fecha_fin es pasado
                'fecha_fin' => $fechaPasada->subDays($i)->format('Y-m-d'),
                'hora_fin' => '18:00:00',
                'duracion_horas' => 8,
                'coordenada_x' => -71.0000 + $i,
                'coordenada_y' => -16.0000 + $i,
                'id_emprendedor' => $this->emprendedor->id
            ]);
            $eventosPasados->push($evento);
        }

        // Act
        $result = $this->repository->getEventosActivos();

        // Assert
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertCount(3, $result);

        foreach ($result as $evento) {
            $this->assertGreaterThanOrEqual($fechaActual, $evento->fecha_fin);
        }

        // Verificar que las relaciones están cargadas
        $this->assertTrue($result->first()->relationLoaded('emprendedor'));
        $this->assertTrue($result->first()->relationLoaded('sliders'));

        // Verificar que están ordenados por fecha_inicio
        $fechasInicio = $result->pluck('fecha_inicio')->toArray();
        $fechasOrdenadas = $fechasInicio;
        sort($fechasOrdenadas);
        $this->assertEquals($fechasOrdenadas, $fechasInicio);
    }

    #[Test]
    public function puede_obtener_proximos_eventos()
    {
        // Arrange
        $fechaActual = now()->format('Y-m-d');
        $fechaFutura1 = now()->addDays(10)->format('Y-m-d');
        $fechaFutura2 = now()->addDays(20)->format('Y-m-d');
        $fechaFutura3 = now()->addDays(30)->format('Y-m-d');

        // Define los atributos comunes para la creación
        $baseAttributes = [
            'descripcion' => "Descripción del evento",
            'tipo_evento' => 'Conferencia',
            'idioma_principal' => 'Español',
            'hora_inicio' => '09:00:00',
            'fecha_fin' => now()->addDays(35)->format('Y-m-d'), // Asegura que la fecha_fin también es futura
            'hora_fin' => '17:00:00',
            'duracion_horas' => 8,
            'coordenada_x' => -70.0000,
            'coordenada_y' => -15.0000,
            'id_emprendedor' => $this->emprendedor->id
        ];

        // Crear eventos futuros (reemplazando factory()->create() con ::create())
        $evento1 = \App\Models\Evento::create(array_merge($baseAttributes, [
            'nombre' => "Evento 20 días",
            'fecha_inicio' => $fechaFutura2, // 20 días
            'coordenada_x' => $baseAttributes['coordenada_x'] + 0.001,
        ]));

        $evento2 = \App\Models\Evento::create(array_merge($baseAttributes, [
            'nombre' => "Evento 10 días",
            'fecha_inicio' => $fechaFutura1, // 10 días (el más próximo, debe ser result[0])
            'coordenada_x' => $baseAttributes['coordenada_x'] + 0.002,
        ]));

        $evento3 = \App\Models\Evento::create(array_merge($baseAttributes, [
            'nombre' => "Evento 30 días",
            'fecha_inicio' => $fechaFutura3, // 30 días
            'coordenada_x' => $baseAttributes['coordenada_x'] + 0.003,
        ]));

        // Act
        $result = $this->repository->getProximosEventos(2);

        // Assert
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertCount(2, $result);

        // Verificar que están ordenados por fecha_inicio (ascendente)
        // El evento con fecha más cercana (10 días) debe ser el primero.
        $this->assertEquals($evento2->id, $result[0]->id);
        // El segundo más cercano (20 días) debe ser el segundo.
        $this->assertEquals($evento1->id, $result[1]->id);

        // Verificar que las relaciones están cargadas
        $this->assertTrue($result->first()->relationLoaded('emprendedor'));
        $this->assertTrue($result->first()->relationLoaded('sliders'));
    }

    #[Test]
    public function puede_obtener_proximos_eventos_con_limite_por_defecto()
    {
        // Arrange
        $eventosCreados = collect();
        $diasBase = 10;

        // Definimos los atributos base (asegurando fechas futuras)
        $baseAttributes = [
            'descripcion' => "Descripción del evento por defecto",
            'tipo_evento' => 'Taller',
            'idioma_principal' => 'Español',
            'hora_inicio' => '10:00:00',
            'fecha_fin' => now()->addDays(30)->format('Y-m-d'), // Fecha futura
            'hora_fin' => '18:00:00',
            'duracion_horas' => 8,
            'id_emprendedor' => $this->emprendedor->id
        ];

        // Reemplazamos Evento::factory()->count(10)->create(...) con un bucle directo.
        for ($i = 0; $i < 10; $i++) {
            // Creamos fechas de inicio progresivas para asegurar la unicidad y el orden
            $fechaInicio = now()->addDays($diasBase + $i)->format('Y-m-d');

            $evento = \App\Models\Evento::create(array_merge($baseAttributes, [
                'nombre' => "Evento Paginado " . ($i + 1),
                'fecha_inicio' => $fechaInicio,
                // Coordenadas únicas para evitar cualquier conflicto
                'coordenada_x' => -70.0000 + $i * 0.001,
                'coordenada_y' => -15.0000 + $i * 0.001,
            ]));
            $eventosCreados->push($evento);
        }

        // Act
        $result = $this->repository->getProximosEventos();

        // Assert
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        // Verificamos el límite por defecto, que es 5.
        $this->assertCount(5, $result);
    }

    #[Test]
    public function maneja_transacciones_correctamente_en_creacion()
    {
        $data = [
            'nombre' => 'Evento Transaccional',
            'descripcion' => 'Descripción requerida para la creación.', // Campo Faltante 1
            'tipo_evento' => 'Conferencia',                           // Campo Faltante 2
            'idioma_principal' => 'Español',                          // Campo Faltante 3
            'fecha_inicio' => '2024-08-15',
            'hora_inicio' => '09:00:00',                              // Campo Faltante 4
            'fecha_fin' => '2024-08-16',                               // Campo Faltante 5
            'hora_fin' => '17:00:00',                                 // Campo Faltante 6
            'duracion_horas' => 8,                                    // Campo Faltante 7
            'coordenada_x' => -70.0000,                               // Campo Faltante 8
            'coordenada_y' => -15.0000,                               // Campo Faltante 9
            'id_emprendedor' => $this->emprendedor->id
        ];

        // Act
        DB::beginTransaction();
        $result = $this->repository->create($data);
        DB::commit();

        // Assert
        $this->assertInstanceOf(\App\Models\Evento::class, $result);
        $this->assertDatabaseHas('eventos', [
            'nombre' => 'Evento Transaccional'
        ]);
    }

    #[Test]
    public function maneja_transacciones_correctamente_en_actualizacion()
    {
        // Arrange
        // Atributos requeridos para crear el evento directamente
        $attributes = [
            'nombre' => "Evento Original",
            'descripcion' => "Descripción para test de actualización",
            'tipo_evento' => 'Conferencia',
            'idioma_principal' => 'Español',
            'fecha_inicio' => now()->addDays(5)->format('Y-m-d'),
            'hora_inicio' => '10:00:00',
            'fecha_fin' => now()->addDays(6)->format('Y-m-d'),
            'hora_fin' => '18:00:00',
            'duracion_horas' => 8,
            'coordenada_x' => -70.4000,
            'coordenada_y' => -15.4000,
            'id_emprendedor' => $this->emprendedor->id
        ];

        // Reemplazamos Evento::factory()->create(...) con la creación directa.
        $evento = \App\Models\Evento::create($attributes);

        // Datos a actualizar
        $data = [
            'nombre' => 'Actualizado con Transacción',
            // Debemos incluir otros campos requeridos si el método update los valida,
            // pero para este test, solo necesitamos el cambio de nombre.
        ];

        // Act
        // Asegúrate de que tienes 'use Illuminate\Support\Facades\DB;' al inicio del archivo
        DB::beginTransaction();
        $result = $this->repository->update($evento->id, $data);
        DB::commit();

        // Assert
        $this->assertInstanceOf(\App\Models\Evento::class, $result);
        $this->assertDatabaseHas('eventos', [
            'id' => $evento->id,
            'nombre' => 'Actualizado con Transacción'
        ]);
    }

    #[Test]
    public function maneja_transacciones_correctamente_en_eliminacion()
    {
        // Arrange

        // Atributos requeridos para crear el evento directamente
        $attributes = [
            'nombre' => "Evento a Eliminar",
            'descripcion' => "Descripción para test de eliminación",
            'tipo_evento' => 'Taller',
            'idioma_principal' => 'Español',
            'fecha_inicio' => now()->addDays(5)->format('Y-m-d'),
            'hora_inicio' => '10:00:00',
            'fecha_fin' => now()->addDays(6)->format('Y-m-d'),
            'hora_fin' => '18:00:00',
            'duracion_horas' => 8,
            'coordenada_x' => -70.5000,
            'coordenada_y' => -15.5000,
            'id_emprendedor' => $this->emprendedor->id
        ];

        // Reemplazamos Evento::factory()->create(...) con la creación directa.
        $evento = \App\Models\Evento::create($attributes);

        // Act
        // Asegúrate de que tienes 'use Illuminate\Support\Facades\DB;' al inicio del archivo
        DB::beginTransaction();
        $result = $this->repository->delete($evento->id);
        DB::commit();

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('eventos', ['id' => $evento->id]);
    }

    #[Test]
    public function puede_usar_paginacion_con_diferentes_tamaños()
    {
        // Arrange
        $diasBase = 10;

        // Atributos base para la creación de eventos
        $baseAttributes = [
            'descripcion' => "Descripción para paginación",
            'tipo_evento' => 'Taller',
            'idioma_principal' => 'Español',
            'hora_inicio' => '10:00:00',
            'fecha_fin' => now()->addDays(30)->format('Y-m-d'),
            'hora_fin' => '18:00:00',
            'duracion_horas' => 8,
            'coordenada_y' => -15.0000,
            'id_emprendedor' => $this->emprendedor->id
        ];

        // Reemplazamos Evento::factory()->count(25)->create(...) con un bucle directo.
        for ($i = 0; $i < 25; $i++) {
            // Creamos fechas y coordenadas únicas para los 25 eventos
            $fechaInicio = now()->addDays($diasBase + $i)->format('Y-m-d');

            \App\Models\Evento::create(array_merge($baseAttributes, [
                'nombre' => "Evento Paginado " . ($i + 1),
                'fecha_inicio' => $fechaInicio,
                'coordenada_x' => -70.0000 + $i * 0.001,
            ]));
        }

        // Act
        $resultados5 = $this->repository->getPaginated(5);
        $resultados10 = $this->repository->getPaginated(10);
        $resultados15 = $this->repository->getPaginated(15);

        // Assert
        $this->assertEquals(5, $resultados5->perPage());
        $this->assertEquals(10, $resultados10->perPage());
        $this->assertEquals(15, $resultados15->perPage());

        $this->assertEquals(25, $resultados5->total());
        $this->assertEquals(25, $resultados10->total());
        $this->assertEquals(25, $resultados15->total());
    }

    #[Test]
    public function filtra_eventos_por_emprendedor_correctamente()
    {
        // Arrange
        // Se asume que Emprendedor::factory()->create() funciona correctamente
        $emprendedor1 = $this->emprendedor;
        $emprendedor2 = \App\Models\Emprendedor::factory()->create(['asociacion_id' => $this->asociacion->id]);
        $emprendedor3 = \App\Models\Emprendedor::factory()->create(['asociacion_id' => $this->asociacion->id]);

        // Atributos base para evitar la violación de NOT NULL
        $baseAttributes = [
            'descripcion' => "Descripción del evento de prueba",
            'tipo_evento' => 'Conferencia',
            'idioma_principal' => 'Español',
            'fecha_inicio' => now()->addDays(5)->format('Y-m-d'),
            'hora_inicio' => '10:00:00',
            'fecha_fin' => now()->addDays(6)->format('Y-m-d'),
            'hora_fin' => '18:00:00',
            'duracion_horas' => 8,
            'coordenada_y' => -15.0000,
        ];

        // Reemplazamos Evento::factory()->count(N)->create(...) con bucles directos.

        // 1. Emprendedor 1 (3 eventos)
        for ($i = 0; $i < 3; $i++) {
            \App\Models\Evento::create(array_merge($baseAttributes, [
                'nombre' => "E1-Evento " . ($i + 1),
                'id_emprendedor' => $emprendedor1->id,
                'coordenada_x' => -70.0000 + $i * 0.001,
            ]));
        }

        // 2. Emprendedor 2 (2 eventos)
        for ($i = 0; $i < 2; $i++) {
            \App\Models\Evento::create(array_merge($baseAttributes, [
                'nombre' => "E2-Evento " . ($i + 1),
                'id_emprendedor' => $emprendedor2->id,
                'coordenada_x' => -71.0000 + $i * 0.001,
            ]));
        }

        // 3. Emprendedor 3 (4 eventos)
        for ($i = 0; $i < 4; $i++) {
            \App\Models\Evento::create(array_merge($baseAttributes, [
                'nombre' => "E3-Evento " . ($i + 1),
                'id_emprendedor' => $emprendedor3->id,
                'coordenada_x' => -72.0000 + $i * 0.001,
            ]));
        }

        // Act
        $eventos1 = $this->repository->getEventosByEmprendedor($emprendedor1->id);
        $eventos2 = $this->repository->getEventosByEmprendedor($emprendedor2->id);
        $eventos3 = $this->repository->getEventosByEmprendedor($emprendedor3->id);

        // Assert
        $this->assertCount(3, $eventos1);
        $this->assertCount(2, $eventos2);
        $this->assertCount(4, $eventos3);

        foreach ($eventos1 as $evento) {
            $this->assertEquals($emprendedor1->id, $evento->id_emprendedor);
        }
    }

    #[Test]
    public function maneja_eventos_con_fechas_identicas()
    {
        // Arrange
        $fechaComun = now()->addDays(15)->format('Y-m-d');
        $eventos = collect();

        // Reemplazamos Evento::factory()->count(3)->create(...) por un bucle directo.
        for ($i = 0; $i < 3; $i++) {
            $evento = \App\Models\Evento::create([
                'nombre' => "Evento Común " . ($i + 1),
                'descripcion' => "Descripción del evento común",
                'tipo_evento' => 'Conferencia',
                'idioma_principal' => 'Español',
                'fecha_inicio' => $fechaComun,
                'hora_inicio' => '09:00:00', // Asumimos una hora
                'fecha_fin' => $fechaComun,
                'hora_fin' => '17:00:00', // Asumimos una hora
                'duracion_horas' => 8,
                // Usamos un ligero desplazamiento en coordenadas para unicidad
                'coordenada_x' => -70.0000 + $i * 0.001,
                'coordenada_y' => -15.0000 + $i * 0.001,
                'id_emprendedor' => $this->emprendedor->id
            ]);
            $eventos->push($evento);
        }

        // Act
        $eventosActivos = $this->repository->getEventosActivos();
        $proximosEventos = $this->repository->getProximosEventos();

        // Assert
        $this->assertCount(3, $eventosActivos);
        $this->assertCount(3, $proximosEventos);
    }

    #[Test]
    public function puede_manejar_coleccion_vacia()
    {
        // Act
        $todos = $this->repository->getAll();
        $paginados = $this->repository->getPaginated();
        $activos = $this->repository->getEventosActivos();
        $proximos = $this->repository->getProximosEventos();

        // Assert
        $this->assertInstanceOf(Collection::class, $todos);
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginados);
        $this->assertInstanceOf(Collection::class, $activos);
        $this->assertInstanceOf(Collection::class, $proximos);

        $this->assertCount(0, $todos);
        $this->assertEquals(0, $paginados->total());
        $this->assertCount(0, $activos);
        $this->assertCount(0, $proximos);
    }
}
