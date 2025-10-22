<?php

namespace Tests\Integradas; // Cambiar a Feature para pruebas integradas

use App\Models\Categoria;
use App\Models\User;
use App\Repository\CategoriaRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Laravel\Sanctum\Sanctum;

class CategoriaIntegradaTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $adminUser;
    protected CategoriaRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        // Inicializar el repositorio real (sin mock)
        $this->repository = new CategoriaRepository(new Categoria());

        // Spatie: limpiar cache de permisos
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Crear permisos y roles (como en CategoriaControllerTest)
        Permission::firstOrCreate(['name' => 'categoria_create']);
        Permission::firstOrCreate(['name' => 'categoria_read']);
        Permission::firstOrCreate(['name' => 'categoria_update']);
        Permission::firstOrCreate(['name' => 'categoria_delete']);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(['categoria_create', 'categoria_read', 'categoria_update', 'categoria_delete']);

        // Usuario administrador
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        // Autenticar al administrador para las peticiones (Controller)
        Sanctum::actingAs($this->adminUser);
    }

    #[Test]
    public function ciclo_de_vida_completo_de_categoria_por_admin()
    {
        // ----------------------------------------------------------------------
        // 1. CREACIÓN (Controller -> Repository -> Model)
        // ----------------------------------------------------------------------
        $initialData = [
            'nombre'      => 'Nueva Categoría Test',
            'descripcion' => 'Descripción inicial',
            'icono_url'   => $this->faker->imageUrl(),
        ];

        $response = $this->postJson('/api/categorias', $initialData)
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJson(['success' => true, 'message' => 'Categoría creada exitosamente']);

        $createdId = $response->json('data.id');
        $this->assertIsInt($createdId);

        // ----------------------------------------------------------------------
        // 2. VERIFICACIÓN DE CREACIÓN (Model & Database)
        // ----------------------------------------------------------------------
        $this->assertDatabaseHas('categorias', [
            'id'          => $createdId,
            'nombre'      => $initialData['nombre'],
            'descripcion' => $initialData['descripcion'],
        ]);

        // Usar el Repositorio para buscar
        $categoriaFromRepo = $this->repository->findById($createdId);
        $this->assertInstanceOf(Categoria::class, $categoriaFromRepo);
        $this->assertNotNull($categoriaFromRepo->created_at);
        $originalUpdatedAt = $categoriaFromRepo->updated_at;

        // ----------------------------------------------------------------------
        // 3. ACTUALIZACIÓN (Controller -> Repository -> Model)
        // ----------------------------------------------------------------------
        $updatedData = [
            'nombre'      => 'Nombre Actualizado',
            'descripcion' => 'Descripción Modificada',
        ];

        sleep(1); // Simular paso del tiempo para verificar 'updated_at'

        $this->putJson("/api/categorias/{$createdId}", $updatedData)
            ->assertStatus(Response::HTTP_OK)
            ->assertJson(['success' => true, 'message' => 'Categoría actualizada exitosamente']);

        // ----------------------------------------------------------------------
        // 4. VERIFICACIÓN DE ACTUALIZACIÓN & RELACIÓN (Repository & Model)
        // ----------------------------------------------------------------------
        $this->assertDatabaseHas('categorias', [
            'id'          => $createdId,
            'nombre'      => $updatedData['nombre'],
            'descripcion' => $updatedData['descripcion'],
        ]);

        $categoriaUpdated = $this->repository->findWithServicios($createdId);
        $this->assertInstanceOf(Categoria::class, $categoriaUpdated);
        $this->assertTrue($categoriaUpdated->relationLoaded('servicios'));
        $this->assertCount(0, $categoriaUpdated->servicios); // Sin servicios asociados
        $this->assertNotEquals($originalUpdatedAt, $categoriaUpdated->updated_at); // updated_at debe cambiar

        // ----------------------------------------------------------------------
        // 5. ELIMINACIÓN (Controller -> Repository -> Model)
        // ----------------------------------------------------------------------
        $this->deleteJson("/api/categorias/{$createdId}")
            ->assertStatus(Response::HTTP_OK)
            ->assertJson(['success' => true, 'message' => 'Categoría eliminada exitosamente']);

        // ----------------------------------------------------------------------
        // 6. VERIFICACIÓN DE ELIMINACIÓN (Model & Database)
        // ----------------------------------------------------------------------
        $this->assertDatabaseMissing('categorias', ['id' => $createdId]);
        $this->assertNull($this->repository->findById($createdId));
    }
}
