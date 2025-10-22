<?php

namespace Database\Factories;

use App\Models\Evento;
use App\Models\Emprendedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Evento>
 */
class EventoFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Evento::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->sentence(3),
            'descripcion' => $this->faker->paragraph(),
            'tipo_evento' => $this->faker->randomElement(['Festival', 'Ceremonia Tradicional', 'Concierto', 'Feria', 'Exposición']),
            'fecha_inicio' => $this->faker->dateTimeBetween('now', '+1 year'),
            'fecha_fin' => $this->faker->dateTimeBetween('+1 day', '+1 year'),
            'hora_inicio' => $this->faker->time('H:i:s'),
            'hora_fin' => $this->faker->time('H:i:s'),
            'ubicacion' => $this->faker->address(),
            'precio_entrada' => $this->faker->randomFloat(2, 0, 100),
            'capacidad_maxima' => $this->faker->numberBetween(50, 1000),
            'estado' => $this->faker->boolean(80), // 80% probabilidad de estar activo
            'id_emprendedor' => Emprendedor::factory(),
        ];
    }

    /**
     * Indicate that the event is active.
     */
    public function activo(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => true,
        ]);
    }

    /**
     * Indicate that the event is inactive.
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => false,
        ]);
    }

    /**
     * Indicate that the event is a festival.
     */
    public function festival(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_evento' => 'Festival',
        ]);
    }

    /**
     * Indicate that the event is a traditional ceremony.
     */
    public function ceremonia(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_evento' => 'Ceremonia Tradicional',
        ]);
    }
}