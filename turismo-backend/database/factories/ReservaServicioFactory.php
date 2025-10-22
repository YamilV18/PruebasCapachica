<?php

namespace Database\Factories;

use App\Models\ReservaServicio;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\Emprendedor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class ReservaServicioFactory extends Factory
{
    protected $model = ReservaServicio::class;

    public function definition(): array
    {
        // Fechas futuras para servicios reservados
        $fechaInicio = $this->faker->dateTimeBetween('now', '+3 months');
        $fechaFin = $this->faker->optional(0.3)->dateTimeBetween($fechaInicio, $fechaInicio->format('Y-m-d') . ' +7 days');

        // Horarios realistas para servicios turísticos
        $horasInicio = ['06:00:00', '07:00:00', '08:00:00', '09:00:00', '10:00:00', '14:00:00', '15:00:00', '16:00:00'];
        $horaInicio = $this->faker->randomElement($horasInicio);
        
        $duracionMinutos = $this->faker->randomElement([60, 90, 120, 180, 240, 300, 360, 480]); // 1-8 horas
        $horaFin = Carbon::createFromFormat('H:i:s', $horaInicio)->addMinutes($duracionMinutos)->format('H:i:s');

        // Precios realistas para servicios turísticos en soles
        $precios = [25.00, 35.00, 50.00, 75.00, 100.00, 150.00, 200.00, 250.00, 300.00];

        return [
            'reserva_id' => Reserva::factory(),
            'servicio_id' => Servicio::factory(),
            'emprendedor_id' => Emprendedor::factory(),
            'fecha_inicio' => $fechaInicio->format('Y-m-d'),
            'fecha_fin' => $fechaFin ? $fechaFin->format('Y-m-d') : null,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'duracion_minutos' => $duracionMinutos,
            'cantidad' => $this->faker->numberBetween(1, 6),
            'precio' => $this->faker->randomElement($precios),
            'estado' => $this->faker->randomElement([
                ReservaServicio::ESTADO_PENDIENTE,
                ReservaServicio::ESTADO_CONFIRMADO
            ]),
            'notas_cliente' => $this->faker->optional(0.4)->sentence(8),
            'notas_emprendedor' => $this->faker->optional(0.2)->sentence(6),
        ];
    }

    /**
     * Servicio en carrito
     */
    public function enCarrito(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => ReservaServicio::ESTADO_EN_CARRITO,
        ]);
    }

    /**
     * Servicio pendiente
     */
    public function pendiente(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => ReservaServicio::ESTADO_PENDIENTE,
        ]);
    }

    /**
     * Servicio confirmado
     */
    public function confirmado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => ReservaServicio::ESTADO_CONFIRMADO,
        ]);
    }

    /**
     * Servicio cancelado
     */
    public function cancelado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => ReservaServicio::ESTADO_CANCELADO,
        ]);
    }

    /**
     * Servicio completado
     */
    public function completado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => ReservaServicio::ESTADO_COMPLETADO,
        ]);
    }

    /**
     * Servicio de un día completo
     */
    public function unDia(): static
    {
        $fecha = $this->faker->dateTimeBetween('now', '+2 months')->format('Y-m-d');
        
        return $this->state(fn (array $attributes) => [
            'fecha_inicio' => $fecha,
            'fecha_fin' => $fecha,
            'hora_inicio' => '08:00:00',
            'hora_fin' => '17:00:00',
            'duracion_minutos' => 540, // 9 horas
        ]);
    }

    /**
     * Servicio de múltiples días
     */
    public function multipleDias(): static
    {
        $fechaInicio = $this->faker->dateTimeBetween('now', '+2 months');
        $fechaFin = (clone $fechaInicio)->modify('+' . $this->faker->numberBetween(2, 5) . ' days');
        
        return $this->state(fn (array $attributes) => [
            'fecha_inicio' => $fechaInicio->format('Y-m-d'),
            'fecha_fin' => $fechaFin->format('Y-m-d'),
            'duracion_minutos' => $this->faker->numberBetween(240, 480), // 4-8 horas por día
        ]);
    }

    /**
     * Servicio matutino
     */
    public function matutino(): static
    {
        $horaInicio = $this->faker->randomElement(['06:00:00', '07:00:00', '08:00:00']);
        $duracion = $this->faker->numberBetween(120, 240); // 2-4 horas
        $horaFin = Carbon::createFromFormat('H:i:s', $horaInicio)->addMinutes($duracion)->format('H:i:s');

        return $this->state(fn (array $attributes) => [
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'duracion_minutos' => $duracion,
        ]);
    }

    /**
     * Servicio vespertino
     */
    public function vespertino(): static
    {
        $horaInicio = $this->faker->randomElement(['14:00:00', '15:00:00', '16:00:00']);
        $duracion = $this->faker->numberBetween(180, 300); // 3-5 horas
        $horaFin = Carbon::createFromFormat('H:i:s', $horaInicio)->addMinutes($duracion)->format('H:i:s');

        return $this->state(fn (array $attributes) => [
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'duracion_minutos' => $duracion,
        ]);
    }

    /**
     * Servicio gastronómico
     */
    public function gastronomico(): static
    {
        return $this->state(fn (array $attributes) => [
            'hora_inicio' => $this->faker->randomElement(['12:00:00', '13:00:00', '19:00:00', '20:00:00']),
            'hora_fin' => $this->faker->randomElement(['14:00:00', '15:00:00', '21:00:00', '22:00:00']),
            'duracion_minutos' => $this->faker->randomElement([90, 120, 150]), // 1.5-2.5 horas
            'precio' => $this->faker->randomElement([45.00, 65.00, 85.00, 120.00]),
            'notas_cliente' => $this->faker->randomElement([
                'Preferencia por comida no muy picante',
                'Vegetariano, sin carne ni pescado',
                'Alérgico a mariscos',
                'Interesado en platos tradicionales',
                'Celebración especial, aniversario'
            ]),
        ]);
    }

    /**
     * Servicio de aventura
     */
    public function aventura(): static
    {
        return $this->state(fn (array $attributes) => [
            'hora_inicio' => $this->faker->randomElement(['06:00:00', '07:00:00', '08:00:00']),
            'duracion_minutos' => $this->faker->randomElement([240, 300, 360, 480]), // 4-8 horas
            'precio' => $this->faker->randomElement([80.00, 120.00, 180.00, 250.00]),
            'notas_cliente' => $this->faker->randomElement([
                'Nivel principiante, primera vez',
                'Experiencia intermedia en deportes acuáticos',
                'Grupo con niños, actividad familiar',
                'Buscan máxima adrenalina',
                'Fotografía incluida por favor'
            ]),
        ]);
    }

    /**
     * Servicio cultural
     */
    public function cultural(): static
    {
        return $this->state(fn (array $attributes) => [
            'hora_inicio' => $this->faker->randomElement(['09:00:00', '10:00:00', '15:00:00']),
            'duracion_minutos' => $this->faker->randomElement([120, 180, 240]), // 2-4 horas
            'precio' => $this->faker->randomElement([35.00, 50.00, 75.00, 100.00]),
            'notas_cliente' => $this->faker->randomElement([
                'Interés especial en historia local',
                'Solicitan guía en inglés',
                'Grupo de estudiantes universitarios',
                'Enfoque en tradiciones textiles',
                'Ceremonia tradicional incluida'
            ]),
        ]);
    }

    /**
     * Servicio con reserva específica
     */
    public function conReserva(int $reservaId): static
    {
        return $this->state(fn (array $attributes) => [
            'reserva_id' => $reservaId,
        ]);
    }

    /**
     * Servicio con emprendedor específico
     */
    public function conEmprendedor(int $emprendedorId): static
    {
        return $this->state(fn (array $attributes) => [
            'emprendedor_id' => $emprendedorId,
        ]);
    }

    /**
     * Servicio con servicio específico
     */
    public function conServicio(int $servicioId): static
    {
        return $this->state(fn (array $attributes) => [
            'servicio_id' => $servicioId,
        ]);
    }

    /**
     * Servicio con precio específico
     */
    public function conPrecio(float $precio): static
    {
        return $this->state(fn (array $attributes) => [
            'precio' => $precio,
        ]);
    }

    /**
     * Servicio con cantidad específica
     */
    public function conCantidad(int $cantidad): static
    {
        return $this->state(fn (array $attributes) => [
            'cantidad' => $cantidad,
        ]);
    }

    /**
     * Servicio con notas del cliente
     */
    public function conNotasCliente(): static
    {
        return $this->state(fn (array $attributes) => [
            'notas_cliente' => $this->faker->paragraph(2),
        ]);
    }

    /**
     * Servicio con notas del emprendedor
     */
    public function conNotasEmprendedor(): static
    {
        return $this->state(fn (array $attributes) => [
            'notas_emprendedor' => $this->faker->paragraph(1),
        ]);
    }

    /**
     * Servicio premium (precio alto)
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'precio' => $this->faker->randomElement([300.00, 450.00, 600.00, 800.00]),
            'notas_cliente' => 'Servicio premium, experiencia exclusiva solicitada',
        ]);
    }

    /**
     * Servicio económico (precio bajo)
     */
    public function economico(): static
    {
        return $this->state(fn (array $attributes) => [
            'precio' => $this->faker->randomElement([15.00, 25.00, 35.00, 45.00]),
            'cantidad' => $this->faker->numberBetween(1, 2),
        ]);
    }
}