<?php

namespace Database\Factories;

use App\Models\Slider;
use App\Models\Emprendedor;
use App\Models\Servicio;
use App\Models\Evento;
use Illuminate\Database\Eloquent\Factories\Factory;

class SliderFactory extends Factory
{
    protected $model = Slider::class;

    public function definition(): array
    {
        return [
            'url' => $this->faker->imageUrl(1200, 600, 'nature'),
            'nombre' => $this->faker->sentence(3),
            'es_principal' => $this->faker->boolean(20), // 20% probabilidad de ser principal
            'tipo_entidad' => null,
            'entidad_id' => null,
            'orden' => $this->faker->numberBetween(1, 10),
            'activo' => true,
        ];
    }

    public function principal(): static
    {
        return $this->state(fn (array $attributes) => [
            'es_principal' => true,
            'orden' => 1,
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }

    public function paraEmprendedor(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_entidad' => Emprendedor::class,
            'entidad_id' => Emprendedor::factory(),
        ]);
    }

    public function paraServicio(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_entidad' => Servicio::class,
            'entidad_id' => Servicio::factory(),
        ]);
    }

    public function paraEvento(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_entidad' => Evento::class,
            'entidad_id' => Evento::factory(),
        ]);
    }
}