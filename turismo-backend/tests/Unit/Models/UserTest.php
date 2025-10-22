<?php

namespace Tests\Unit\Models;

use App\Models\Asociacion;
use App\Models\Emprendedor;
use App\Models\Plan;
use App\Models\PlanInscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear roles básicos
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'emprendedor']);
        Role::create(['name' => 'turista']);

        Storage::fake('public');
    }

    #[Test]
    public function user_can_be_created_with_factory()
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(User::class, $user);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
        ]);
    }

    #[Test]
    public function user_has_fillable_attributes()
    {
        $fillable = [
            'name', 'email', 'password', 'phone', 'active', 'foto_perfil',
            'google_id', 'avatar', 'country', 'birth_date', 'address',
            'gender', 'preferred_language', 'last_login'
        ];
        $user = new User();
        $this->assertEquals($fillable, $user->getFillable());
    }

    #[Test]
    public function user_has_hidden_attributes()
    {
        $hidden = ['password', 'remember_token', 'google_id'];
        $user = new User();
        $this->assertEquals($hidden, $user->getHidden());
    }

    #[Test]
    public function user_casts_attributes_correctly()
    {
        $user = User::factory()->create([
            'email_verified_at' => '2023-01-01 12:00:00',
            'active' => 1,
            'birth_date' => '1990-01-01',
            'last_login' => '2023-01-01 12:00:00',
        ]);
        $this->assertInstanceOf(\Carbon\Carbon::class, $user->email_verified_at);
        $this->assertIsBool($user->active);
        $this->assertInstanceOf(\Carbon\Carbon::class, $user->birth_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $user->last_login);
    }

    #[Test]
    public function user_has_emprendimientos_relationship()
    {
        $user = User::factory()->create();
        $asociacion = Asociacion::factory()->create();
        $emprendedor = Emprendedor::factory()->create(['asociacion_id' => $asociacion->id]);

        $user->emprendimientos()->attach($emprendedor->id, [
            'es_principal' => true,
            'rol' => 'administrador'
        ]);

        $this->assertTrue($user->emprendimientos->contains($emprendedor));
        $this->assertEquals('administrador', $user->emprendimientos->first()->pivot->rol);
    }

    #[Test]
    public function user_can_check_if_administra_emprendimientos()
    {
        $user = User::factory()->create();
        $this->assertFalse($user->administraEmprendimientos());

        $asociacion = Asociacion::factory()->create();
        $emprendedor = Emprendedor::factory()->create(['asociacion_id' => $asociacion->id]);
        $user->emprendimientos()->attach($emprendedor->id);

        $this->assertTrue($user->fresh()->administraEmprendimientos());
    }

    #[Test]
    public function user_has_planes_creados_relationship()
    {
        $user = User::factory()->create();
        $asociacion = Asociacion::factory()->create();
        $emprendedor = Emprendedor::factory()->create(['asociacion_id' => $asociacion->id]);
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $user->id,
            'emprendedor_id' => $emprendedor->id,
            'estado' => 'activo'
        ]);

        $this->assertTrue($user->planesCreados->contains($plan));
    }



    #[Test]
    public function user_has_planes_inscritos_relationship()
    {
        $user = User::factory()->create();
        $asociacion = Asociacion::factory()->create();
        $emprendedor = Emprendedor::factory()->create(['asociacion_id' => $asociacion->id]);
        $plan = Plan::factory()->create(['emprendedor_id' => $emprendedor->id, 'estado' => 'activo']);

        PlanInscripcion::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'estado' => 'confirmada'
        ]);

        $this->assertTrue($user->planesInscritos->contains($plan));
    }




    #[Test]
    public function user_can_check_if_puede_gestionar_plan()
    {
        $user = User::factory()->create();
        $asociacion = Asociacion::factory()->create();
        $emprendedor = Emprendedor::factory()->create(['asociacion_id' => $asociacion->id]);
        $plan = Plan::factory()->create(['creado_por_usuario_id' => $user->id, 'emprendedor_id' => $emprendedor->id, 'estado' => 'activo']);

        $this->assertTrue($user->puedeGestionarPlan($plan->id));

        $otherUser = User::factory()->create();
        $this->assertFalse($otherUser->puedeGestionarPlan($plan->id));
    }

    #[Test]
    public function admin_user_can_gestionar_any_plan()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $asociacion = Asociacion::factory()->create();
        $emprendedor = Emprendedor::factory()->create(['asociacion_id' => $asociacion->id]);
        $plan = Plan::factory()->create(['emprendedor_id' => $emprendedor->id, 'estado' => 'activo']);

        $this->assertTrue($admin->puedeGestionarPlan($plan->id));
    }

    #[Test]
    public function user_gets_estadisticas_planes_correctly()
    {
        $user = User::factory()->create();
        $asociacion = Asociacion::factory()->create();
        $emprendedor = Emprendedor::factory()->create(['asociacion_id' => $asociacion->id]);
        $plan1 = Plan::factory()->create(['creado_por_usuario_id' => $user->id, 'emprendedor_id' => $emprendedor->id, 'estado' => 'activo']);
        $plan2 = Plan::factory()->create(['creado_por_usuario_id' => $user->id, 'emprendedor_id' => $emprendedor->id, 'estado' => 'activo']);

        PlanInscripcion::factory()->create(['user_id' => $user->id, 'plan_id' => $plan1->id, 'estado' => 'confirmada', 'precio_pagado' => 100]);
        PlanInscripcion::factory()->create(['user_id' => $user->id, 'plan_id' => $plan2->id, 'estado' => 'pendiente']);

        $estadisticas = $user->estadisticas_planes_usuario;

        $this->assertEquals(2, $estadisticas['planes_creados']);
        $this->assertEquals(2, $estadisticas['total_inscripciones']);
        $this->assertEquals(1, $estadisticas['inscripciones_confirmadas']);
        $this->assertEquals(1, $estadisticas['inscripciones_pendientes']);
        $this->assertEquals(100, $estadisticas['total_gastado']);
    }

    #[Test]
    public function user_gets_foto_perfil_url_correctly()
    {
        $user1 = User::factory()->create(['foto_perfil' => null, 'avatar' => null]);
        $this->assertNull($user1->foto_perfil_url);

        $user2 = User::factory()->create(['foto_perfil' => null, 'avatar' => 'https://example.com/avatar.jpg', 'google_id' => '123']);
        $this->assertEquals('https://example.com/avatar.jpg', $user2->foto_perfil_url);

        $user3 = User::factory()->create(['foto_perfil' => 'fotos_perfil/test.jpg', 'avatar' => 'https://example.com/avatar.jpg']);
        $this->assertStringContainsString('fotos_perfil/test.jpg', $user3->foto_perfil_url);
    }

    #[Test]
    public function user_can_check_registered_with_google()
    {
        $normalUser = User::factory()->create(['google_id' => null]);
        $googleUser = User::factory()->create(['google_id' => 'some_google_id']);

        $this->assertFalse($normalUser->registeredWithGoogle());
        $this->assertTrue($googleUser->registeredWithGoogle());
    }
}
