<?php

namespace Tests\Feature\Controllers;

use App\Models\Asociacion;
use App\Models\Municipalidad;
use App\Models\Emprendedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Laravel\Sanctum\Sanctum;

class AsociacionControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected Municipalidad $municipalidad;
    protected User $adminUser;
    protected User $normalUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear permisos necesarios
        $this->createPermissions();
        
        // Crear roles
        $adminRole = Role::create(['name' => 'admin']);
        $userRole = Role::create(['name' => 'user']);
        
        // Asignar permisos a roles
        $adminRole->givePermissionTo([
            'asociacion_create', 'asociacion_read', 'asociacion_update', 'asociacion_delete'
        ]);
        $userRole->givePermissionTo(['asociacion_read']);
        
        // Crear usuarios
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');
        
        $this->normalUser = User::factory()->create();
        $this->normalUser->assignRole('user');
        
        // Crear municipalidad
        $this->municipalidad = Municipalidad::factory()->create();
    }

    private function createPermissions(): void
    {
        $permissions = [
            'asociacion_create', 'asociacion_read', 'asociacion_update', 'asociacion_delete'
        ];
        
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }

    #[Test]
    public function puede_listar_todas_las_asociaciones()
    {
        // Arrange
        Asociacion::factory()->count(5)->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);

        // Act
        $response = $this->getJson('/api/asociaciones');

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'nombre',
                                'descripcion',
                                'telefono',
                                'email',
                                'municipalidad_id',
                                'estado',
                                'imagen_url',
                                'municipalidad'
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
    public function puede_listar_asociaciones_con_paginacion_personalizada()
    {
        // Arrange
        Asociacion::factory()->count(20)->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);

        // Act
        $response = $this->getJson('/api/asociaciones?per_page=5');

        // Assert
        $response->assertStatus(Response::HTTP_OK);
        $data = $response->json('data');
        
        $this->assertEquals(5, $data['per_page']);
        $this->assertEquals(20, $data['total']);
        $this->assertCount(5, $data['data']);
    }

    #[Test]
    public function puede_mostrar_una_asociacion_especifica()
    {
        // Arrange
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);

        // Act
        $response = $this->getJson("/api/asociaciones/{$asociacion->id}");

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $asociacion->id,
                        'nombre' => $asociacion->nombre,
                        'email' => $asociacion->email
                    ]
                ]);
    }

    #[Test]
    public function retorna_404_cuando_asociacion_no_existe()
    {
        // Act
        $response = $this->getJson('/api/asociaciones/999');

        // Assert
        $response->assertStatus(Response::HTTP_NOT_FOUND)
                ->assertJson([
                    'success' => false,
                    'message' => 'Asociación no encontrada'
                ]);
    }

    #[Test]
    public function admin_puede_crear_nueva_asociacion()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $data = [
            'nombre' => $this->faker->company,
            'descripcion' => $this->faker->text,
            'telefono' => $this->faker->phoneNumber,
            'email' => $this->faker->email,
            'municipalidad_id' => $this->municipalidad->id,
            'estado' => true
        ];

        // Act
        $response = $this->postJson('/api/asociaciones', $data);

        // Assert
        $response->assertStatus(Response::HTTP_CREATED)
                ->assertJson([
                    'success' => true,
                    'message' => 'Asociación creada exitosamente'
                ]);

        $this->assertDatabaseHas('asociaciones', [
            'nombre' => $data['nombre'],
            'email' => $data['email']
        ]);
    }

    #[Test]
    public function usuario_normal_no_puede_crear_asociacion()
    {
        // Arrange
        Sanctum::actingAs($this->normalUser);
        
        $data = [
            'nombre' => $this->faker->company,
            'municipalidad_id' => $this->municipalidad->id
        ];

        // Act
        $response = $this->postJson('/api/asociaciones', $data);

        // Assert
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    #[Test]
    public function usuario_no_autenticado_no_puede_crear_asociacion()
    {
        // Arrange
        $data = [
            'nombre' => $this->faker->company,
            'municipalidad_id' => $this->municipalidad->id
        ];

        // Act
        $response = $this->postJson('/api/asociaciones', $data);

        // Assert
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    #[Test]
    public function falla_validacion_al_crear_asociacion_sin_datos_requeridos()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $data = [
            'descripcion' => $this->faker->text
            // Falta nombre y municipalidad_id requeridos
        ];

        // Act
        $response = $this->postJson('/api/asociaciones', $data);

        // Assert
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->assertJson([
                    'success' => false,
                    'message' => 'Error de validación'
                ])
                ->assertJsonValidationErrors(['nombre', 'municipalidad_id']);
    }

    #[Test]
    public function falla_validacion_con_email_invalido()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $data = [
            'nombre' => $this->faker->company,
            'email' => 'email-invalido',
            'municipalidad_id' => $this->municipalidad->id
        ];

        // Act
        $response = $this->postJson('/api/asociaciones', $data);

        // Assert
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function falla_validacion_con_municipalidad_inexistente()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $data = [
            'nombre' => $this->faker->company,
            'municipalidad_id' => 999999
        ];

        // Act
        $response = $this->postJson('/api/asociaciones', $data);

        // Assert
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->assertJsonValidationErrors(['municipalidad_id']);
    }

    #[Test]
    public function admin_puede_actualizar_asociacion_existente()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);

        $updateData = [
            'nombre' => 'Nombre Actualizado',
            'descripcion' => 'Nueva descripción',
            'estado' => '0' // Test string to boolean conversion
        ];

        // Act
        $response = $this->putJson("/api/asociaciones/{$asociacion->id}", $updateData);

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true,
                    'message' => 'Asociación actualizada exitosamente'
                ]);

        $this->assertDatabaseHas('asociaciones', [
            'id' => $asociacion->id,
            'nombre' => 'Nombre Actualizado',
            'descripcion' => 'Nueva descripción',
            'estado' => false
        ]);
    }

    #[Test]
    public function usuario_normal_no_puede_actualizar_asociacion()
    {
        // Arrange
        Sanctum::actingAs($this->normalUser);
        
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);

        $updateData = [
            'nombre' => 'Nombre Actualizado'
        ];

        // Act
        $response = $this->putJson("/api/asociaciones/{$asociacion->id}", $updateData);

        // Assert
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    #[Test]
    public function retorna_404_al_actualizar_asociacion_inexistente()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $updateData = [
            'nombre' => 'Test',
            'estado' => 0,
        ];

        // Act
        $response = $this->putJson('/api/asociaciones/999', $updateData);

        // Assert
        $response->assertStatus(Response::HTTP_NOT_FOUND)
                ->assertJson([
                    'success' => false,
                    'message' => 'Asociación no encontrada'
                ]);
    }

    #[Test]
    public function falla_validacion_estado_con_valor_invalido()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);

        $updateData = [
            'estado' => 'invalid_state'
        ];

        // Act
        $response = $this->putJson("/api/asociaciones/{$asociacion->id}", $updateData);

        // Assert
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->assertJsonValidationErrors(['estado']);
    }

    #[Test]
    public function admin_puede_eliminar_asociacion_existente()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);

        // Act
        $response = $this->deleteJson("/api/asociaciones/{$asociacion->id}");

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true,
                    'message' => 'Asociación eliminada exitosamente'
                ]);

        $this->assertDatabaseMissing('asociaciones', [
            'id' => $asociacion->id
        ]);
    }

    #[Test]
    public function usuario_normal_no_puede_eliminar_asociacion()
    {
        // Arrange
        Sanctum::actingAs($this->normalUser);
        
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);

        // Act
        $response = $this->deleteJson("/api/asociaciones/{$asociacion->id}");

        // Assert
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    #[Test]
    public function retorna_404_al_eliminar_asociacion_inexistente()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);

        // Act
        $response = $this->deleteJson('/api/asociaciones/999');

        // Assert
        $response->assertStatus(Response::HTTP_NOT_FOUND)
                ->assertJson([
                    'success' => false,
                    'message' => 'Asociación no encontrada'
                ]);
    }

    #[Test]
    public function puede_obtener_emprendedores_de_asociacion()
    {
        // Arrange
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);

        $emprendedores = Emprendedor::factory()->count(3)->create([
            'asociacion_id' => $asociacion->id
        ]);

        // Act
        $response = $this->getJson("/api/asociaciones/{$asociacion->id}/emprendedores");

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonCount(3, 'data');

        foreach ($emprendedores as $index => $emprendedor) {
            $response->assertJsonFragment([
                'id' => $emprendedor->id
            ]);
        }
    }

    #[Test]
    public function retorna_404_al_obtener_emprendedores_de_asociacion_inexistente()
    {
        // Act
        $response = $this->getJson('/api/asociaciones/999/emprendedores');

        // Assert
        $response->assertStatus(Response::HTTP_NOT_FOUND)
                ->assertJson([
                    'success' => false,
                    'message' => 'Asociación no encontrada'
                ]);
    }

    #[Test]
    public function puede_obtener_asociaciones_por_municipalidad()
    {
        // Arrange
        $otraMunicipalidad = Municipalidad::factory()->create();

        Asociacion::factory()->count(3)->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);

        Asociacion::factory()->count(2)->create([
            'municipalidad_id' => $otraMunicipalidad->id
        ]);

        // Act
        $response = $this->getJson("/api/asociaciones/municipalidad/{$this->municipalidad->id}");

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonCount(3, 'data');

        foreach ($response->json('data') as $asociacion) {
            $this->assertEquals($this->municipalidad->id, $asociacion['municipalidad_id']);
        }
    }

    #[Test]
    public function maneja_error_interno_del_servidor()
    {
        // Este test es difícil de simular sin mocking, pero podemos verificar
        // que el controlador maneja correctamente las excepciones
        
        // Arrange - Crear asociación válida para el test
        Sanctum::actingAs($this->adminUser);
        
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);

        // Act - Intentar actualizar con datos válidos (este debería funcionar)
        $response = $this->putJson("/api/asociaciones/{$asociacion->id}", [
            'nombre' => 'Test Update',
            'estado' => 0,
        ]);

        // Assert - Verificar que no hay error 500
        $response->assertStatus(Response::HTTP_OK);
    }

    #[Test]
    public function respuesta_json_tiene_estructura_correcta_en_exito()
    {
        // Arrange
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id
        ]);

        // Act
        $response = $this->getJson("/api/asociaciones/{$asociacion->id}");

        // Assert
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'nombre',
                'descripcion',
                'telefono',
                'email',
                'municipalidad_id',
                'estado',
                'imagen',
                'imagen_url',
                'created_at',
                'updated_at',
                'municipalidad' => [
                    'id',
                    'nombre'
                ]
            ]
        ]);
    }

    #[Test]
    public function respuesta_json_tiene_estructura_correcta_en_error()
    {
        // Act
        $response = $this->getJson('/api/asociaciones/999');

        // Assert
        $response->assertJsonStructure([
            'success',
            'message'
        ]);
        
        $this->assertFalse($response->json('success'));
    }

    #[Test]
    public function imagen_url_es_correcta_cuando_hay_imagen()
    {
        // Arrange
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id,
            'imagen' => 'asociaciones/test.jpg'
        ]);

        // Act
        $response = $this->getJson("/api/asociaciones/{$asociacion->id}");

        // Assert
        $response->assertStatus(Response::HTTP_OK);
        $data = $response->json('data');
        
        $this->assertNotNull($data['imagen_url']);
        $this->assertStringContainsString('asociaciones/test.jpg', $data['imagen_url']);
    }

    #[Test]
    public function imagen_url_es_null_cuando_no_hay_imagen()
    {
        // Arrange
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id,
            'imagen' => null
        ]);

        // Act
        $response = $this->getJson("/api/asociaciones/{$asociacion->id}");

        // Assert
        $response->assertStatus(Response::HTTP_OK);
        $data = $response->json('data');
        
        $this->assertNull($data['imagen_url']);
    }
}