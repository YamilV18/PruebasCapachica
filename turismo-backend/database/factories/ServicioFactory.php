<?php

namespace Database\Factories;

use App\Models\Servicio;
use App\Models\Emprendedor;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServicioFactory extends Factory
{
    protected $model = Servicio::class;

    public function definition(): array
    {
        $servicios = [
            'Tour en Kayak',
            'Caminata por Senderos',
            'Observación de Aves',
            'Pesca Vivencial',
            'Almuerzo Tradicional',
            'Degustación de Trucha',
            'Camping Nocturno',
            'Fotografía Paisajística',
            'Ciclismo de Montaña',
            'Artesanía Local'
        ];

        return [
            'nombre' => $this->faker->randomElement($servicios),
            'descripcion' => $this->faker->text(300),
            'precio_referencial' => $this->faker->randomFloat(2, 15.00, 250.00),
            'emprendedor_id' => Emprendedor::factory(),
            'estado' => $this->faker->boolean(85), // 85% probabilidad de estar activo
            'capacidad' => $this->faker->numberBetween(1, 20),
            'latitud' => $this->faker->latitude(-15.85, -15.83), // Área de Puno
            'longitud' => $this->faker->longitude(-70.03, -70.01), // Área de Puno
            'ubicacion_referencia' => $this->faker->optional(0.8)->address(),
        ];
    }

    /**
     * Servicio activo
     */
    public function activo(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => true,
        ]);
    }

    /**
     * Servicio inactivo
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => false,
        ]);
    }

    /**
     * Servicio con precio alto
     */
    public function precioAlto(): static
    {
        return $this->state(fn (array $attributes) => [
            'precio_referencial' => $this->faker->randomFloat(2, 150.00, 500.00),
        ]);
    }

    /**
     * Servicio con precio bajo
     */
    public function precioBajo(): static
    {
        return $this->state(fn (array $attributes) => [
            'precio_referencial' => $this->faker->randomFloat(2, 10.00, 50.00),
        ]);
    }

    /**
     * Servicio sin coordenadas
     */
    public function sinCoordenadas(): static
    {
        return $this->state(fn (array $attributes) => [
            'latitud' => null,
            'longitud' => null,
        ]);
    }

    /**
     * Servicio con capacidad alta
     */
    public function capacidadAlta(): static
    {
        return $this->state(fn (array $attributes) => [
            'capacidad' => $this->faker->numberBetween(15, 50),
        ]);
    }

    /**
     * Servicio individual (capacidad 1)
     */
    public function individual(): static
    {
        return $this->state(fn (array $attributes) => [
            'capacidad' => 1,
        ]);
    }

    /**
     * Servicio familiar (capacidad 4-6)
     */
    public function familiar(): static
    {
        return $this->state(fn (array $attributes) => [
            'capacidad' => $this->faker->numberBetween(4, 6),
        ]);
    }

    /**
     * Servicio grupal (capacidad 8-12)
     */
    public function grupal(): static
    {
        return $this->state(fn (array $attributes) => [
            'capacidad' => $this->faker->numberBetween(8, 12),
        ]);
    }

    /**
     * Tour en kayak específico
     */
    public function tourKayak(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Tour en Kayak por el Lago Titicaca',
            'descripcion' => 'Experiencia única navegando en kayak por las aguas cristalinas del Lago Titicaca, con vistas espectaculares de los Andes.',
            'precio_referencial' => 45.00,
            'capacidad' => 6,
            'estado' => true,
        ]);
    }

    /**
     * Degustación gastronómica específica
     */
    public function degustacionGastronomica(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Degustación de Trucha y Quinua',
            'descripcion' => 'Experiencia culinaria con productos locales frescos, preparados con técnicas tradicionales.',
            'precio_referencial' => 35.00,
            'capacidad' => 8,
            'estado' => true,
        ]);
    }

    /**
     * Caminata ecológica específica
     */
    public function caminataEcologica(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Caminata Ecológica por Senderos Andinos',
            'descripcion' => 'Exploración de la flora y fauna local a través de senderos tradicionales con guía especializado.',
            'precio_referencial' => 25.00,
            'capacidad' => 10,
            'estado' => true,
        ]);
    }

    /**
     * Servicio sin ubicación de referencia
     */
    public function sinUbicacionReferencia(): static
    {
        return $this->state(fn (array $attributes) => [
            'ubicacion_referencia' => null,
        ]);
    }
}