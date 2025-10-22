<?php


namespace Tests\Integradas;
// Cambiado a Feature para indicar un flujo más amplio

use App\Models\Asociacion;
use App\Models\Emprendedor;
use App\Models\Municipalidad;
use App\Models\Plan;
use App\Models\PlanInscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PlanIntegradaTest extends TestCase
{
    use RefreshDatabase;

    protected User $usuarioEmprendedor;
    protected User $usuarioTurista;
    protected Emprendedor $emprendedor;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Crear la estructura base (Municipalidad, Asociación)
        $municipalidad = Municipalidad::factory()->create();
        $asociacion = Asociacion::factory()->create(['municipalidad_id' => $municipalidad->id]);

        // 2. Crear el usuario Emprendedor y el modelo Emprendedor asociado
        $this->usuarioEmprendedor = User::factory()->create();
        $this->emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $asociacion->id
        ]);

        // 3. Crear el usuario Turista
        $this->usuarioTurista = User::factory()->create();
    }

    #[Test]
    public function flujo_completo_creacion_inscripcion_y_gestion_de_cupos()
    {
        // ARRANGE: Datos para crear un plan
        $capacidadInicial = 10;
        $precioUnitario = 150.00;
        $dataPlan = [
            'nombre' => 'Aventura Integrada',
            'descripcion' => 'Prueba de flujo completo',
            'capacidad' => $capacidadInicial,
            'duracion_dias' => 2,
            'es_publico' => true,
            'estado' => Plan::ESTADO_ACTIVO,
            'creado_por_usuario_id' => $this->usuarioEmprendedor->id,
            'emprendedor_id' => $this->emprendedor->id,
            'precio_total' => $precioUnitario,
            'dificultad' => Plan::DIFICULTAD_MODERADO,
        ];

        // 1. CREACIÓN DEL PLAN (Verificación Unit-like)
        $plan = Plan::create($dataPlan);

        // ASSERT 1: Plan creado y propiedades correctas
        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertEquals($capacidadInicial, $plan->capacidad);
        $this->assertEquals($this->emprendedor->id, $plan->emprendedor->id);
        $this->assertTrue($plan->tieneCuposDisponibles());
        $this->assertEquals($capacidadInicial, $plan->cupos_disponibles);

        // 2. PRIMERA INSCRIPCIÓN (Estado 'confirmada')
        $participantes1 = 3;
        $inscripcion1 = PlanInscripcion::factory()->create([
            'plan_id' => $plan->id,
            'user_id' => $this->usuarioTurista->id,
            'estado' => 'confirmada', // Asumimos 'confirmada' es el estado que consume cupo
            'numero_participantes' => $participantes1,
            'precio_pagado' => $precioUnitario * $participantes1,
        ]);

        // ASSERT 2: La inscripción se creó correctamente y el precio total coincide
        $this->assertInstanceOf(PlanInscripcion::class, $inscripcion1);
        $this->assertEquals($plan->id, $inscripcion1->plan->id);
        $this->assertEquals($this->usuarioTurista->id, $inscripcion1->usuario->id);
        $this->assertEquals($precioUnitario * $participantes1, $inscripcion1->getPrecioTotalCalculadoAttribute());

        // ASSERT 3: Los cupos disponibles se redujeron
        $cuposEsperados1 = $capacidadInicial - $participantes1;
        $this->assertEquals($cuposEsperados1, $plan->fresh()->cupos_disponibles);
        $this->assertTrue($plan->fresh()->tieneCuposDisponibles());


        // 3. SEGUNDA INSCRIPCIÓN (Estado 'pendiente' - No consume cupo)
        $participantes2 = 5;
        $inscripcion2 = PlanInscripcion::factory()->create([
            'plan_id' => $plan->id,
            'user_id' => User::factory()->create()->id, // Otro turista
            'estado' => 'pendiente',
            'numero_participantes' => $participantes2,
        ]);

        // ASSERT 4: Los cupos disponibles NO se modifican con una inscripción pendiente
        $this->assertEquals($cuposEsperados1, $plan->fresh()->cupos_disponibles);
        $this->assertTrue($plan->fresh()->tieneCuposDisponibles());

        // 4. TERCERA INSCRIPCIÓN (Estado 'confirmada' - Agota cupos)
        $participantes3 = $cuposEsperados1; // 7 participantes
        $inscripcion3 = PlanInscripcion::factory()->create([
            'plan_id' => $plan->id,
            'user_id' => User::factory()->create()->id, // Tercer turista
            'estado' => 'confirmada',
            'numero_participantes' => $participantes3,
        ]);

        // ASSERT 5: Los cupos disponibles llegan a cero
        $this->assertEquals(0, $plan->fresh()->cupos_disponibles);
        $this->assertFalse($plan->fresh()->tieneCuposDisponibles());

        // 5. Verificación de Ámbitos (Scopes)
        $planesActivos = Plan::activos()->get();
        $this->assertTrue($planesActivos->contains($plan)); // El plan está activo

        $inscripcionesDelPlan = PlanInscripcion::delPlan($plan->id)->get();
        $this->assertCount(3, $inscripcionesDelPlan); // Incluye la confirmada, la pendiente y la que agota cupo
    }
}
