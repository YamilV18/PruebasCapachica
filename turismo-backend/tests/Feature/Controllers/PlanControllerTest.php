<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\Models\User;
use App\Models\Plan;
use App\Models\Emprendedor;
use App\Models\Asociacion;
use App\Services\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use PHPUnit\Framework\Attributes\Test;

class PlanControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Emprendedor $emprendedor;
    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear roles básicos
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'emprendedor']);
        Role::create(['name' => 'turista']);

        $this->user = User::factory()->create();

        $asociacion = Asociacion::factory()->create();
        $this->emprendedor = Emprendedor::factory()->create(['asociacion_id' => $asociacion->id]);

        $this->plan = Plan::factory()->create([
            'emprendedor_id' => $this->emprendedor->id,
            'creado_por_usuario_id' => $this->user->id,
            'estado' => 'activo',
            'es_publico' => true,
        ]);

        Sanctum::actingAs($this->user);
    }


    #[Test]
    public function user_can_get_plans_with_filters(): void
    {
        Plan::factory()->create(['estado' => 'inactivo']);
        Plan::factory()->create(['estado' => 'activo', 'es_publico' => false]);

        $response = $this->getJson('/api/planes?estado=activo&es_publico=1');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }



    #[Test]
    public function user_can_get_specific_plan(): void
    {
        $response = $this->getJson("/api/planes/{$this->plan->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'nombre',
                    'descripcion',
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->plan->id,
                    'nombre' => $this->plan->nombre,
                ]
            ]);
    }

    #[Test]
    public function get_nonexistent_plan_returns_404(): void
    {
        $response = $this->getJson('/api/planes/999999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Plan no encontrado',
            ]);
    }




    #[Test]
    public function delete_nonexistent_plan_returns_404(): void
    {
        $response = $this->deleteJson('/api/planes/999999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Plan no encontrado',
            ]);
    }


    #[Test]
    public function search_requires_search_term(): void
    {
        $response = $this->getJson('/api/planes/search');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Término de búsqueda requerido',
            ]);
    }

    #[Test]
    public function user_can_get_plan_statistics(): void
    {
        $response = $this->getJson("/api/planes/{$this->plan->id}/estadisticas");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ])
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function get_statistics_for_nonexistent_plan_returns_404(): void
    {
        $response = $this->getJson('/api/planes/999999/estadisticas');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Plan no encontrado',
            ]);
    }



    #[Test]
    public function public_plans_can_be_filtered(): void
    {
        Plan::factory()->create([
            'es_publico' => true,
            'estado' => 'activo',
            'dificultad' => 'facil',
            'precio_total' => 100
        ]);

        Plan::factory()->create([
            'es_publico' => true,
            'estado' => 'activo',
            'dificultad' => 'moderado',
            'precio_total' => 300
        ]);

        $response = $this->getJson('/api/planes/publicos?dificultad=facil&precio_max=200');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'filtros_aplicados' => [
                        'dificultad' => 'facil',
                        'precio_max' => '200'
                    ]
                ]
            ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_create_plan(): void
    {
        // Crear una nueva instancia de test sin autenticación
        $this->app->make(\Illuminate\Contracts\Auth\Factory::class)->forgetGuards();

        $planData = [
            'nombre' => 'Plan de Prueba',
            'descripcion' => 'Descripción del plan',
            'emprendedor_id' => $this->emprendedor->id,
            'duracion_dias' => 3,
            'precio_total' => 150.00,
        ];

        $response = $this->postJson('/api/planes', $planData);

        $response->assertStatus(401);
    }

    #[Test]
    public function unauthenticated_user_can_view_public_plans(): void
    {
        // Crear una nueva instancia de test sin autenticación
        $this->app->make(\Illuminate\Contracts\Auth\Factory::class)->forgetGuards();

        $response = $this->getJson('/api/planes/publicos');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}
