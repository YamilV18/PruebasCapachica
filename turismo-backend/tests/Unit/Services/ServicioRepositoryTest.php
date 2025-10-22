<?php

namespace Tests\Unit\Services;

use App\Models\Servicio;
use App\Models\Emprendedor;
use App\Models\Categoria;
use App\Models\Asociacion;
use App\Models\Municipalidad;
use App\Repository\ServicioRepository;
use App\Repository\SliderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Mockery;

class ServicioRepositoryTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected ServicioRepository $repository;
    protected Emprendedor $emprendedor;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock SliderRepository para evitar dependencias
        $sliderRepositoryMock = Mockery::mock(SliderRepository::class);
        $sliderRepositoryMock->shouldReceive('createMultiple')->andReturn(true);
        $sliderRepositoryMock->shouldReceive('updateEntitySliders')->andReturn(true);
        $sliderRepositoryMock->shouldReceive('delete')->andReturn(true);
        
        $this->repository = new ServicioRepository(new Servicio(), $sliderRepositoryMock);
        
        // Crear emprendedor con dependencias
        $municipalidad = Municipalidad::factory()->create();
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $municipalidad->id
        ]);
        $this->emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $asociacion->id
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function puede_obtener_todos_los_servicios()
    {
        // Arrange
        Servicio::factory()->count(5)->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $result = $this->repository->getAll();

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(5, $result);
        
        // Verificar que las relaciones están cargadas
        $this->assertTrue($result->first()->relationLoaded('emprendedor'));
        $this->assertTrue($result->first()->relationLoaded('categorias'));
        $this->assertTrue($result->first()->relationLoaded('horarios'));
    }

    #[Test]
    public function puede_obtener_servicios_paginados()
    {
        // Arrange
        Servicio::factory()->count(20)->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $result = $this->repository->getPaginated(10);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(10, $result->perPage());
        $this->assertEquals(20, $result->total());
        $this->assertCount(10, $result->items());
        
        // Verificar que las relaciones están cargadas
        $this->assertTrue($result->items()[0]->relationLoaded('emprendedor'));
    }

    #[Test]
    public function puede_obtener_un_servicio_por_id()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $result = $this->repository->findById($servicio->id);

        // Assert
        $this->assertInstanceOf(Servicio::class, $result);
        $this->assertEquals($servicio->id, $result->id);
        $this->assertEquals($servicio->nombre, $result->nombre);
        
        // Verificar que todas las relaciones están cargadas
        $this->assertTrue($result->relationLoaded('emprendedor'));
        $this->assertTrue($result->relationLoaded('categorias'));
        $this->assertTrue($result->relationLoaded('horarios'));
        $this->assertTrue($result->relationLoaded('sliders'));
    }

    #[Test]
    public function retorna_null_cuando_servicio_no_existe()
    {
        // Act
        $result = $this->repository->findById(999);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function puede_crear_nuevo_servicio_sin_relaciones()
    {
        // Arrange
        $data = [
            'nombre' => 'Tour en Kayak',
            'descripcion' => 'Experiencia única',
            'precio_referencial' => 45.50,
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => true,
            'capacidad' => 6
        ];

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertInstanceOf(Servicio::class, $result);
        $this->assertEquals($data['nombre'], $result->nombre);
        $this->assertEquals($data['precio_referencial'], $result->precio_referencial);
        $this->assertDatabaseHas('servicios', [
            'nombre' => $data['nombre'],
            'emprendedor_id' => $data['emprendedor_id']
        ]);
    }

    #[Test]
    public function puede_crear_servicio_con_categorias()
    {
        // Arrange
        $categorias = Categoria::factory()->count(2)->create();
        $data = [
            'nombre' => 'Servicio con Categorías',
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => true,
            'capacidad' => 5
        ];

        // Act
        $result = $this->repository->create($data, $categorias->pluck('id')->toArray());

        // Assert
        $this->assertInstanceOf(Servicio::class, $result);
        $this->assertCount(2, $result->categorias);
        
        foreach ($categorias as $categoria) {
            $this->assertTrue($result->categorias->contains('id', $categoria->id));
        }
    }

    #[Test]
    public function puede_crear_servicio_con_horarios()
    {
        // Arrange
        $data = [
            'nombre' => 'Servicio con Horarios',
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => true,
            'capacidad' => 5
        ];
        
        $horarios = [
            [
                'dia_semana' => 'lunes',
                'hora_inicio' => '09:00:00',
                'hora_fin' => '17:00:00',
                'activo' => true
            ],
            [
                'dia_semana' => 'martes',
                'hora_inicio' => '10:00:00',
                'hora_fin' => '16:00:00',
                'activo' => true
            ]
        ];

        // Act
        $result = $this->repository->create($data, [], $horarios);

        // Assert
        $this->assertInstanceOf(Servicio::class, $result);
        $this->assertCount(2, $result->horarios);
    }

    #[Test]
    public function puede_actualizar_servicio_existente()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);
        
        $data = [
            'nombre' => 'Nombre Actualizado',
            'precio_referencial' => 75.00,
            'estado' => false
        ];

        // Act
        $result = $this->repository->update($servicio->id, $data);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseHas('servicios', [
            'id' => $servicio->id,
            'nombre' => 'Nombre Actualizado',
            'precio_referencial' => 75.00,
            'estado' => false
        ]);
    }

    #[Test]
    public function puede_actualizar_servicio_con_categorias()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);
        
        $categoriasIniciales = Categoria::factory()->count(2)->create();
        $servicio->categorias()->attach($categoriasIniciales->pluck('id'));
        
        $nuevasCategorias = Categoria::factory()->count(3)->create();
        $data = ['nombre' => 'Actualizado'];

        // Act
        $result = $this->repository->update($servicio->id, $data, $nuevasCategorias->pluck('id')->toArray());

        // Assert
        $this->assertTrue($result);
        
        $servicioActualizado = $this->repository->findById($servicio->id);
        $this->assertCount(3, $servicioActualizado->categorias);
        
        foreach ($nuevasCategorias as $categoria) {
            $this->assertTrue($servicioActualizado->categorias->contains('id', $categoria->id));
        }
    }

    #[Test]
    public function retorna_false_al_actualizar_servicio_inexistente()
    {
        // Arrange
        $data = ['nombre' => 'Test'];

        // Act
        $result = $this->repository->update(999, $data);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function puede_eliminar_servicio_existente()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $result = $this->repository->delete($servicio->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('servicios', ['id' => $servicio->id]);
    }

    #[Test]
    public function retorna_false_al_eliminar_servicio_inexistente()
    {
        // Act
        $result = $this->repository->delete(999);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function puede_obtener_servicios_activos()
    {
        // Arrange
        Servicio::factory()->count(3)->activo()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);
        Servicio::factory()->count(2)->inactivo()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $result = $this->repository->getActiveServicios();

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);
        
        foreach ($result as $servicio) {
            $this->assertTrue($servicio->estado);
        }
    }

    #[Test]
    public function puede_obtener_servicios_por_emprendedor()
    {
        // Arrange
        $otroEmprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->emprendedor->asociacion_id
        ]);
        
        Servicio::factory()->count(3)->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);
        
        Servicio::factory()->count(2)->create([
            'emprendedor_id' => $otroEmprendedor->id
        ]);

        // Act
        $result = $this->repository->getServiciosByEmprendedor($this->emprendedor->id);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);
        
        foreach ($result as $servicio) {
            $this->assertEquals($this->emprendedor->id, $servicio->emprendedor_id);
        }
    }

    #[Test]
    public function puede_obtener_servicios_por_categoria()
    {
        // Arrange
        $categoria = Categoria::factory()->create();
        $otraCategoria = Categoria::factory()->create();
        
        $servicio1 = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);
        $servicio2 = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);
        $servicio3 = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Asociar servicios a categorías
        $servicio1->categorias()->attach($categoria->id);
        $servicio2->categorias()->attach($categoria->id);
        $servicio3->categorias()->attach($otraCategoria->id);

        // Act
        $result = $this->repository->getServiciosByCategoria($categoria->id);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
        
        $serviciosIds = $result->pluck('id')->toArray();
        $this->assertContains($servicio1->id, $serviciosIds);
        $this->assertContains($servicio2->id, $serviciosIds);
        $this->assertNotContains($servicio3->id, $serviciosIds);
    }

    #[Test]
    public function puede_obtener_servicios_por_ubicacion()
    {
        // Arrange
        $serviciosCercanos = Servicio::factory()->count(2)->create([
            'emprendedor_id' => $this->emprendedor->id,
            'latitud' => -15.8422,
            'longitud' => -70.0199,
            'estado' => true
        ]);
        
        $servicioLejano = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id,
            'latitud' => -16.5000, // Muy lejos
            'longitud' => -71.5000,
            'estado' => true
        ]);

        // Act
        $result = $this->repository->getServiciosByUbicacion(-15.8422, -70.0199, 10);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThanOrEqual(2, $result->count());
        
        // Todos los servicios retornados deben estar activos
        foreach ($result as $servicio) {
            $this->assertTrue($servicio->estado);
        }
    }

    #[Test]
    public function puede_verificar_disponibilidad_de_servicio()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $result = $this->repository->verificarDisponibilidad(
            $servicio->id,
            '2024-12-25',
            '09:00:00',
            '11:00:00'
        );

        // Assert
        $this->assertIsBool($result);
    }

    #[Test]
    public function retorna_false_al_verificar_disponibilidad_de_servicio_inexistente()
    {
        // Act
        $result = $this->repository->verificarDisponibilidad(
            999,
            '2024-12-25',
            '09:00:00',
            '11:00:00'
        );

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function puede_usar_paginacion_con_diferentes_tamaños()
    {
        // Arrange
        Servicio::factory()->count(25)->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

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
    public function maneja_transacciones_correctamente_en_creacion()
    {
        // Arrange
        $data = [
            'nombre' => 'Servicio Transaccional',
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => true,
            'capacidad' => 5
        ];

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertInstanceOf(Servicio::class, $result);
        $this->assertDatabaseHas('servicios', [
            'nombre' => 'Servicio Transaccional'
        ]);
    }

    #[Test]
    public function maneja_transacciones_correctamente_en_actualizacion()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);
        
        $data = ['nombre' => 'Actualizado con Transacción'];

        // Act
        $result = $this->repository->update($servicio->id, $data);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseHas('servicios', [
            'id' => $servicio->id,
            'nombre' => 'Actualizado con Transacción'
        ]);
    }

    #[Test]
    public function maneja_transacciones_correctamente_en_eliminacion()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $result = $this->repository->delete($servicio->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('servicios', ['id' => $servicio->id]);
    }
}