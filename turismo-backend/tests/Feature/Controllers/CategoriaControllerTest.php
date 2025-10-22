<?php

namespace Tests\Feature\Controllers;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Laravel\Sanctum\Sanctum;
use App\Repository\CategoriaRepository;

class CategoriaControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $adminUser;
    protected User $normalUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock del repositorio para que el controlador reciba este doble
        $this->mock(CategoriaRepository::class, function ($mock) {
            // GET /api/categorias
            $mock->shouldReceive('getAll')
                ->andReturnUsing(fn () => Categoria::orderBy('id')->get());

            // GET /api/categorias/{id} con servicios
            $mock->shouldReceive('findWithServicios')
                ->andReturnUsing(function (int $id) {
                    return Categoria::with('servicios')->find($id);
                });

            // POST /api/categorias
            $mock->shouldReceive('create')
                ->andReturnUsing(function (array $data) {
                    return Categoria::create([
                        'nombre'      => $data['nombre'] ?? null,
                        'descripcion' => $data['descripcion'] ?? null,
                        'icono_url'   => $data['icono_url'] ?? null,
                    ]);
                });

            // PUT /api/categorias/{id}
            $mock->shouldReceive('update')
                ->andReturnUsing(function (int $id, array $data) {
                    $cat = Categoria::find($id);
                    if (!$cat) return false;
                    $cat->fill([
                        'nombre'      => $data['nombre']      ?? $cat->nombre,
                        'descripcion' => array_key_exists('descripcion', $data) ? $data['descripcion'] : $cat->descripcion,
                        'icono_url'   => array_key_exists('icono_url', $data)   ? $data['icono_url']   : $cat->icono_url,
                    ])->save();
                    return true;
                });

            // usada por el controlador tras update()
            $mock->shouldReceive('findById')
                ->andReturnUsing(fn (int $id) => Categoria::find($id));

            // DELETE /api/categorias/{id}
            $mock->shouldReceive('delete')
                ->andReturnUsing(function (int $id) {
                    $cat = Categoria::find($id);
                    if (!$cat) return false;
                    return (bool) $cat->delete();
                });
        });

        // Spatie: limpiar cache de permisos
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Crear permisos y roles
        $this->createPermissions();

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $userRole  = Role::firstOrCreate(['name' => 'user']);

        $adminRole->givePermissionTo(['categoria_create', 'categoria_read', 'categoria_update', 'categoria_delete']);
        $userRole->givePermissionTo(['categoria_read']);

        // Usuarios y roles
        $this->adminUser  = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->normalUser = User::factory()->create();
        $this->normalUser->assignRole('user');
    }

    private function createPermissions(): void
    {
        foreach (['categoria_create','categoria_read','categoria_update','categoria_delete'] as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }
    }

    #[Test]
    public function puede_listar_todas_las_categorias()
    {
        Categoria::factory()->count(5)->create();

        $response = $this->getJson('/api/categorias');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id','nombre','descripcion','icono_url','created_at','updated_at']
                ]
            ]);

        $this->assertTrue($response->json('success'));
    }

    #[Test]
    public function puede_mostrar_una_categoria_especifica()
    {
        $categoria = Categoria::factory()->create();

        $response = $this->getJson("/api/categorias/{$categoria->id}");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $categoria->id,
                    'nombre' => $categoria->nombre,
                    'descripcion' => $categoria->descripcion,
                ]
            ]);
    }

    #[Test]
    public function retorna_404_cuando_categoria_no_existe()
    {
        $this->getJson('/api/categorias/999')
            ->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJson([
                'success' => false,
                'message' => 'Categoría no encontrada'
            ]);
    }

    #[Test]
    public function admin_puede_crear_nueva_categoria()
    {
        Sanctum::actingAs($this->adminUser);

        $data = [
            'nombre' => $this->faker->unique()->words(2, true),
            'descripcion' => $this->faker->text(60),
            'icono_url' => $this->faker->imageUrl(),
        ];

        $this->postJson('/api/categorias', $data)
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJson([
                'success' => true,
                'message' => 'Categoría creada exitosamente'
            ]);

        $this->assertDatabaseHas('categorias', [
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'],
        ]);
    }

    #[Test]
    public function usuario_normal_no_puede_crear_categoria()
    {
        Sanctum::actingAs($this->normalUser);

        $data = [
            'nombre' => $this->faker->word,
            'descripcion' => $this->faker->text(40),
        ];

        $this->postJson('/api/categorias', $data)
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    #[Test]
    public function usuario_no_autenticado_no_puede_crear_categoria()
    {
        $data = [
            'nombre' => $this->faker->word,
            'descripcion' => $this->faker->text(40),
        ];

        // debe haber middleware auth en la ruta POST /api/categorias
        $this->postJson('/api/categorias', $data)
            ->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    #[Test]
    public function falla_validacion_al_crear_categoria_sin_datos_requeridos()
    {
        Sanctum::actingAs($this->adminUser);

        $data = ['descripcion' => $this->faker->text(30)];

        $this->postJson('/api/categorias', $data)
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['nombre']);
    }

    #[Test]
    public function puede_crear_categoria_solo_con_nombre()
    {
        Sanctum::actingAs($this->adminUser);

        $data = ['nombre' => 'Categoría Mínima'];

        $this->postJson('/api/categorias', $data)
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJson([
                'success' => true,
                'message' => 'Categoría creada exitosamente'
            ]);

        $this->assertDatabaseHas('categorias', [
            'nombre' => 'Categoría Mínima',
            'descripcion' => null,
            'icono_url' => null,
        ]);
    }

    #[Test]
    public function admin_puede_actualizar_categoria_existente()
    {
        Sanctum::actingAs($this->adminUser);

        $categoria = Categoria::factory()->create();

        $updateData = [
            'nombre' => 'Nombre Actualizado',
            'descripcion' => 'Nueva descripción',
        ];

        $this->putJson("/api/categorias/{$categoria->id}", $updateData)
            ->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'message' => 'Categoría actualizada exitosamente'
            ]);

        $this->assertDatabaseHas('categorias', [
            'id' => $categoria->id,
            'nombre' => 'Nombre Actualizado',
            'descripcion' => 'Nueva descripción',
        ]);
    }

    #[Test]
    public function usuario_normal_no_puede_actualizar_categoria()
    {
        Sanctum::actingAs($this->normalUser);

        $categoria = Categoria::factory()->create();

        $this->putJson("/api/categorias/{$categoria->id}", ['nombre' => 'Nombre Actualizado'])
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    #[Test]
    public function retorna_404_al_actualizar_categoria_inexistente()
    {
        Sanctum::actingAs($this->adminUser);

        $this->putJson('/api/categorias/999', ['nombre' => 'Test'])
            ->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJson([
                'success' => false,
                'message' => 'Categoría no encontrada'
            ]);
    }

    #[Test]
    public function admin_puede_eliminar_categoria_existente()
    {
        Sanctum::actingAs($this->adminUser);

        $categoria = Categoria::factory()->create();

        $this->deleteJson("/api/categorias/{$categoria->id}")
            ->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'message' => 'Categoría eliminada exitosamente'
            ]);

        $this->assertDatabaseMissing('categorias', ['id' => $categoria->id]);
    }

    #[Test]
    public function usuario_normal_no_puede_eliminar_categoria()
    {
        Sanctum::actingAs($this->normalUser);

        $categoria = Categoria::factory()->create();

        $this->deleteJson("/api/categorias/{$categoria->id}")
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    #[Test]
    public function retorna_404_al_eliminar_categoria_inexistente()
    {
        Sanctum::actingAs($this->adminUser);

        $this->deleteJson('/api/categorias/999')
            ->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJson([
                'success' => false,
                'message' => 'Categoría no encontrada'
            ]);
    }

    #[Test]
    public function puede_obtener_categoria_con_servicios()
    {
        $categoria = Categoria::factory()->create();

        $this->getJson("/api/categorias/{$categoria->id}")
            ->assertStatus(Response::HTTP_OK)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id','nombre','descripcion','icono_url','servicios'
                ]
            ]);
    }

    #[Test]
    public function respuesta_json_tiene_estructura_correcta_en_exito()
    {
        $categoria = Categoria::factory()->create();

        $this->getJson("/api/categorias/{$categoria->id}")
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id','nombre','descripcion','icono_url','created_at','updated_at','servicios'
                ]
            ]);
    }

    #[Test]
    public function respuesta_json_tiene_estructura_correcta_en_error()
    {
        $res = $this->getJson('/api/categorias/999');

        $res->assertJsonStructure(['success','message']);
        $this->assertFalse($res->json('success'));
    }

    #[Test]
    public function icono_url_puede_ser_nulo()
    {
        $categoria = Categoria::factory()->sinIcono()->create();

        $response = $this->getJson("/api/categorias/{$categoria->id}")
            ->assertStatus(Response::HTTP_OK);

        $this->assertNull($response->json('data.icono_url'));
    }

    #[Test]
    public function descripcion_puede_ser_nula()
    {
        $categoria = Categoria::factory()->sinDescripcion()->create();

        $response = $this->getJson("/api/categorias/{$categoria->id}")
            ->assertStatus(Response::HTTP_OK);

        $this->assertNull($response->json('data.descripcion'));
    }

    #[Test]
    public function puede_actualizar_campos_opcionales_a_null()
    {
        Sanctum::actingAs($this->adminUser);

        $categoria = Categoria::factory()->conDescripcion()->conIcono()->create();

        $this->putJson("/api/categorias/{$categoria->id}", [
            'descripcion' => null,
            'icono_url'   => null,
        ])->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseHas('categorias', [
            'id'          => $categoria->id,
            'descripcion' => null,
            'icono_url'   => null,
        ]);
    }

    #[Test]
    public function valida_longitud_maxima_del_nombre()
    {
        Sanctum::actingAs($this->adminUser);

        $this->postJson('/api/categorias', [
            'nombre' => str_repeat('a', 256),
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['nombre']);
    }

    #[Test]
    public function valida_longitud_maxima_del_icono_url()
    {
        Sanctum::actingAs($this->adminUser);

        $this->postJson('/api/categorias', [
            'nombre'    => 'Test',
            'icono_url' => str_repeat('a', 256),
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['icono_url']);
    }
}
