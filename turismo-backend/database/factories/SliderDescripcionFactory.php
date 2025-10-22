<?php

namespace Database\Factories;

use App\Models\SliderDescripcion;
use App\Models\Slider;
use Illuminate\Database\Eloquent\Factories\Factory;

class SliderDescripcionFactory extends Factory
{
    protected $model = SliderDescripcion::class;

    public function definition(): array
    {
        return [
            'slider_id' => Slider::factory(),
            'titulo' => $this->faker->sentence(4, true),
            'descripcion' => $this->faker->paragraph(3),
        ];
    }

    public function conTituloCorto(): static
    {
        return $this->state(fn (array $attributes) => [
            'titulo' => $this->faker->words(2, true),
        ]);
    }

    public function conDescripcionLarga(): static
    {
        return $this->state(fn (array $attributes) => [
            'descripcion' => $this->faker->paragraphs(5, true),
        ]);
    }
}