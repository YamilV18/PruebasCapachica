<?php

namespace Database\Factories;

use App\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoriaFactory extends Factory
{
    protected $model = Categoria::class;

    public function definition(): array
    {
        $categorias = [
            'Aventura',
            'Gastronomía',
            'Cultural',
            'Naturaleza',
            'Deportes',
            'Relajación',
            'Entretenimiento',
            'Educativo',
            'Familiar',
            'Romántico'
        ];

        return [
            'nombre' => $this->faker->randomElement($categorias),
            'descripcion' => $this->faker->optional(0.8)->text(200),
            'icono_url' => $this->faker->optional(0.7)->imageUrl(64, 64, 'business'),
        ];
    }

    /**
     * Categoría con descripción completa
     */
    public function conDescripcion(): static
    {
        return $this->state(fn (array $attributes) => [
            'descripcion' => $this->faker->text(200),
        ]);
    }

    /**
     * Categoría sin descripción
     */
    public function sinDescripcion(): static
    {
        return $this->state(fn (array $attributes) => [
            'descripcion' => null,
        ]);
    }

    /**
     * Categoría con icono
     */
    public function conIcono(): static
    {
        return $this->state(fn (array $attributes) => [
            'icono_url' => $this->faker->imageUrl(64, 64, 'business'),
        ]);
    }

    /**
     * Categoría sin icono
     */
    public function sinIcono(): static
    {
        return $this->state(fn (array $attributes) => [
            'icono_url' => null,
        ]);
    }

    /**
     * Categoría con URL externa de icono
     */
    public function conIconoExterno(): static
    {
        return $this->state(fn (array $attributes) => [
            'icono_url' => 'https://example.com/icons/' . $this->faker->uuid() . '.png',
        ]);
    }

    /**
     * Categoría específica para aventura
     */
    public function aventura(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Aventura',
            'descripcion' => 'Actividades emocionantes y llenas de adrenalina para los más aventureros.',
            'icono_url' => 'https://example.com/icons/aventura.png',
        ]);
    }

    /**
     * Categoría específica para gastronomía
     */
    public function gastronomia(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Gastronomía',
            'descripcion' => 'Experiencias culinarias únicas con sabores locales e internacionales.',
            'icono_url' => 'https://example.com/icons/gastronomia.png',
        ]);
    }

    /**
     * Categoría específica para cultura
     */
    public function cultural(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Cultural',
            'descripcion' => 'Descubre la rica historia y tradiciones del lugar.',
            'icono_url' => 'https://example.com/icons/cultura.png',
        ]);
    }
}
