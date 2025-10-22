<?php

// database/factories/AsociacionFactory.php
namespace Database\Factories;

use App\Models\Asociacion;
use App\Models\Municipalidad;
use Illuminate\Database\Eloquent\Factories\Factory;

class AsociacionFactory extends Factory
{
    protected $model = Asociacion::class;

    public function definition(): array
    {
        return [
            'nombre' => $this->faker->company(),
            'descripcion' => $this->faker->text(200),
            'latitud' => $this->faker->latitude(-18, -13), // Rango aproximado de Perú
            'longitud' => $this->faker->longitude(-82, -68), // Rango aproximado de Perú
            'telefono' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'municipalidad_id' => Municipalidad::factory(),
            'estado' => $this->faker->boolean(80), // 80% probabilidad de estar activo
            'imagen' => null,
        ];
    }

    /**
     * Asociación activa
     */
    public function activa(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => true,
        ]);
    }

    /**
     * Asociación inactiva
     */
    public function inactiva(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => false,
        ]);
    }

    /**
     * Asociación con imagen
     */
    public function conImagen(): static
    {
        return $this->state(fn (array $attributes) => [
            'imagen' => 'asociaciones/' . $this->faker->uuid() . '.jpg',
        ]);
    }

    /**
     * Asociación con URL externa de imagen
     */
    public function conImagenExterna(): static
    {
        return $this->state(fn (array $attributes) => [
            'imagen' => 'https://example.com/images/' . $this->faker->uuid() . '.jpg',
        ]);
    }

    /**
     * Asociación sin coordenadas
     */
    public function sinCoordenadas(): static
    {
        return $this->state(fn (array $attributes) => [
            'latitud' => null,
            'longitud' => null,
        ]);
    }

    /**
     * Asociación con datos mínimos
     */
    public function minima(): static
    {
        return $this->state(fn (array $attributes) => [
            'descripcion' => null,
            'latitud' => null,
            'longitud' => null,
            'telefono' => null,
            'email' => null,
            'imagen' => null,
        ]);
    }

    /**
     * Asociación en ubicación específica (Puno, Perú)
     */
    public function enPuno(): static
    {
        return $this->state(fn (array $attributes) => [
            'latitud' => $this->faker->randomFloat(6, -15.85, -15.83),
            'longitud' => $this->faker->randomFloat(6, -70.03, -70.01)
        ]);
    }
}

