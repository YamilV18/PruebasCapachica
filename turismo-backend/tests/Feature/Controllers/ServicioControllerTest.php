<?php

namespace Tests\Feature\Controllers;

use App\Models\Servicio;
use App\Models\Emprendedor;
use App\Models\Categoria;
use App\Models\User;
use App\Models\Asociacion;
use App\Models\Municipalidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Laravel\Sanctum\Sanctum;

class ServicioControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $adminUser;
    protected User $normalUser;
    protected Emprendedor $emprendedor;

    protected function setUp(): void
    {
        parent::setUp();

        // ---- Spatie: permisos/roles idempotentes y con guard_name consistente ----
        $this->createPermissions();

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $userRole  = Role::firstOrCreate(['name' => 'user',  'guard_name' => 'web']);

        $adminRole->givePermissionTo([
            'servicio_create', 'servicio_read', 'servicio_update', 'servicio_delete',
        ]);
        $userRole->givePermissionTo(['servicio_read']);

        // ---- Usuarios (anotación @var para call de factory) ----
        /** @var User $admin */
        $admin = User::factory()->createOne();
        $this->adminUser = $admin;

        /** @var User $normal */
        $normal = User::factory()->createOne();
        $this->normalUser = $normal;

        // ---- Emprendedor con dependencias ----
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
    }

    private function createPermissions(): void
    {
        foreach (['servicio_create', 'servicio_read', 'servicio_update', 'servicio_delete'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    #[Test]
    public function puede_listar_todos_los_servicios()
    {
        Servicio::factory()->count(5)->create([
            'emprendedor_id' => $this->emprendedor->id,
        ]);

        $response = $this->getJson('/api/servicios');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'nombre',
                            'descripcion',
                            'precio_referencial',
                            'emprendedor_id',
                            'estado',
                            'capacidad',
                            'latitud',
                            'longitud',
                            'ubicacion_referencia',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'current_page',
                    'per_page',
                    'total',
                ],
            ]);

        $this->assertTrue($response->json('success'));
    }

    #[Test]
    public function puede_mostrar_un_servicio_especifico()
    {
        /** @var Servicio $servicio */
        $servicio = Servicio::factory()->createOne([
            'emprendedor_id' => $this->emprendedor->id,
        ]);

        $response = $this->getJson("/api/servicios/{$servicio->id}");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $servicio->id,
                    'nombre' => $servicio->nombre,
                    'precio_referencial' => (string) $servicio->precio_referencial,
                ],
            ]);
    }

    #[Test]
    public function retorna_404_cuando_servicio_no_existe()
    {
        $response = $this->getJson('/api/servicios/999');

        $response->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJson([
                'success' => false,
                'message' => 'Servicio no encontrado',
            ]);
    }

    #[Test]
    public function admin_puede_crear_nuevo_servicio()
    {
        Sanctum::actingAs($this->adminUser);

        /** @var Categoria $categoria */
        $categoria = Categoria::factory()->createOne();

        $data = [
            'nombre' => 'Tour en Kayak',
            'descripcion' => 'Experiencia única en el lago',
            'precio_referencial' => 45.50,
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => true,
            'capacidad' => 6,
            'latitud' => -15.8422,
            'longitud' => -70.0199,
            'ubicacion_referencia' => 'Muelle Principal',
            'categorias' => [$categoria->id],
        ];

        $response = $this->postJson('/api/servicios', $data);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJson([
                'success' => true,
                'message' => 'Servicio creado exitosamente',
            ]);

        $this->assertDatabaseHas('servicios', [
            'nombre' => $data['nombre'],
            'precio_referencial' => '45.50',
            'emprendedor_id' => $data['emprendedor_id'],
        ]);
    }



    #[Test]
    public function usuario_no_autenticado_no_puede_crear_servicio()
    {
        $data = [
            'nombre' => 'Tour en Kayak',
            'emprendedor_id' => $this->emprendedor->id,
            'precio_referencial' => 45.50,
        ];

        $response = $this->postJson('/api/servicios', $data);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    #[Test]
    public function falla_validacion_al_crear_servicio_sin_datos_requeridos()
    {
        Sanctum::actingAs($this->adminUser);

        $data = [
            'descripcion' => 'Descripción sin nombre',
        ];

        $response = $this->postJson('/api/servicios', $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }



    #[Test]
    public function admin_puede_eliminar_servicio_existente()
    {
        Sanctum::actingAs($this->adminUser);

        /** @var Servicio $servicio */
        $servicio = Servicio::factory()->createOne([
            'emprendedor_id' => $this->emprendedor->id,
        ]);

        $response = $this->deleteJson("/api/servicios/{$servicio->id}");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'message' => 'Servicio eliminado exitosamente',
            ]);

        $this->assertDatabaseMissing('servicios', [
            'id' => $servicio->id,
        ]);
    }

    #[Test]
    public function puede_obtener_servicios_por_emprendedor()
    {
        /** @var Emprendedor $otroEmprendedor */
        $otroEmprendedor = Emprendedor::factory()
            ->for($this->emprendedor->asociacion, 'asociacion')
            ->createOne();

        Servicio::factory()->count(3)->create([
            'emprendedor_id' => $this->emprendedor->id,
        ]);

        Servicio::factory()->count(2)->create([
            'emprendedor_id' => $otroEmprendedor->id,
        ]);

        $response = $this->getJson("/api/servicios/emprendedor/{$this->emprendedor->id}");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson(['success' => true])
            ->assertJsonCount(3, 'data');

        foreach ($response->json('data') as $servicio) {
            $this->assertEquals($this->emprendedor->id, $servicio['emprendedor_id']);
        }
    }

    #[Test]
    public function puede_obtener_servicios_por_categoria()
    {
        /** @var Categoria $categoria */
        $categoria = Categoria::factory()->createOne();
        /** @var Categoria $otraCategoria */
        $otraCategoria = Categoria::factory()->createOne();

        /** @var Servicio $servicio1 */
        $servicio1 = Servicio::factory()->createOne(['emprendedor_id' => $this->emprendedor->id]);
        /** @var Servicio $servicio2 */
        $servicio2 = Servicio::factory()->createOne(['emprendedor_id' => $this->emprendedor->id]);
        /** @var Servicio $servicio3 */
        $servicio3 = Servicio::factory()->createOne(['emprendedor_id' => $this->emprendedor->id]);

        $servicio1->categorias()->attach($categoria->id);
        $servicio2->categorias()->attach($categoria->id);
        $servicio3->categorias()->attach($otraCategoria->id);

        $response = $this->getJson("/api/servicios/categoria/{$categoria->id}");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function puede_obtener_servicios_por_ubicacion()
    {
        Servicio::factory()->createOne([
            'emprendedor_id' => $this->emprendedor->id,
            'latitud' => -15.8422,
            'longitud' => -70.0199,
            'estado' => true,
        ]);

        Servicio::factory()->createOne([
            'emprendedor_id' => $this->emprendedor->id,
            'latitud' => -16.0000, // más lejos
            'longitud' => -71.0000,
            'estado' => true,
        ]);

        $response = $this->getJson('/api/servicios/ubicacion?latitud=-15.8422&longitud=-70.0199&distancia=5');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson(['success' => true]);

        $this->assertGreaterThanOrEqual(1, count($response->json('data') ?? []));
    }

    #[Test]
    public function puede_verificar_disponibilidad_de_servicio()
    {
        /** @var Servicio $servicio */
        $servicio = Servicio::factory()->createOne([
            'emprendedor_id' => $this->emprendedor->id,
        ]);

        $response = $this->getJson("/api/servicios/verificar-disponibilidad?servicio_id={$servicio->id}&fecha=2024-12-25&hora_inicio=09:00:00&hora_fin=11:00:00");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'disponible',
            ]);
    }

    #[Test]
    public function falla_validacion_ubicacion_con_parametros_invalidos()
    {
        $response = $this->getJson('/api/servicios/ubicacion?latitud=invalid&longitud=-70.0199');

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function falla_validacion_disponibilidad_con_parametros_invalidos()
    {
        $response = $this->getJson('/api/servicios/verificar-disponibilidad?servicio_id=999&fecha=invalid-date');

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function precio_referencial_se_almacena_correctamente()
    {
        Sanctum::actingAs($this->adminUser);

        $data = [
            'nombre' => 'Servicio con Precio',
            'precio_referencial' => 123.45,
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => true,
            'capacidad' => 5,
        ];

        $response = $this->postJson('/api/servicios', $data);

        $response->assertStatus(Response::HTTP_CREATED);
        $this->assertDatabaseHas('servicios', [
            'nombre' => 'Servicio con Precio',
            'precio_referencial' => '123.45',
        ]);
    }


    #[Test]
    public function coordenadas_se_almacenan_como_decimal()
    {
        Sanctum::actingAs($this->adminUser);

        $data = [
            'nombre' => 'Servicio con Coordenadas',
            'emprendedor_id' => $this->emprendedor->id,
            'latitud' => -15.8422123,
            'longitud' => -70.0199456,
            'estado' => true,
            'capacidad' => 5,
        ];

        $response = $this->postJson('/api/servicios', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        /** @var Servicio $servicio */
        $servicio = Servicio::query()->latest('id')->firstOrFail();
        $this->assertIsFloat((float) $servicio->latitud);
        $this->assertIsFloat((float) $servicio->longitud);
    }

    #[Test]
    public function puede_crear_servicio_con_categorias_multiples()
    {
        Sanctum::actingAs($this->adminUser);

        /** @var Categoria $categoria1 */
        $categoria1 = Categoria::factory()->createOne();
        /** @var Categoria $categoria2 */
        $categoria2 = Categoria::factory()->createOne();

        $data = [
            'nombre' => 'Servicio Multicategoría',
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => true,
            'capacidad' => 5,
            'categorias' => [$categoria1->id, $categoria2->id],
        ];

        $response = $this->postJson('/api/servicios', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        /** @var Servicio $servicio */
        $servicio = Servicio::query()->latest('id')->firstOrFail();
        $servicio->loadCount('categorias');
        $this->assertEquals(2, $servicio->categorias_count);
    }
}
