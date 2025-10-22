<?php

namespace Database\Factories;

use App\Models\PlanInscripcion;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanInscripcionFactory extends Factory
{
    protected $model = PlanInscripcion::class;

    public function definition(): array
    {
        $fechaInicio = $this->faker->dateTimeBetween('+1 week', '+3 months');
        $fechaFin = (clone $fechaInicio)->modify('+3 days');

        return [
            'plan_id' => Plan::factory(),
            'user_id' => User::factory(),
            'estado' => $this->faker->randomElement([
                PlanInscripcion::ESTADO_PENDIENTE,
                PlanInscripcion::ESTADO_CONFIRMADA,
                PlanInscripcion::ESTADO_EN_PROGRESO,
            ]),
            'notas' => $this->faker->optional()->paragraph(),
            'fecha_inscripcion' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'fecha_inicio_plan' => $fechaInicio,
            'fecha_fin_plan' => $fechaFin,
            'notas_usuario' => $this->faker->optional()->sentence(),
            'requerimientos_especiales' => $this->faker->optional()->text(200),
            'numero_participantes' => $this->faker->numberBetween(1, 6),
            'precio_pagado' => $this->faker->randomFloat(2, 50, 500),
            'metodo_pago' => $this->faker->randomElement([
                PlanInscripcion::METODO_EFECTIVO,
                PlanInscripcion::METODO_TRANSFERENCIA,
                PlanInscripcion::METODO_TARJETA,
                PlanInscripcion::METODO_YAPE,
                PlanInscripcion::METODO_PLIN,
            ]),
            'comentarios_adicionales' => $this->faker->optional()->text(300),
        ];
    }

    public function pendiente(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => PlanInscripcion::ESTADO_PENDIENTE,
            'precio_pagado' => null,
            'metodo_pago' => null,
        ]);
    }

    public function confirmada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => PlanInscripcion::ESTADO_CONFIRMADA,
            'precio_pagado' => $this->faker->randomFloat(2, 100, 800),
        ]);
    }

    public function enProgreso(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => PlanInscripcion::ESTADO_EN_PROGRESO,
            'fecha_inicio_plan' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'fecha_fin_plan' => $this->faker->dateTimeBetween('now', '+1 week'),
        ]);
    }

    public function cancelada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => PlanInscripcion::ESTADO_CANCELADA,
        ]);
    }

    public function completada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => PlanInscripcion::ESTADO_COMPLETADA,
            'fecha_inicio_plan' => $this->faker->dateTimeBetween('-2 months', '-1 week'),
            'fecha_fin_plan' => $this->faker->dateTimeBetween('-1 week', '-1 day'),
        ]);
    }

    public function conPagoYape(): static
    {
        return $this->state(fn (array $attributes) => [
            'metodo_pago' => PlanInscripcion::METODO_YAPE,
            'precio_pagado' => $this->faker->randomFloat(2, 50, 300),
        ]);
    }

    public function individual(): static
    {
        return $this->state(fn (array $attributes) => [
            'numero_participantes' => 1,
        ]);
    }

    public function grupal(): static
    {
        return $this->state(fn (array $attributes) => [
            'numero_participantes' => $this->faker->numberBetween(4, 10),
        ]);
    }
}