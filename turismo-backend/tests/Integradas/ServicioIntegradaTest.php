<?php

namespace Tests\Integradas;

use App\Models\Servicio;
use App\Models\Emprendedor;
use App\Models\Categoria;
use App\Models\User;
use App\Models\Asociacion;
use App\Models\Municipalidad;
use App\Repository\ServicioRepository;
use App\Repository\SliderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Laravel\Sanctum\Sanctum;
use Mockery;

/**
 * Prueba de Integración Completa para el ciclo de vida de Servicio
 * (Model, Repository, y Controller).
 */
class ServicioIntegradaTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $adminUser;
    protected User $normalUser;
    protected Emprendedor $emprendedor;
    protected ServicioRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Configuración de Mock para el Repository (Para ServicioRepository)
        // Se necesita mockear SliderRepository para no depender de él en las pruebas
        $sliderRepositoryMock = Mockery::mock(SliderRepository::class);
        $sliderRepositoryMock->shouldReceive('createMultiple')->andReturn(true);
        $sliderRepositoryMock->shouldReceive('updateEntitySliders')->andReturn(true);
        $sliderRepositoryMock->shouldReceive('delete')->andReturn(true);

        $this->repository = new ServicioRepository(new Servicio(), $sliderRepositoryMock);

        // 2. Creación de Entidades Base (Emprendedor/Asociacion/Municipalidad)
        /** @var Municipalidad $municipalidad */
        $municipalidad = Municipalidad::factory()->createOne();

        /** @var Asociacion $asociacion */
        $asociacion = Asociacion::factory()
            ->for($municipalidad, 'municipalidad')
            ->createOne();

        /** @var Emprendedor $emp */
        $emp = Emprendedor::factory()
            ->for($asociacion, 'asociacion')
            ->createOne();

        $this->emprendedor = $emp;

        // 3. Configuración de Usuarios y Permisos (Para ServicioControllerTest)
        $this->createPermissions();

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $userRole  = Role::firstOrCreate(['name' => 'user',  'guard_name' => 'web']);

        $adminRole->givePermissionTo([
            'servicio_create', 'servicio_read', 'servicio_update', 'servicio_delete',
        ]);
        $userRole->givePermissionTo(['servicio_read']);

        /** @var User $admin */
        $admin = User::factory()->createOne();
        $admin->assignRole('admin'); // Asignar el rol de admin
        $this->adminUser = $admin;

        /** @var User $normal */
        $normal = User::factory()->createOne();
        $normal->assignRole('user'); // Asignar el rol de usuario normal
        $this->normalUser = $normal;
    }

    protected function tearDown(): void
    {
        // Limpiar Mockery
        Mockery::close();
        parent::tearDown();
    }

    private function createPermissions(): void
    {
        foreach (['servicio_create', 'servicio_read', 'servicio_update', 'servicio_delete'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    // ========================================================================
    // MODEL UNIT TESTS (ServicioTest.php)
    // ========================================================================

    #[Test]
    public function modelo_puede_crear_servicio_con_datos_validos()
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
        $this->assertDatabaseHas('servicios', $data);
    }

    #[Test]
    public function modelo_relacion_emprendedor_funciona_correctamente()
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
    }

    #[Test]
    public function modelo_relacion_categorias_funciona_correctamente()
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
        $this->assertTrue($categoriasRelacionadas->contains('id', $categorias->first()->id));
    }

    #[Test]
    public function modelo_relacion_horarios_existe_y_funciona()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $horariosRelation = $servicio->horarios();

        // Assert
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $horariosRelation);
    }

    // ========================================================================
    // REPOSITORY UNIT TESTS (ServicioRepositoryTest.php)
    // ========================================================================

    #[Test]
    public function repositorio_puede_obtener_todos_los_servicios()
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
        $this->assertTrue($result->first()->relationLoaded('emprendedor'));
    }

    #[Test]
    public function repositorio_puede_obtener_servicios_paginados()
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
    }

    #[Test]
    public function repositorio_puede_crear_nuevo_servicio_con_relaciones()
    {
        // Arrange
        $categorias = Categoria::factory()->count(2)->create();
        $horarios = [
            [
                'dia_semana' => 'lunes',
                'hora_inicio' => '09:00:00',
                'hora_fin' => '17:00:00',
                'activo' => true
            ]
        ];

        $data = [
            'nombre' => 'Servicio Con Todo',
            'descripcion' => 'Prueba de integración total',
            'precio_referencial' => 99.99,
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => true,
            'capacidad' => 10
        ];

        // Act
        $result = $this->repository->create(
            $data,
            $categorias->pluck('id')->toArray(),
            $horarios
        );

        // Assert
        $this->assertInstanceOf(Servicio::class, $result);
        $this->assertEquals($data['nombre'], $result->nombre);
        $this->assertDatabaseHas('servicios', ['nombre' => $data['nombre']]);
        $this->assertCount(2, $result->categorias); // Relación Many-to-Many
        $this->assertCount(1, $result->horarios);   // Relación One-to-Many
    }

    #[Test]
    public function repositorio_puede_actualizar_servicio_existente_con_nuevas_categorias()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);
        $categoriasIniciales = Categoria::factory()->count(2)->create();
        $servicio->categorias()->attach($categoriasIniciales->pluck('id'));

        $nuevasCategorias = Categoria::factory()->count(3)->create();
        $data = ['nombre' => 'Actualizado con Categorías'];

        // Act
        $result = $this->repository->update(
            $servicio->id,
            $data,
            $nuevasCategorias->pluck('id')->toArray()
        );

        // Assert
        $this->assertTrue($result);
        $servicioActualizado = $this->repository->findById($servicio->id);
        $this->assertEquals('Actualizado con Categorías', $servicioActualizado->nombre);
        $this->assertCount(3, $servicioActualizado->categorias);
    }

    // ========================================================================
    // FEATURE/CONTROLLER TESTS (ServicioControllerTest.php)
    // ========================================================================

    #[Test]
    public function controller_puede_listar_todos_los_servicios_publicamente()
    {
        // Arrange: Servicio::factory() y Emprendedor::factory() ya se han creado en setUp()
        Servicio::factory()->count(5)->create([
            'emprendedor_id' => $this->emprendedor->id,
        ]);

        // Act
        $response = $this->getJson('/api/servicios');

        // Assert
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => ['*' => ['id', 'nombre', 'emprendedor_id']],
                ],
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertCount(5, $response->json('data.data'));
    }

    #[Test]
    public function controller_admin_puede_crear_nuevo_servicio_con_categorias()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        /** @var Categoria $categoria */
        $categoria = Categoria::factory()->createOne();

        $data = [
            'nombre' => 'Tour en Kayak (Controller)',
            'descripcion' => 'Experiencia única en el lago',
            'precio_referencial' => 45.50,
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => true,
            'capacidad' => 6,
            'latitud' => -15.8422,
            'longitud' => -70.0199,
            'ubicacion_referencia' => 'Muelle Principal',
            'categorias' => [$categoria->id], // Se maneja en el Repository
        ];

        // Act
        $response = $this->postJson('/api/servicios', $data);

        // Assert
        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJson([
                'success' => true,
                'message' => 'Servicio creado exitosamente',
            ]);

        // Verificación de la integración:
        // 1. Base de datos (Model/Repository)
        $this->assertDatabaseHas('servicios', [
            'nombre' => $data['nombre'],
            'emprendedor_id' => $data['emprendedor_id'],
        ]);

        // 2. Relación guardada (Model/Repository)
        $servicioCreado = Servicio::latest('id')->first();
        $this->assertTrue($servicioCreado->categorias->contains('id', $categoria->id));
    }

    #[Test]
    public function controller_usuario_no_autenticado_no_puede_crear_servicio()
    {
        // Arrange
        $data = [
            'nombre' => 'Servicio No Autorizado',
            'emprendedor_id' => $this->emprendedor->id,
            'precio_referencial' => 45.50,
        ];

        // Act
        $response = $this->postJson('/api/servicios', $data);

        // Assert
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
        $this->assertDatabaseMissing('servicios', ['nombre' => $data['nombre']]);
    }

    #[Test]
    public function controller_admin_puede_eliminar_servicio_existente()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        /** @var Servicio $servicio */
        $servicio = Servicio::factory()->createOne([
            'emprendedor_id' => $this->emprendedor->id,
        ]);

        // Act
        $response = $this->deleteJson("/api/servicios/{$servicio->id}");

        // Assert
        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'message' => 'Servicio eliminado exitosamente',
            ]);

        // Verificación de la integración:
        // El servicio ha sido eliminado de la base de datos (Repository/Model)
        $this->assertDatabaseMissing('servicios', [
            'id' => $servicio->id
        ]);

        $this->assertNull(Servicio::find($servicio->id));
    }
}
