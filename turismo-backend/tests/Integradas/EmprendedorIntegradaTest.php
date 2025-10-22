<?php

namespace Tests\Integradas;

use App\Models\Emprendedor;
use App\Models\Asociacion;
use App\Models\Municipalidad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Laravel\Sanctum\Sanctum;

class EmprendedorIntegradaTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $adminUser;
    protected User $emprendedorUser;
    protected Asociacion $asociacion;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Configuración Inicial: Crear permisos, roles y usuarios
        $this->createPermissionsAndRoles();

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->emprendedorUser = User::factory()->create();
        $this->emprendedorUser->assignRole('emprendedor');

        $municipalidad = Municipalidad::factory()->create();
        $this->asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $municipalidad->id
        ]);
    }

    private function createPermissionsAndRoles(): void
    {
        $permissions = [
            'emprendedor_create', 'emprendedor_read', 'emprendedor_update', 'emprendedor_delete'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $emprendedorRole = Role::firstOrCreate(['name' => 'emprendedor']);

        $adminRole->givePermissionTo($permissions);
        $emprendedorRole->givePermissionTo(['emprendedor_read', 'emprendedor_update']);
    }

    #[Test]
    public function flujo_completo_integrado_emprendedor()
    {
        $emprendedorId = $this->flujoCreacion();
        $this->flujoAsignacionAdmin($emprendedorId);
        $this->flujoActualizacionPropietario($emprendedorId);
        $this->flujoActualizacionAdmin($emprendedorId);
        $this->flujoListadoBusqueda($emprendedorId);
        $this->flujoEliminacion($emprendedorId);
    }

    protected function flujoCreacion(): int
    {
        // 2. Creación por Admin
        Sanctum::actingAs($this->adminUser);

        $data = [
            'nombre' => 'Restaurante Integrado Test',
            'tipo_servicio' => 'Restaurante',
            'descripcion' => 'Especialidad en trucha fresca del lago',
            'ubicacion' => 'Av. Principal 123',
            'telefono' => '987654321',
            'email' => 'integrado@test.com',
            'categoria' => 'Gastronomía',
            'precio_rango' => 'S/ 50 - S/ 100',
            'metodos_pago' => ['efectivo', 'tarjeta_credito'],
            'horario_atencion' => 'Lunes a Domingo: 11:00 AM - 10:00 PM',
            'idiomas_hablados' => ['español', 'inglés'],
            'facilidades_discapacidad' => true,
            'asociacion_id' => $this->asociacion->id,
            'estado' => true
        ];

        $response = $this->postJson('/api/emprendedores', $data);

        // 3. Verificación de Creación (Controlador y Base de Datos)
        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJson([
                'success' => true,
                'message' => 'Emprendedor creado exitosamente'
            ]);

        $this->assertDatabaseHas('emprendedores', [
            'nombre' => $data['nombre'],
            'email' => $data['email'],
            'facilidades_discapacidad' => true // Verifica el cast a booleano
        ]);

        $emprendedor = Emprendedor::latest()->first();

        // Verificación de casts (Modelo)
        $this->assertIsArray($emprendedor->metodos_pago);
        $this->assertTrue($emprendedor->facilidades_discapacidad);
        $this->assertInstanceOf(Asociacion::class, $emprendedor->asociacion); // Verifica relación

        return $emprendedor->id;
    }

    protected function flujoAsignacionAdmin(int $emprendedorId): void
    {
        // 4. Asignación de Administrador
        Sanctum::actingAs($this->adminUser);

        $data = [
            'email' => $this->emprendedorUser->email,
            'rol' => 'administrador',
            'es_principal' => true
        ];

        $response = $this->postJson("/api/emprendedores/{$emprendedorId}/administradores", $data);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'message' => 'Administrador agregado correctamente'
            ]);

        $this->assertDatabaseHas('user_emprendedor', [
            'user_id' => $this->emprendedorUser->id,
            'emprendedor_id' => $emprendedorId,
            'es_principal' => true
        ]);
    }

    protected function flujoActualizacionPropietario(int $emprendedorId): void
    {
        // 5. Actualización por Propietario (Emprendedor con permisos)
        Sanctum::actingAs($this->emprendedorUser);

        $updateData = [
            'descripcion' => 'Descripción actualizada por propietario',
            'telefono' => '999111222'
        ];

        $response = $this->putJson("/api/emprendedores/{$emprendedorId}", $updateData);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseHas('emprendedores', [
            'id' => $emprendedorId,
            'descripcion' => 'Descripción actualizada por propietario',
        ]);
    }

    protected function flujoActualizacionAdmin(int $emprendedorId): void
    {
        // 6. Actualización por Admin (cambio de estado)
        Sanctum::actingAs($this->adminUser);

        $updateData = [
            'estado' => false // Cambiar de true a false
        ];

        $response = $this->putJson("/api/emprendedores/{$emprendedorId}", $updateData);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseHas('emprendedores', [
            'id' => $emprendedorId,
            'estado' => false // Verifica el cast a booleano
        ]);
    }

    protected function flujoListadoBusqueda(int $emprendedorId): void
    {
        // 7. Listado y Búsqueda (Verificación de getAll y search en Service/Controller)

        // Listado general (Debe aparecer)
        $responseList = $this->getJson('/api/emprendedores');
        $responseList->assertStatus(Response::HTTP_OK);
        $dataList = collect($responseList->json('data.data'));
        $this->assertContains($emprendedorId, $dataList->pluck('id')->toArray());

        // Búsqueda por texto (Debe encontrar "Restaurante" o "Integrado")
        $responseSearch = $this->getJson('/api/emprendedores/search?q=Restaurante');
        $responseSearch->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonCount(1, 'data'); // Asume que solo se creó este emprendedor

        $this->assertEquals($emprendedorId, $responseSearch->json('data.0.id'));

        // Obtener un emprendedor específico (Verificación getById en Service/Controller)
        $responseShow = $this->getJson("/api/emprendedores/{$emprendedorId}");
        $responseShow->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'nombre',
                    'asociacion', // Verifica que la relación esté cargada
                ]
            ]);
    }

    protected function flujoEliminacion(int $emprendedorId): void
    {
        // 8. Eliminación por Admin
        Sanctum::actingAs($this->adminUser);

        $response = $this->deleteJson("/api/emprendedores/{$emprendedorId}");

        // 9. Verificación de Eliminación
        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'message' => 'Emprendedor eliminado exitosamente'
            ]);

        $this->assertDatabaseMissing('emprendedores', [
            'id' => $emprendedorId
        ]);

        // Verificar que ya no se puede obtener (404)
        $responseNotFound = $this->getJson("/api/emprendedores/{$emprendedorId}");
        $responseNotFound->assertStatus(Response::HTTP_NOT_FOUND);
    }
}
