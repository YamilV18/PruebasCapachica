<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\User;
use App\Models\Emprendedor;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        // Nombres de planes turísticos específicos para Capachica/Lago Titicaca
        $nombresPlan = [
            'Aventura Completa en el Titicaca',
            'Experiencia Cultural Capachica',
            'Ruta Gastronómica del Altiplano',
            'Trekking Islas Flotantes',
            'Paquete Familiar Lago Sagrado',
            'Expedición Fotográfica Titicaca',
            'Retiro Espiritual Andino',
            'Circuito Artesanal Tradicional',
            'Aventura Acuática Premium',
            'Descubrimiento Cultural Intensivo'
        ];

        // Descripciones realistas para turismo en la zona
        $descripciones = [
            'Descubre la magia del Lago Titicaca en este plan completo que combina aventura, cultura y gastronomía local.',
            'Sumérgete en las tradiciones ancestrales de Capachica con actividades auténticas y encuentros comunitarios.',
            'Explora los sabores únicos del altiplano en una experiencia gastronómica que incluye productos locales y técnicas tradicionales.',
            'Aventura de múltiples días navegando por las islas más hermosas del lago navegable más alto del mundo.',
            'Plan perfecto para familias que buscan conocer la cultura andina en un ambiente seguro y educativo.',
            'Captura la belleza natural y cultural del Titicaca con guías especializados en fotografía de paisajes.'
        ];

        // Qué incluye típico para planes turísticos
        $queIncluye = [
            'Transporte ida y vuelta, guía especializado, todas las comidas, hospedaje en casas locales',
            'Actividades culturales, talleres artesanales, degustaciones, materiales incluidos',
            'Navegación en totora, visitas a islas, almuerzo tradicional, certificado de participación',
            'Equipos de seguridad, instructor certificado, snacks energéticos, fotos profesionales',
            'Hospedaje 3 noches, todas las comidas, actividades familiares, transporte local',
            'Guía bilingüe, entrada a sitios arqueológicos, kit de primeros auxilios, seguro de viaje'
        ];

        // Requerimientos típicos
        $requerimientos = [
            'Edad mínima 12 años, condición física básica, no apto para embarazadas',
            'Documentos de identidad, ropa abrigada, zapatos cómodos para caminar',
            'Experiencia previa no necesaria, apto para principiantes',
            'Saber nadar, no tener problemas cardíacos, edad entre 16-65 años',
            'Autorización parental para menores, seguro médico vigente',
            'Nivel intermedio de caminata, resistencia a alturas (3800+ msnm)'
        ];

        // Qué llevar específico para turismo en Titicaca
        $queLlevar = [
            'Ropa abrigada, protector solar factor 50+, gorra, lentes de sol, cámara fotográfica',
            'Zapatos antideslizantes, mochila pequeña, botella de agua, snacks personales',
            'Documentos en bolsa impermeable, medicamentos personales, dinero en efectivo',
            'Ropa de cambio, toalla, traje de baño, sandalias, impermeable',
            'Libreta de notas, materiales de arte, ropa cómoda, gorro de lana',
            'Binoculares, cargador portátil, memoria extra para cámara, linterna pequeña'
        ];

        return [
            'nombre' => $this->faker->randomElement($nombresPlan),
            'descripcion' => $this->faker->randomElement($descripciones) . ' ' . $this->faker->sentence(12),
            'que_incluye' => $this->faker->randomElement($queIncluye),
            'capacidad' => $this->faker->numberBetween(6, 20),
            'duracion_dias' => $this->faker->numberBetween(1, 7),
            'es_publico' => $this->faker->boolean(80), // 80% públicos
            'estado' => $this->faker->randomElement([Plan::ESTADO_ACTIVO, Plan::ESTADO_ACTIVO, Plan::ESTADO_BORRADOR]), // Más activos
            'creado_por_usuario_id' => User::factory(),
            'emprendedor_id' => Emprendedor::factory(), // Mantener por compatibilidad
            'precio_total' => $this->faker->randomFloat(2, 150, 1200),
            'dificultad' => $this->faker->randomElement([Plan::DIFICULTAD_FACIL, Plan::DIFICULTAD_MODERADO, Plan::DIFICULTAD_DIFICIL]),
            'requerimientos' => $this->faker->randomElement($requerimientos),
            'que_llevar' => $this->faker->randomElement($queLlevar),
            'imagen_principal' => 'plans/' . $this->faker->uuid() . '.jpg',
            'imagenes_galeria' => $this->generarImagenesGaleria(),
        ];
    }

    /**
     * Generar array de imágenes para la galería
     */
    private function generarImagenesGaleria(): array
    {
        $numImagenes = $this->faker->numberBetween(2, 6);
        $imagenes = [];
        
        for ($i = 0; $i < $numImagenes; $i++) {
            $imagenes[] = 'plans/gallery/' . $this->faker->uuid() . '.jpg';
        }
        
        return $imagenes;
    }

    /**
     * Plan activo
     */
    public function activo(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => Plan::ESTADO_ACTIVO,
            'es_publico' => true,
        ]);
    }

    /**
     * Plan inactivo
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => Plan::ESTADO_INACTIVO,
        ]);
    }

    /**
     * Plan en borrador
     */
    public function borrador(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => Plan::ESTADO_BORRADOR,
            'es_publico' => false,
        ]);
    }

    /**
     * Plan público
     */
    public function publico(): static
    {
        return $this->state(fn (array $attributes) => [
            'es_publico' => true,
            'estado' => Plan::ESTADO_ACTIVO,
        ]);
    }

    /**
     * Plan privado
     */
    public function privado(): static
    {
        return $this->state(fn (array $attributes) => [
            'es_publico' => false,
        ]);
    }

    /**
     * Plan fácil
     */
    public function facil(): static
    {
        return $this->state(fn (array $attributes) => [
            'dificultad' => Plan::DIFICULTAD_FACIL,
            'duracion_dias' => $this->faker->numberBetween(1, 3),
            'capacidad' => $this->faker->numberBetween(10, 20),
        ]);
    }

    /**
     * Plan moderado
     */
    public function moderado(): static
    {
        return $this->state(fn (array $attributes) => [
            'dificultad' => Plan::DIFICULTAD_MODERADO,
            'duracion_dias' => $this->faker->numberBetween(2, 5),
            'capacidad' => $this->faker->numberBetween(8, 15),
        ]);
    }

    /**
     * Plan difícil
     */
    public function dificil(): static
    {
        return $this->state(fn (array $attributes) => [
            'dificultad' => Plan::DIFICULTAD_DIFICIL,
            'duracion_dias' => $this->faker->numberBetween(3, 7),
            'capacidad' => $this->faker->numberBetween(6, 12),
            'precio_total' => $this->faker->randomFloat(2, 500, 1500),
        ]);
    }

    /**
     * Plan familiar
     */
    public function familiar(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Aventura Familiar en el Titicaca',
            'dificultad' => Plan::DIFICULTAD_FACIL,
            'capacidad' => $this->faker->numberBetween(12, 20),
            'requerimientos' => 'Apto para todas las edades, actividades familiares',
            'que_incluye' => 'Actividades para niños, menús familiares, entretenimiento educativo',
        ]);
    }

    /**
     * Plan aventura
     */
    public function aventura(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Expedición Extrema Titicaca',
            'dificultad' => Plan::DIFICULTAD_DIFICIL,
            'requerimientos' => 'Excelente condición física, experiencia en deportes de aventura',
            'que_llevar' => 'Equipo de trekking, ropa técnica, equipo de seguridad personal',
            'precio_total' => $this->faker->randomFloat(2, 800, 1500),
        ]);
    }

    /**
     * Plan cultural
     */
    public function cultural(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Inmersión Cultural Capachica',
            'dificultad' => Plan::DIFICULTAD_FACIL,
            'que_incluye' => 'Talleres artesanales, ceremonias tradicionales, convivencia comunitaria',
            'requerimientos' => 'Respeto por las tradiciones locales, mente abierta',
        ]);
    }

    /**
     * Plan gastronómico
     */
    public function gastronomico(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Ruta Gastronómica del Altiplano',
            'que_incluye' => 'Degustaciones, clases de cocina, visitas a mercados, productos locales',
            'requerimientos' => 'No restricciones alimentarias severas',
            'que_llevar' => 'Delantal, cuaderno de recetas, cámara para fotos gastronómicas',
        ]);
    }

    /**
     * Plan con usuario específico
     */
    public function creadoPor(int $usuarioId): static
    {
        return $this->state(fn (array $attributes) => [
            'creado_por_usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Plan con emprendedor específico
     */
    public function deEmprendedor(int $emprendedorId): static
    {
        return $this->state(fn (array $attributes) => [
            'emprendedor_id' => $emprendedorId,
        ]);
    }

    /**
     * Plan premium con precio alto
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'precio_total' => $this->faker->randomFloat(2, 1000, 2000),
            'capacidad' => $this->faker->numberBetween(4, 10), // Grupos pequeños
            'que_incluye' => 'Servicios premium, guía privado, transporte exclusivo, hospedaje de lujo',
        ]);
    }

    /**
     * Plan económico
     */
    public function economico(): static
    {
        return $this->state(fn (array $attributes) => [
            'precio_total' => $this->faker->randomFloat(2, 80, 300),
            'capacidad' => $this->faker->numberBetween(15, 25), // Grupos grandes
            'duracion_dias' => $this->faker->numberBetween(1, 2),
        ]);
    }

    /**
     * Plan con múltiples días
     */
    public function multipleDias(): static
    {
        return $this->state(fn (array $attributes) => [
            'duracion_dias' => $this->faker->numberBetween(4, 7),
            'precio_total' => $this->faker->randomFloat(2, 600, 1400),
            'que_incluye' => 'Hospedaje todas las noches, pensión completa, transporte entre sitios',
        ]);
    }
}