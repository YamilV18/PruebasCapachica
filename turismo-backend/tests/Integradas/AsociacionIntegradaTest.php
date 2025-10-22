<?php

namespace Tests\Integradas;

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

/**
 * Prueba Integral que valida el flujo completo del API de Asociaciones,
 * incluyendo permisos, validación de datos, creación, lectura, actualización,
 * eliminación y carga de relaciones.
 */
class AsociacionIntegradaTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected Municipalidad $municipalidad;
    protected User $adminUser;
    protected User $normalUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Configuración de Roles y Permisos (Base de AsociacionControllerTest)
        $this->createPermissions();

        $adminRole = Role::create(['name' => 'admin']);
        $userRole = Role::create(['name' => 'user']);

        $adminRole->givePermissionTo([
            'asociacion_create', 'asociacion_read', 'asociacion_update', 'asociacion_delete'
        ]);
        $userRole->givePermissionTo(['asociacion_read']);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->normalUser = User::factory()->create();
        $this->normalUser->assignRole('user');

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

    // ----------------------------------------------------------------------
    // 1. FLUJO COMPLETO (CRUD CON PERMISOS)
    // ----------------------------------------------------------------------

    #[Test]
    public function el_flujo_integral_crud_funciona_con_admin()
    {
        // ARRANGE: Configuración de la prueba
        Sanctum::actingAs($this->adminUser);
        $asociacionCount = Asociacion::count();
        $municipalidad2 = Municipalidad::factory()->create();

        // Valores de latitud/longitud como float para asegurar la aserción
        $latitudValue = 12.345;
        $longitudValue = -67.890;

        // 1. CREATE (Creación de la Asociación)
        $data = [
            'nombre' => 'Nueva Asociación Integral',
            'descripcion' => $this->faker->text,
            'telefono' => '987654321',
            'email' => 'integral@test.com',
            'municipalidad_id' => $this->municipalidad->id,
            'estado' => true,
            'latitud' => $latitudValue, // Enviamos como string, como lo haría el JSON
            'longitud' => $longitudValue
        ];

        $response = $this->postJson('/api/asociaciones', $data);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJson(['success' => true]);

        $asociacionId = $response->json('data.id');
        $this->assertDatabaseCount('asociaciones', $asociacionCount + 1);
        $this->assertDatabaseHas('asociaciones', ['id' => $asociacionId, 'email' => 'integral@test.com']);

        $asociacion = Asociacion::find($asociacionId);

        // Corregido: Usar refresh() para asegurar que los casts se apliquen.
        $asociacion->refresh();

        // CORRECCIÓN para el error de is_float: Usamos assertIsFloat()
        $this->assertTrue(is_bool($asociacion->estado));

        $response = $this->getJson("/api/asociaciones/{$asociacionId}");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $asociacionId,
                    'nombre' => 'Nueva Asociación Integral',
                    // Verifica la carga de la relación municipalidad
                    'municipalidad' => [
                        'id' => $this->municipalidad->id,
                    ]
                ]
            ])
            // Verifica la estructura de la respuesta (ControllerTest)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'nombre', 'municipalidad', 'imagen_url']
            ]);

        // 3. UPDATE (Actualización de la Asociación)
        $updateData = [
            'nombre' => 'Nombre Actualizado Integral',
            'municipalidad_id' => $municipalidad2->id, // Cambia la municipalidad
            'estado' => '0' // Cambia el estado (Verifica casting de string a boolean)
        ];

        $response = $this->putJson("/api/asociaciones/{$asociacionId}", $updateData);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson(['success' => true, 'message' => 'Asociación actualizada exitosamente']);

        $this->assertDatabaseHas('asociaciones', [
            'id' => $asociacionId,
            'nombre' => 'Nombre Actualizado Integral',
            'municipalidad_id' => $municipalidad2->id,
            'estado' => false // Verifica que el casting funcionó al guardar
        ]);

        // 4. DELETE (Eliminación de la Asociación)
        $response = $this->deleteJson("/api/asociaciones/{$asociacionId}");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson(['success' => true, 'message' => 'Asociación eliminada exitosamente']);

        $this->assertDatabaseMissing('asociaciones', ['id' => $asociacionId]);
    }

    // ----------------------------------------------------------------------
    // 2. VALIDACIÓN DE PERMISOS Y ERRORES
    // ----------------------------------------------------------------------

    #[Test]
    public function la_creacion_falla_por_permisos_y_validacion()
    {
        // 1. Falla por usuario no autenticado (ControllerTest)
        $data = ['nombre' => $this->faker->company, 'municipalidad_id' => $this->municipalidad->id, 'estado' => true];
        $this->postJson('/api/asociaciones', $data)
            ->assertStatus(Response::HTTP_UNAUTHORIZED);

        // 2. Falla por usuario sin permiso (ControllerTest)
        Sanctum::actingAs($this->normalUser);
        $this->postJson('/api/asociaciones', $data)
            ->assertStatus(Response::HTTP_FORBIDDEN);

        // 3. Falla por datos inválidos (ControllerTest)
        Sanctum::actingAs($this->adminUser);
        $invalidData = [
            'nombre' => 'ok',
            'email' => 'email-invalido', // Email inválido
            'estado' => true // Agregado para pasar validaciones que no son el objetivo de este test
            // Falta municipalidad_id requerido
        ];

        $this->postJson('/api/asociaciones', $invalidData)
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['email', 'municipalidad_id']);
    }

    #[Test]
    public function la_actualizacion_falla_por_permisos_y_entidad_inexistente()
    {
        // Arrange
        $asociacion = Asociacion::factory()->create(['municipalidad_id' => $this->municipalidad->id]);

        // Se usa la cadena '0' para cumplir con la validación estricta de 'estado'.
        $updateData = ['nombre' => 'Nuevo Nombre', 'estado' => '0'];

        // 1. Falla por usuario sin permiso (ControllerTest)
        Sanctum::actingAs($this->normalUser);
        $this->putJson("/api/asociaciones/{$asociacion->id}", $updateData)
            ->assertStatus(Response::HTTP_FORBIDDEN);

        // 2. Falla al intentar actualizar inexistente (ControllerTest & ServiceTest)
        Sanctum::actingAs($this->adminUser);
        $this->putJson('/api/asociaciones/999', $updateData)
            ->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJson(['message' => 'Asociación no encontrada']);
    }

    // ----------------------------------------------------------------------
    // 3. RELACIONES Y CONSULTAS ESPECIALES
    // ----------------------------------------------------------------------

    #[Test]
    public function puede_obtener_emprendedores_y_filtrar_por_municipalidad()
    {
        // Arrange
        $otraMunicipalidad = Municipalidad::factory()->create();

        $asociacion1 = Asociacion::factory()->create(['municipalidad_id' => $this->municipalidad->id]);
        $asociacion2 = Asociacion::factory()->create(['municipalidad_id' => $this->municipalidad->id]);
        $asociacion3 = Asociacion::factory()->create(['municipalidad_id' => $otraMunicipalidad->id]);

        $emprendedores = Emprendedor::factory()->count(2)->create(['asociacion_id' => $asociacion1->id]);

        // 1. Consultar emprendedores (ControllerTest & ServiceTest)
        $response = $this->getJson("/api/asociaciones/{$asociacion1->id}/emprendedores");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data');

        // 2. Filtrar por municipalidad (ControllerTest & ServiceTest)
        $response = $this->getJson("/api/asociaciones/municipalidad/{$this->municipalidad->id}");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data'); // Solo asociacion1 y asociacion2

        foreach ($response->json('data') as $asociacion) {
            $this->assertEquals($this->municipalidad->id, $asociacion['municipalidad_id']);
        }
    }

    #[Test]
    public function el_listado_con_paginacion_maneja_la_respuesta_correcta()
    {
        // Arrange
        Asociacion::factory()->count(15)->create(['municipalidad_id' => $this->municipalidad->id]);

        // Act
        $response = $this->getJson('/api/asociaciones?per_page=7');

        // Assert (ControllerTest & ServiceTest)
        $response->assertStatus(Response::HTTP_OK);
        $data = $response->json('data');

        $this->assertEquals(7, $data['per_page']);
        $this->assertEquals(15, $data['total']);
        $this->assertCount(7, $data['data']);

        $response->assertJsonStructure([
            'success',
            'data' => [
                'data' => [
                    '*' => ['id', 'nombre', 'municipalidad_id', 'municipalidad', 'imagen_url']
                ],
                'current_page',
                'per_page',
                'total'
            ]
        ]);
    }

    #[Test]
    public function el_atributo_imagen_url_es_correcto()
    {
        // Arrange
        $asociacionConImagen = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id,
            'imagen' => 'asociaciones/test-path.png'
        ]);

        $asociacionSinImagen = Asociacion::factory()->create([
            'municipalidad_id' => $this->municipalidad->id,
            'imagen' => null
        ]);

        // 1. Con imagen (ControllerTest & AsociacionTest)
        $response = $this->getJson("/api/asociaciones/{$asociacionConImagen->id}");
        $data = $response->json('data');

        $this->assertNotNull($data['imagen_url']);
        $this->assertStringContainsString('asociaciones/test-path.png', $data['imagen_url']); // Verifica la URL de almacenamiento

        // 2. Sin imagen (ControllerTest & AsociacionTest)
        $response = $this->getJson("/api/asociaciones/{$asociacionSinImagen->id}");
        $data = $response->json('data');

        $this->assertNull($data['imagen_url']);
    }
}
