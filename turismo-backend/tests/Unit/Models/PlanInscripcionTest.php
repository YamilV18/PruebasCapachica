<?php

namespace Tests\Unit\Models;

use App\Models\Asociacion;
use App\Models\Emprendedor;
use App\Models\Plan;
use App\Models\PlanInscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlanInscripcionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $asociacion = Asociacion::factory()->create();
        $emprendedor = Emprendedor::factory()->create(['asociacion_id' => $asociacion->id]);

        $this->plan = Plan::factory()->create([
            'emprendedor_id' => $emprendedor->id,
            'creado_por_usuario_id' => $this->user->id,
            'precio_total' => 200.00,
            'estado' => 'activo',
        ]);
    }

    #[Test]
    public function plan_inscripcion_can_be_created_with_factory()
    {
        $inscripcion = PlanInscripcion::factory()->create([
            'plan_id' => $this->plan->id,
            'user_id' => $this->user->id,
            'estado' => 1, // La columna 'estado' es numérica
        ]);

        $this->assertInstanceOf(PlanInscripcion::class, $inscripcion);
        $this->assertDatabaseHas('plan_inscripciones', ['id' => $inscripcion->id]);
    }

    #[Test]
    public function plan_inscripcion_has_fillable_attributes()
    {
        $fillable = [
            'plan_id', 'user_id', 'estado', 'notas', 'fecha_inscripcion',
            'fecha_inicio_plan', 'fecha_fin_plan', 'notas_usuario',
            'requerimientos_especiales', 'numero_participantes',
            'precio_pagado', 'metodo_pago', 'comentarios_adicionales'
        ];
        $this->assertEquals($fillable, (new PlanInscripcion())->getFillable());
    }

    #[Test]
    public function plan_inscripcion_casts_dates_and_decimal()
    {
        $inscripcion = PlanInscripcion::factory()->create([
            'plan_id' => $this->plan->id,
            'user_id' => $this->user->id,
            'estado' => 1, // La columna 'estado' es numérica
            'fecha_inscripcion' => '2024-01-01 10:00:00',
            'fecha_inicio_plan' => '2024-02-01 09:00:00',
            'fecha_fin_plan' => '2024-02-03 18:00:00',
            'precio_pagado' => 150.50,
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $inscripcion->fecha_inscripcion);
        $this->assertInstanceOf(\Carbon\Carbon::class, $inscripcion->fecha_inicio_plan);
        $this->assertInstanceOf(\Carbon\Carbon::class, $inscripcion->fecha_fin_plan);
        $this->assertEquals(150.50, $inscripcion->precio_pagado);
    }

    #[Test]
    public function plan_inscripcion_has_plan_relationship()
    {
        $inscripcion = PlanInscripcion::factory()->create([
            'plan_id' => $this->plan->id,
            'user_id' => $this->user->id,
            'estado' => 1, // La columna 'estado' es numérica
        ]);
        $this->assertInstanceOf(Plan::class, $inscripcion->plan);
        $this->assertEquals($this->plan->id, $inscripcion->plan->id);
    }

    #[Test]
    public function plan_inscripcion_has_user_relationship()
    {
        $inscripcion = PlanInscripcion::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'estado' => 1, // La columna 'estado' es numérica
        ]);
        $this->assertInstanceOf(User::class, $inscripcion->usuario);
        $this->assertEquals($this->user->id, $inscripcion->usuario->id);
    }

    #[Test]
    public function plan_inscripcion_calculates_precio_total_with_participants()
    {
        $inscripcion = PlanInscripcion::factory()->create([
            'plan_id' => $this->plan->id,
            'user_id' => $this->user->id,
            'numero_participantes' => 3,
            'estado' => 1, // La columna 'estado' es numérica
        ]);
        $this->assertEquals(600.00, $inscripcion->getPrecioTotalCalculadoAttribute());
    }

    #[Test]
    public function plan_inscripcion_scope_del_plan_filters_correctly()
    {
        $inscripcionPlan1 = PlanInscripcion::factory()->create([
            'plan_id' => $this->plan->id,
            'user_id' => $this->user->id,
            'estado' => 1, // La columna 'estado' es numérica
        ]);

        $asociacion = Asociacion::factory()->create();
        $emprendedor = Emprendedor::factory()->create(['asociacion_id' => $asociacion->id]);
        $otherPlan = Plan::factory()->create([
            'emprendedor_id' => $emprendedor->id,
            'creado_por_usuario_id' => User::factory()->create()->id,
            'estado' => 'activo'
        ]);
        $inscripcionPlan2 = PlanInscripcion::factory()->create([
            'plan_id' => $otherPlan->id,
            'user_id' => $this->user->id,
            'estado' => 1, // La columna 'estado' es numérica
        ]);

        $inscripcionesPlan1 = PlanInscripcion::delPlan($this->plan->id)->get();

        $this->assertCount(1, $inscripcionesPlan1);
        $this->assertTrue($inscripcionesPlan1->contains($inscripcionPlan1));
        $this->assertFalse($inscripcionesPlan1->contains($inscripcionPlan2));
    }
}
