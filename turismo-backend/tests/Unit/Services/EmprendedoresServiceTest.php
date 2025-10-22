<?php

namespace Tests\Unit\Services;

use App\Models\Emprendedor;
use App\Models\Asociacion;
use App\Models\Municipalidad;
use App\Models\User;
use App\Models\Servicio;
use App\Services\EmprendedoresService;
use App\Repository\SliderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Mockery;

class EmprendedoresServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected EmprendedoresService $service;
    protected Asociacion $asociacion;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock SliderRepository para evitar dependencias
        $sliderRepositoryMock = Mockery::mock(SliderRepository::class);
        $sliderRepositoryMock->shouldReceive('createMultiple')->andReturn(new \Illuminate\Support\Collection());
        $sliderRepositoryMock->shouldReceive('updateEntitySliders')->andReturn(new \Illuminate\Support\Collection());
        $sliderRepositoryMock->shouldReceive('delete')->andReturn(true);

        $this->service = new EmprendedoresService($sliderRepositoryMock);

        // Crear asociación con municipalidad
        $municipalidad = Municipalidad::factory()->create();
        $this->asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $municipalidad->id
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function puede_obtener_todos_los_emprendedores_paginados()
    {
        // Arrange
        Emprendedor::factory()->count(20)->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        // Act
        $result = $this->service->getAll(10);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(10, $result->perPage());
        $this->assertEquals(20, $result->total());
        $this->assertCount(10, $result->items());

        // Verificar que las relaciones están cargadas
        $this->assertTrue($result->items()[0]->relationLoaded('asociacion'));
    }

    #[Test]
    public function puede_filtrar_emprendedores_por_usuario_actual()
    {
        // Arrange
        $usuario = User::factory()->create();
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('id')->andReturn($usuario->id);

        $emprendedorDelUsuario = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);
        $emprendedorDelUsuario->administradores()->attach($usuario->id, [
            'es_principal' => true,
            'rol' => 'administrador'
        ]);

        $otroEmprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        // Act
        $result = $this->service->getAll(15, true);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(1, $result->total());
        $this->assertEquals($emprendedorDelUsuario->id, $result->items()[0]->id);
    }

    #[Test]
    public function puede_obtener_un_emprendedor_por_id()
    {
        // Arrange
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        // Act
        $result = $this->service->getById($emprendedor->id);

        // Assert
        $this->assertInstanceOf(Emprendedor::class, $result);
        $this->assertEquals($emprendedor->id, $result->id);
        $this->assertEquals($emprendedor->nombre, $result->nombre);

        // Verificar que las relaciones están cargadas
        $this->assertTrue($result->relationLoaded('asociacion'));
        $this->assertTrue($result->relationLoaded('servicios'));
    }

    #[Test]
    public function retorna_null_cuando_emprendedor_no_existe()
    {
        // Act
        $result = $this->service->getById(999);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function puede_crear_nuevo_emprendedor_sin_sliders()
    {
        // Arrange
        $data = [
            'nombre' => 'Restaurante El Lago',
            'tipo_servicio' => 'Restaurante',
            'descripcion' => 'Especialidad en trucha fresca',
            'categoria' => 'Gastronomía',
            'telefono' => '987654321',
            'email' => 'prueba@mail.com',
            'precio_rango' => '$$',
            'horario_atencion' => '9:00 - 21:00',
            'ubicacion' => 'Lago Central, Ciudad',
            'asociacion_id' => $this->asociacion->id,
            'estado' => true
        ];

        // Act
        $result = $this->service->create($data);

        // Assert
        $this->assertInstanceOf(Emprendedor::class, $result);
        $this->assertEquals($data['nombre'], $result->nombre);
        $this->assertEquals($data['categoria'], $result->categoria);
        $this->assertDatabaseHas('emprendedores', [
            'nombre' => $data['nombre'],
            'tipo_servicio' => $data['tipo_servicio']
        ]);
    }

    #[Test]
    public function puede_crear_emprendedor_con_sliders_principales()
    {
        // Arrange
        $data = [
            'nombre' => 'Hotel Vista Hermosa',
            'categoria' => 'Hospedaje',
            'tipo_servicio' => 'Hotel',
            'descripcion' => 'Hotel con vista panorámica al lago',
            'ubicacion' => 'Lago Central, Ciudad',
            'telefono' => '987654321',
            'horario_atencion' => '9:00 - 21:00',
            'precio_rango' => '$$',
            'email' => 'prueba@mail.com',
            'asociacion_id' => $this->asociacion->id,
            'estado' => true,
            'sliders_principales' => [
                [
                    'titulo' => 'Vista al Lago',
                    'descripcion' => 'Hermosa vista panorámica',
                    'imagen' => 'imagen1.jpg',
                    'orden' => 1
                ],
                [
                    'titulo' => 'Habitaciones Cómodas',
                    'descripcion' => 'Habitaciones completamente equipadas',
                    'imagen' => 'imagen2.jpg',
                    'orden' => 2
                ]
            ]
        ];

        // Act
        $result = $this->service->create($data);

        // Assert
        $this->assertInstanceOf(Emprendedor::class, $result);
        $this->assertEquals($data['nombre'], $result->nombre);
    }

    #[Test]
    public function puede_crear_emprendedor_con_sliders_secundarios()
    {
        // Arrange
        $data = [
            'nombre' => 'Artesanías Locales',
            'tipo_servicio' => 'Tienda de Artesanías',
            'descripcion' => 'Productos artesanales únicos',
            'categoria' => 'Artesanías',
            'asociacion_id' => $this->asociacion->id,
            'estado' => true,
            'telefono' => '987654321',
            'email' => 'prueba@mail.com',
            'precio_rango' => '$$',
            'horario_atencion' => '9:00 - 21:00',
            'ubicacion' => 'Lago Central, Ciudad',
            'sliders_secundarios' => [
                [
                    'titulo' => 'Productos Únicos',
                    'descripcion' => 'Artesanías hechas a mano',
                    'imagen' => 'artesania1.jpg',
                    'orden' => 1
                ]
            ]
        ];

        // Act
        $result = $this->service->create($data);

        // Assert
        $this->assertInstanceOf(Emprendedor::class, $result);
        $this->assertEquals($data['nombre'], $result->nombre);
    }

    #[Test]
    public function puede_actualizar_emprendedor_existente()
    {
        // Arrange
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        $data = [
            'nombre' => 'Nombre Actualizado',
            'categoria' => 'Turismo',
            'estado' => false
        ];

        // Act
        $result = $this->service->update($emprendedor->id, $data);

        // Assert
        $this->assertInstanceOf(Emprendedor::class, $result);
        $this->assertEquals('Nombre Actualizado', $result->nombre);
        $this->assertEquals('Turismo', $result->categoria);
        $this->assertFalse($result->estado);

        $this->assertDatabaseHas('emprendedores', [
            'id' => $emprendedor->id,
            'nombre' => 'Nombre Actualizado',
            'categoria' => 'Turismo'
        ]);
    }

    #[Test]
    public function puede_actualizar_emprendedor_con_sliders()
    {
        // Arrange
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        $data = [
            'nombre' => 'Actualizado con Sliders',
            'sliders_principales' => [
                [
                    'titulo' => 'Nuevo Slider Principal',
                    'imagen' => 'nueva_imagen.jpg',
                    'orden' => 1
                ]
            ]
        ];

        // Act
        $result = $this->service->update($emprendedor->id, $data);

        // Assert
        $this->assertInstanceOf(Emprendedor::class, $result);
        $this->assertEquals('Actualizado con Sliders', $result->nombre);
    }

    #[Test]
    public function retorna_null_al_actualizar_emprendedor_inexistente()
    {
        // Arrange
        $data = ['nombre' => 'Test'];

        // Act
        $result = $this->service->update(999, $data);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function puede_eliminar_emprendedor_existente()
    {
        // Arrange
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        // Act
        $result = $this->service->delete($emprendedor->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('emprendedores', ['id' => $emprendedor->id]);
    }

    #[Test]
    public function retorna_false_al_eliminar_emprendedor_inexistente()
    {
        // Act
        $result = $this->service->delete(999);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function puede_buscar_emprendedores_por_categoria()
    {
        // Arrange
        $emprendedoresGastronomia = Emprendedor::factory()->count(3)->gastronomico()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        $emprendedorTurismo = Emprendedor::factory()->turistico()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        // Act
        $result = $this->service->findByCategory('Gastronomía');

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);

        foreach ($result as $emprendedor) {
            $this->assertEquals('Gastronomía', $emprendedor->categoria);
        }
    }

    #[Test]
    public function puede_buscar_emprendedores_por_asociacion()
    {
        // Arrange
        $otraAsociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->asociacion->municipalidad_id
        ]);

        Emprendedor::factory()->count(3)->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        Emprendedor::factory()->count(2)->create([
            'asociacion_id' => $otraAsociacion->id
        ]);

        // Act
        $result = $this->service->findByAsociacion($this->asociacion->id);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);

        foreach ($result as $emprendedor) {
            $this->assertEquals($this->asociacion->id, $emprendedor->asociacion_id);
        }
    }

    #[Test]
    public function puede_buscar_emprendedores_por_texto()
    {
        // Arrange
        $emprendedor1 = Emprendedor::factory()->create([
            'nombre' => 'Restaurante El Lago',
            'descripcion' => 'Especialidad en trucha del lago',
            'asociacion_id' => $this->asociacion->id
        ]);

        $emprendedor2 = Emprendedor::factory()->create([
            'nombre' => 'Hotel Vista Hermosa',
            'descripcion' => 'Hotel con vista panorámica al lago',
            'asociacion_id' => $this->asociacion->id
        ]);

        $emprendedor3 = Emprendedor::factory()->create([
            'nombre' => 'Artesanías Locales',
            'descripcion' => 'Productos artesanales únicos',
            'asociacion_id' => $this->asociacion->id
        ]);

        // Act
        $result = $this->service->search('lago');

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);

        $nombres = $result->pluck('nombre')->toArray();
        $this->assertContains('Restaurante El Lago', $nombres);
        $this->assertContains('Hotel Vista Hermosa', $nombres);
        $this->assertNotContains('Artesanías Locales', $nombres);
    }

    #[Test]
    public function puede_obtener_emprendedor_con_todas_las_relaciones()
    {
        // Arrange
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        // Crear servicios
        Servicio::factory()->count(2)->create([
            'emprendedor_id' => $emprendedor->id
        ]);

        // Act
        $result = $this->service->getWithRelations($emprendedor->id);

        // Assert
        $this->assertInstanceOf(Emprendedor::class, $result);
        $this->assertTrue($result->relationLoaded('asociacion'));
        $this->assertTrue($result->relationLoaded('servicios'));
        $this->assertTrue($result->relationLoaded('slidersPrincipales'));
        $this->assertTrue($result->relationLoaded('slidersSecundarios'));
        $this->assertTrue($result->relationLoaded('administradores'));
    }

    #[Test]
    public function retorna_null_al_obtener_con_relaciones_emprendedor_inexistente()
    {
        // Act
        $result = $this->service->getWithRelations(999);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function puede_obtener_emprendimientos_por_usuario()
    {
        // Arrange
        $usuario = User::factory()->create();
        $otroUsuario = User::factory()->create();

        $emprendedor1 = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);
        $emprendedor2 = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);
        $emprendedor3 = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        // Asociar usuario a dos emprendimientos
        $emprendedor1->administradores()->attach($usuario->id, [
            'es_principal' => true,
            'rol' => 'administrador'
        ]);
        $emprendedor2->administradores()->attach($usuario->id, [
            'es_principal' => false,
            'rol' => 'colaborador'
        ]);

        // Asociar otro usuario al tercer emprendimiento
        $emprendedor3->administradores()->attach($otroUsuario->id, [
            'es_principal' => true,
            'rol' => 'administrador'
        ]);

        // Act
        $result = $this->service->getByUserId($usuario->id);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);

        $ids = $result->pluck('id')->toArray();
        $this->assertContains($emprendedor1->id, $ids);
        $this->assertContains($emprendedor2->id, $ids);
        $this->assertNotContains($emprendedor3->id, $ids);
    }

    #[Test]
    public function puede_verificar_si_usuario_es_administrador()
    {
        // Arrange
        $usuario = User::factory()->create();
        $otroUsuario = User::factory()->create();

        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        $emprendedor->administradores()->attach($usuario->id, [
            'es_principal' => false,
            'rol' => 'administrador'
        ]);

        // Act & Assert
        $this->assertTrue($this->service->esAdministrador($emprendedor->id, $usuario->id));
        $this->assertFalse($this->service->esAdministrador($emprendedor->id, $otroUsuario->id));
    }

    #[Test]
    public function puede_verificar_si_usuario_es_administrador_principal()
    {
        // Arrange
        $usuarioPrincipal = User::factory()->create();
        $usuarioSecundario = User::factory()->create();

        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        $emprendedor->administradores()->attach([
            $usuarioPrincipal->id => [
                'es_principal' => true,
                'rol' => 'administrador'
            ],
            $usuarioSecundario->id => [
                'es_principal' => false,
                'rol' => 'colaborador'
            ]
        ]);

        // Act & Assert
        $this->assertTrue($this->service->esAdministradorPrincipal($emprendedor->id, $usuarioPrincipal->id));
        $this->assertFalse($this->service->esAdministradorPrincipal($emprendedor->id, $usuarioSecundario->id));
    }

    #[Test]
    public function maneja_transacciones_correctamente_en_creacion()
    {
        // Arrange
        $data = [
            'nombre' => 'Emprendedor Transaccional',
            'tipo_servicio' => 'Restaurante',
            'descripcion' => 'Especialidad en trucha fresca',
            'categoria' => 'Gastronomía',
            'telefono' => '987654321',
            'email' => 'prueba@mail.com',
            'precio_rango' => '$$',
            'horario_atencion' => '9:00 - 21:00',
            'ubicacion' => 'Lago Central, Ciudad',
            'asociacion_id' => $this->asociacion->id,
            'estado' => true
        ];

        // Act
        $result = $this->service->create($data);

        // Assert
        $this->assertInstanceOf(Emprendedor::class, $result);
        $this->assertDatabaseHas('emprendedores', [
            'nombre' => 'Emprendedor Transaccional'
        ]);
    }

    #[Test]
    public function maneja_transacciones_correctamente_en_actualizacion()
    {
        // Arrange
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        $data = ['nombre' => 'Actualizado con Transacción'];

        // Act
        $result = $this->service->update($emprendedor->id, $data);

        // Assert
        $this->assertInstanceOf(Emprendedor::class, $result);
        $this->assertDatabaseHas('emprendedores', [
            'id' => $emprendedor->id,
            'nombre' => 'Actualizado con Transacción'
        ]);
    }

    #[Test]
    public function maneja_transacciones_correctamente_en_eliminacion()
    {
        // Arrange
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        // Act
        $result = $this->service->delete($emprendedor->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('emprendedores', ['id' => $emprendedor->id]);
    }

    #[Test]
    public function puede_usar_paginacion_con_diferentes_tamaños()
    {
        // Arrange
        Emprendedor::factory()->count(25)->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        // Act
        $resultados5 = $this->service->getAll(5);
        $resultados10 = $this->service->getAll(10);
        $resultados15 = $this->service->getAll(); // Default

        // Assert
        $this->assertEquals(5, $resultados5->perPage());
        $this->assertEquals(10, $resultados10->perPage());
        $this->assertEquals(15, $resultados15->perPage());

        $this->assertEquals(25, $resultados5->total());
        $this->assertEquals(25, $resultados10->total());
        $this->assertEquals(25, $resultados15->total());
    }
}
