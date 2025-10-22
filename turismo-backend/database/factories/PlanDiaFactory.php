<?php

namespace Database\Factories;

use App\Models\PlanDia;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanDiaFactory extends Factory
{
    protected $model = PlanDia::class;

    public function definition(): array
    {
        // Títulos de días turísticos específicos para Capachica/Lago Titicaca
        $titulos = [
            'Llegada y Orientación',
            'Navegación a Islas Flotantes',
            'Experiencia Cultural Comunitaria',
            'Trekking y Observación de Paisajes',
            'Actividades Acuáticas',
            'Talleres Artesanales',
            'Gastronomía Local',
            'Ceremonias Tradicionales',
            'Exploración Arqueológica',
            'Despedida y Reflexión'
        ];

        // Descripciones específicas para cada tipo de día
        $descripciones = [
            'Recepción de participantes, bienvenida con coca tea, orientación sobre el programa y distribución de habitaciones.',
            'Navegación temprana hacia las islas flotantes de los Uros, aprendizaje sobre construcción de totora y vida lacustre.',
            'Convivencia con familias locales, participación en actividades cotidianas y intercambio cultural auténtico.',
            'Caminata guiada por senderos ancestrales con vistas panorámicas del lago y explicación de flora y fauna local.',
            'Actividades acuáticas como kayak, navegación en balsas de totora y pesca tradicional con redes.',
            'Aprendizaje de técnicas textiles tradicionales, cerámica ancestral y elaboración de artesanías locales.',
            'Preparación de platos típicos, visita al mercado local y degustación de productos del altiplano.',
            'Participación en rituales andinos, ofrendas a la Pachamama y ceremonias de agradecimiento.',
            'Exploración de sitios arqueológicos pre-incas, visita a chullpas y explicación de historia local.',
            'Sesión de reflexión grupal, intercambio de experiencias y ceremonia de cierre del programa.'
        ];

        // Horarios típicos para actividades turísticas
        $horasInicio = ['06:00', '07:00', '08:00', '09:00', '14:00', '15:00', '16:00'];
        $duracionMinutos = [180, 240, 300, 360, 480]; // 3-8 horas

        $horaInicio = $this->faker->randomElement($horasInicio);
        $duracion = $this->faker->randomElement($duracionMinutos);
        $horaFin = date('H:i', strtotime($horaInicio . ' +' . $duracion . ' minutes'));

        return [
            'plan_id' => Plan::factory(),
            'numero_dia' => $this->faker->numberBetween(1, 7),
            'titulo' => $this->faker->randomElement($titulos),
            'descripcion' => $this->faker->randomElement($descripciones),
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'duracion_estimada_minutos' => $duracion,
            'notas_adicionales' => $this->faker->optional(0.3)->sentence(8),
            'orden' => $this->faker->numberBetween(1, 10),
        ];
    }

    /**
     * Día de llegada
     */
    public function llegada(): static
    {
        return $this->state(fn (array $attributes) => [
            'numero_dia' => 1,
            'titulo' => 'Llegada y Bienvenida',
            'descripcion' => 'Recepción de participantes, bienvenida con mate de coca, orientación del programa y acomodación.',
            'hora_inicio' => '14:00',
            'hora_fin' => '18:00',
            'duracion_estimada_minutos' => 240,
            'orden' => 1,
        ]);
    }

    /**
     * Día de actividad acuática
     */
    public function actividadAcuatica(): static
    {
        return $this->state(fn (array $attributes) => [
            'titulo' => 'Aventura Acuática en el Titicaca',
            'descripcion' => 'Navegación en kayaks, exploración de bahías secretas y pesca tradicional con redes.',
            'hora_inicio' => '07:00',
            'hora_fin' => '16:00',
            'duracion_estimada_minutos' => 540,
            'notas_adicionales' => 'Incluye equipos de seguridad acuática y almuerzo campestre',
        ]);
    }

    /**
     * Día cultural
     */
    public function cultural(): static
    {
        return $this->state(fn (array $attributes) => [
            'titulo' => 'Inmersión Cultural Comunitaria',
            'descripcion' => 'Convivencia con familias locales, participación en ceremonias tradicionales y talleres artesanales.',
            'hora_inicio' => '08:00',
            'hora_fin' => '17:00',
            'duracion_estimada_minutos' => 540,
            'notas_adicionales' => 'Respeto por tradiciones locales, vestimenta apropiada',
        ]);
    }

    /**
     * Día gastronómico
     */
    public function gastronomico(): static
    {
        return $this->state(fn (array $attributes) => [
            'titulo' => 'Experiencia Gastronómica Altiplánica',
            'descripcion' => 'Preparación de platos típicos, visita al mercado local y degustación de productos únicos del lago.',
            'hora_inicio' => '09:00',
            'hora_fin' => '15:00',
            'duracion_estimada_minutos' => 360,
            'notas_adicionales' => 'Incluye delantal y recetario tradicional',
        ]);
    }

    /**
     * Día de trekking
     */
    public function trekking(): static
    {
        return $this->state(fn (array $attributes) => [
            'titulo' => 'Trekking Senderos Ancestrales',
            'descripcion' => 'Caminata por senderos prehispánicos con vistas panorámicas del lago y observación de fauna local.',
            'hora_inicio' => '06:00',
            'hora_fin' => '14:00',
            'duracion_estimada_minutos' => 480,
            'notas_adicionales' => 'Nivel de dificultad moderado, llevar zapatos de trekking',
        ]);
    }

    /**
     * Día de despedida
     */
    public function despedida(): static
    {
        return $this->state(fn (array $attributes) => [
            'titulo' => 'Ceremonia de Despedida',
            'descripcion' => 'Reflexión grupal, intercambio de experiencias, ceremonia de agradecimiento y traslado.',
            'hora_inicio' => '09:00',
            'hora_fin' => '12:00',
            'duracion_estimada_minutos' => 180,
            'notas_adicionales' => 'Entrega de certificados de participación',
        ]);
    }

    /**
     * Día con plan específico
     */
    public function dePlan(int $planId): static
    {
        return $this->state(fn (array $attributes) => [
            'plan_id' => $planId,
        ]);
    }

    /**
     * Día matutino
     */
    public function matutino(): static
    {
        return $this->state(fn (array $attributes) => [
            'hora_inicio' => $this->faker->randomElement(['06:00', '07:00', '08:00']),
            'hora_fin' => $this->faker->randomElement(['11:00', '12:00', '13:00']),
            'duracion_estimada_minutos' => $this->faker->numberBetween(180, 360),
        ]);
    }

    /**
     * Día vespertino
     */
    public function vespertino(): static
    {
        return $this->state(fn (array $attributes) => [
            'hora_inicio' => $this->faker->randomElement(['14:00', '15:00', '16:00']),
            'hora_fin' => $this->faker->randomElement(['18:00', '19:00', '20:00']),
            'duracion_estimada_minutos' => $this->faker->numberBetween(240, 360),
        ]);
    }

    /**
     * Día completo
     */
    public function diaCompleto(): static
    {
        return $this->state(fn (array $attributes) => [
            'hora_inicio' => '08:00',
            'hora_fin' => '18:00',
            'duracion_estimada_minutos' => 600,
            'notas_adicionales' => 'Jornada completa con almuerzo incluido',
        ]);
    }

    /**
     * Día con número específico
     */
    public function dia(int $numeroDia): static
    {
        return $this->state(fn (array $attributes) => [
            'numero_dia' => $numeroDia,
            'orden' => $numeroDia,
        ]);
    }
}