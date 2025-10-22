<?php

namespace Tests\Feature\Controllers;

use App\Models\Emprendedor;
use App\Models\Asociacion;
use App\Models\Municipalidad;
use App\Models\User;
use App\Models\Servicio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Laravel\Sanctum\Sanctum;

class EmprendedorApiControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $adminUser;
    protected User $normalUser;
    protected User $emprendedorUser;
    protected Asociacion $asociacion;
    protected Municipalidad $municipalidad;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear permisos necesarios
        $this->createPermissions();

        // Crear roles
        $adminRole = Role::create(['name' => 'admin']);
        $userRole = Role::create(['name' => 'user']);
        $emprendedorRole = Role::create(['name' => 'emprendedor']);

        // Asignar permisos a roles
        $adminRole->givePermissionTo([
            'emprendedor_create', 'emprendedor_read', 'emprendedor_update', 'emprendedor_delete'
        ]);
        $userRole->givePermissionTo(['emprendedor_read']);
        $emprendedorRole->givePermissionTo(['emprendedor_read', 'emprendedor_update']);

        // Crear usuarios
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->normalUser = User::factory()->create();
        $this->normalUser->assignRole('user');

        $this->emprendedorUser = User::factory()->create();
        $this->emprendedorUser->assignRole('emprendedor');

        // Crear municipalidad y asociación
        $this->municipalidad = Municipalidad::factory()->create();
        $this->asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);
    }

    private function createPermissions(): void
    {
        $permissions = [
            'emprendedor_create', 'emprendedor_read', 'emprendedor_update', 'emprendedor_delete'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }

    #[Test]
    public function puede_listar_todos_los_emprendedores()
    {
        // Arrange
        Emprendedor::factory()->count(5)->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        // Act
        $response = $this->getJson('/api/emprendedores');

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'nombre',
                                'tipo_servicio',
                                'descripcion',
                                'ubicacion',
                                'telefono',
                                'email',
                                'categoria',
                                'estado',
                                'asociacion_id',
                                'created_at',
                                'updated_at'
                            ]
                        ],
                        'current_page',
                        'per_page',
                        'total'
                    ]
                ]);

        $this->assertTrue($response->json('success'));
    }

    #[Test]
    public function puede_mostrar_un_emprendedor_especifico()
    {
        // Arrange
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        // Act
        $response = $this->getJson("/api/emprendedores/{$emprendedor->id}");

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $emprendedor->id,
                        'nombre' => $emprendedor->nombre,
                        'categoria' => $emprendedor->categoria
                    ]
                ]);
    }

    #[Test]
    public function retorna_404_cuando_emprendedor_no_existe()
    {
        // Act
        $response = $this->getJson('/api/emprendedores/999');

        // Assert
        $response->assertStatus(Response::HTTP_NOT_FOUND)
                ->assertJson([
                    'success' => false,
                    'message' => 'Emprendedor no encontrado'
                ]);
    }

    #[Test]
    public function admin_puede_crear_nuevo_emprendedor()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);

        $data = [
            'nombre' => 'Restaurante El Lago',
            'tipo_servicio' => 'Restaurante',
            'descripcion' => 'Especialidad en trucha fresca',
            'ubicacion' => 'Av. Principal 123',
            'telefono' => '987654321',
            'email' => 'restaurante@ellago.com',
            'categoria' => 'Gastronomía',
            'precio_rango' => 'S/ 50 - S/ 100',
            'metodos_pago' => ['efectivo', 'tarjeta_credito'],
            'capacidad_aforo' => 80,
            'numero_personas_atiende' => 25,
            'horario_atencion' => 'Lunes a Domingo: 11:00 AM - 10:00 PM',
            'idiomas_hablados' => 'español, inglés',
            'certificaciones' => ['DIRCETUR', 'Certificado sanitario'],
            'opciones_acceso' => 'vehiculo_propio, transporte_publico',
            'facilidades_discapacidad' => true,
            'asociacion_id' => $this->asociacion->id,
            'estado' => true
        ];

        // Act
        $response = $this->postJson('/api/emprendedores', $data);

        // Assert
        $response->assertStatus(Response::HTTP_CREATED)
                ->assertJson([
                    'success' => true,
                    'message' => 'Emprendedor creado exitosamente'
                ]);

        $this->assertDatabaseHas('emprendedores', [
            'nombre' => $data['nombre'],
            'email' => $data['email'],
            'categoria' => $data['categoria']
        ]);
    }

    #[Test]
    public function usuario_normal_no_puede_crear_emprendedor()
    {
        // Arrange
        Sanctum::actingAs($this->normalUser);

        $data = [
            'nombre' => 'Test Emprendedor',
            'asociacion_id' => $this->asociacion->id
        ];

        // Act
        $response = $this->postJson('/api/emprendedores', $data);

        // Assert
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    #[Test]
    public function usuario_no_autenticado_no_puede_crear_emprendedor()
    {
        // Arrange
        $data = [
            'nombre' => 'Test Emprendedor',
            'asociacion_id' => $this->asociacion->id
        ];

        // Act
        $response = $this->postJson('/api/emprendedores', $data);

        // Assert
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    #[Test]
    public function admin_puede_actualizar_emprendedor_existente()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);

        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        $updateData = [
            'nombre' => 'Nombre Actualizado',
            'categoria' => 'Turismo',
            'estado' => false
        ];

        // Act
        $response = $this->putJson("/api/emprendedores/{$emprendedor->id}", $updateData);

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true,
                    'message' => 'Emprendedor actualizado exitosamente'
                ]);

        $this->assertDatabaseHas('emprendedores', [
            'id' => $emprendedor->id,
            'nombre' => 'Nombre Actualizado',
            'categoria' => 'Turismo',
            'estado' => false
        ]);
    }

    #[Test]
    public function emprendedor_propietario_puede_actualizar_su_negocio()
    {
        // Arrange
        Sanctum::actingAs($this->emprendedorUser);

        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        // Asociar el usuario como administrador principal
        $emprendedor->administradores()->attach($this->emprendedorUser->id, [
            'es_principal' => true,
            'rol' => 'administrador'
        ]);

        $updateData = [
            'descripcion' => 'Nueva descripción del negocio',
            'telefono' => '999888777'
        ];

        // Act
        $response = $this->putJson("/api/emprendedores/{$emprendedor->id}", $updateData);

        // Assert
        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas('emprendedores', [
            'id' => $emprendedor->id,
            'descripcion' => 'Nueva descripción del negocio',
            'telefono' => '999888777'
        ]);
    }

    #[Test]
    public function retorna_404_al_actualizar_emprendedor_inexistente()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);

        $updateData = [
            'nombre' => 'Test'
        ];

        // Act
        $response = $this->putJson('/api/emprendedores/999', $updateData);

        // Assert
        $response->assertStatus(Response::HTTP_NOT_FOUND)
                ->assertJson([
                    'success' => false,
                    'message' => 'Emprendedor no encontrado'
                ]);
    }

    #[Test]
    public function admin_puede_eliminar_emprendedor_existente()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);

        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        // Act
        $response = $this->deleteJson("/api/emprendedores/{$emprendedor->id}");

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true,
                    'message' => 'Emprendedor eliminado exitosamente'
                ]);

        $this->assertDatabaseMissing('emprendedores', [
            'id' => $emprendedor->id
        ]);
    }

    #[Test]
    public function puede_obtener_emprendedores_por_categoria()
    {
        // Arrange
        $emprendedoresGastronomia = Emprendedor::factory()->count(3)->gastronomico()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        $emprendedorTurismo = Emprendedor::factory()->turistico()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        // Act
        $response = $this->getJson('/api/emprendedores/categoria/Gastronomía');

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonCount(3, 'data');

        foreach ($response->json('data') as $emprendedor) {
            $this->assertEquals('Gastronomía', $emprendedor['categoria']);
        }
    }

    #[Test]
    public function puede_obtener_emprendedores_por_asociacion()
    {
        // Arrange
        $otraAsociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);

        Emprendedor::factory()->count(3)->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        Emprendedor::factory()->count(2)->create([
            'asociacion_id' => $otraAsociacion->id
        ]);

        // Act
        $response = $this->getJson("/api/emprendedores/asociacion/{$this->asociacion->id}");

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonCount(3, 'data');

        foreach ($response->json('data') as $emprendedor) {
            $this->assertEquals($this->asociacion->id, $emprendedor['asociacion_id']);
        }
    }

    #[Test]
    public function puede_buscar_emprendedores_por_texto()
    {
        // Arrange
        $emprendedor1 = Emprendedor::factory()->create([
            'nombre' => 'Restaurante El Lago',
            'descripcion' => 'Especialidad en trucha',
            'asociacion_id' => $this->asociacion->id
        ]);

        $emprendedor2 = Emprendedor::factory()->create([
            'nombre' => 'Hotel Vista Hermosa',
            'descripcion' => 'Hotel con vista al lago',
            'asociacion_id' => $this->asociacion->id
        ]);

        $emprendedor3 = Emprendedor::factory()->create([
            'nombre' => 'Artesanías Locales',
            'descripcion' => 'Productos artesanales únicos',
            'asociacion_id' => $this->asociacion->id
        ]);

        // Act
        $response = $this->getJson('/api/emprendedores/search?q=lago');

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonCount(2, 'data');

        $nombres = collect($response->json('data'))->pluck('nombre')->toArray();
        $this->assertContains('Restaurante El Lago', $nombres);
        $this->assertContains('Hotel Vista Hermosa', $nombres);
        $this->assertNotContains('Artesanías Locales', $nombres);
    }

    #[Test]
    public function busqueda_requiere_parametro_q()
    {
        // Act
        $response = $this->getJson('/api/emprendedores/search');

        // Assert
        $response->assertStatus(Response::HTTP_BAD_REQUEST)
                ->assertJson([
                    'success' => false,
                    'message' => 'Parámetro de búsqueda requerido'
                ]);
    }

    #[Test]
    public function puede_obtener_servicios_de_emprendedor()
    {
        // Arrange
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        $servicios = Servicio::factory()->count(3)->create([
            'emprendedor_id' => $emprendedor->id
        ]);

        // Act
        $response = $this->getJson("/api/emprendedores/{$emprendedor->id}/servicios");

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonCount(3, 'data');

        foreach ($response->json('data') as $servicio) {
            $this->assertEquals($emprendedor->id, $servicio['emprendedor_id']);
        }
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
        $response = $this->getJson("/api/emprendedores/{$emprendedor->id}");

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'nombre',
                        'asociacion',
                        'servicios'
                    ]
                ]);
    }

    #[Test]
    public function admin_puede_agregar_administrador_a_emprendimiento()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);

        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        $nuevoAdmin = User::factory()->create();

        $data = [
            'email' => $nuevoAdmin->email,
            'rol' => 'administrador',
            'es_principal' => false
        ];

        // Act
        $response = $this->postJson("/api/emprendedores/{$emprendedor->id}/administradores", $data);

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true,
                    'message' => 'Administrador agregado correctamente'
                ]);

        $this->assertDatabaseHas('user_emprendedor', [
            'user_id' => $nuevoAdmin->id,
            'emprendedor_id' => $emprendedor->id,
            'rol' => 'administrador'
        ]);
    }

    #[Test]
    public function arrays_se_almacenan_correctamente()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);

        $data = [
            'nombre' => 'Emprendedor con Arrays',
            'tipo_servicio' => 'Restaurante',
            'descripcion' => 'Especialidad en trucha fresca',
            'ubicacion' => 'Av. Principal 123',
            'telefono' => '987654321',
            'email' => 'restaurante@ellago.com',
            'categoria' => 'Gastronomía',
            'precio_rango' => 'S/ 30 - S/ 80',
            'horario_atencion' => 'Lunes a Domingo: 11:00 AM - 10:00 PM',
            'metodos_pago' => ['efectivo', 'tarjeta_credito', 'yape'],
            'idiomas_hablados' => ['español', 'inglés', 'quechua'],
            'certificaciones' => ['CALTUR', 'DIRCETUR'],
            'opciones_acceso' => 'vehiculo_propio, transporte_publico',
            'asociacion_id' => $this->asociacion->id,
            'estado' => true
        ];

        // Act
        $response = $this->postJson('/api/emprendedores', $data);

        // Assert
        $response->assertStatus(Response::HTTP_CREATED);

        $emprendedor = Emprendedor::latest()->first();
        $this->assertEquals(['efectivo', 'tarjeta_credito', 'yape'], $emprendedor->metodos_pago);
        $this->assertEquals(['español', 'inglés', 'quechua'], $emprendedor->idiomas_hablados);
        $this->assertEquals(['CALTUR', 'DIRCETUR'], $emprendedor->certificaciones);
    }

    #[Test]
    public function facilidades_discapacidad_se_almacena_como_booleano()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);

        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id,
            'facilidades_discapacidad' => true
        ]);

        $updateData = [
            'facilidades_discapacidad' => 'false' // String que debe convertirse a false
        ];

        // Act
        $response = $this->putJson("/api/emprendedores/{$emprendedor->id}", $updateData);

        // Assert
        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas('emprendedores', [
            'id' => $emprendedor->id,
            'facilidades_discapacidad' => false
        ]);
    }

    #[Test]
    public function estado_se_almacena_como_booleano()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);

        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id,
            'estado' => true
        ]);

        $updateData = [
            'estado' => '0' // String que debe convertirse a false
        ];

        // Act
        $response = $this->putJson("/api/emprendedores/{$emprendedor->id}", $updateData);

        // Assert
        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas('emprendedores', [
            'id' => $emprendedor->id,
            'estado' => false
        ]);
    }

    #[Test]
    public function puede_crear_emprendedor_con_datos_minimos()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);

        $data = [
            'nombre' => 'Emprendedor Mínimo',
            'tipo_servicio' => 'Servicios Generales',
            'descripcion' => 'Descripción mínima',
            'ubicacion' => 'Ubicación mínima',
            'telefono' => '987654321',
            'email' => 'minimo@test.com',
            'categoria' => 'Servicios',
            'precio_rango' => 'S/ 20 - S/ 50',
            'horario_atencion' => 'Lunes a Viernes: 9:00 AM - 5:00 PM',
            'asociacion_id' => $this->asociacion->id,
            'estado' => true
        ];

        // Act
        $response = $this->postJson('/api/emprendedores', $data);

        // Assert
        $response->assertStatus(Response::HTTP_CREATED);
        $this->assertDatabaseHas('emprendedores', [
            'nombre' => 'Emprendedor Mínimo',
            'asociacion_id' => $this->asociacion->id
        ]);
    }

    #[Test]
    public function falla_validacion_con_asociacion_inexistente()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);

        $data = [
            'nombre' => 'Test Emprendedor',
            'asociacion_id' => 999999, // ID inexistente
            'estado' => true
        ];

        // Act
        $response = $this->postJson('/api/emprendedores', $data);

        // Assert
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Test]
    public function no_puede_agregar_administrador_duplicado()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);

        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        $usuario = User::factory()->create();

        // Agregar usuario como administrador primera vez
        $emprendedor->administradores()->attach($usuario->id, [
            'es_principal' => false,
            'rol' => 'administrador'
        ]);

        $data = [
            'email' => $usuario->email,
            'rol' => 'colaborador'
        ];

        // Act
        $response = $this->postJson("/api/emprendedores/{$emprendedor->id}/administradores", $data);

        // Assert
        $response->assertStatus(Response::HTTP_BAD_REQUEST)
                ->assertJson([
                    'success' => false,
                    'message' => 'Este usuario ya es administrador de este emprendimiento'
                ]);
    }
}
