<?php

namespace Database\Factories;

use App\Models\Reserva;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservaFactory extends Factory
{
    protected $model = Reserva::class;

    public function definition(): array
    {
        return [
            'usuario_id' => User::factory(),
            'codigo_reserva' => $this->generarCodigoReserva(),
            'estado' => $this->faker->randomElement([
                Reserva::ESTADO_PENDIENTE,
                Reserva::ESTADO_CONFIRMADA,
                Reserva::ESTADO_COMPLETADA
            ]),
            'notas' => $this->faker->optional(0.3)->paragraph(2),
        ];
    }

    /**
     * Genera un código único para la reserva
     */
    private function generarCodigoReserva(): string
    {
        $codigo = strtoupper($this->faker->bothify('??####')) . date('ymd');
        
        // Verificar que no exista ya una reserva con este código
        while (Reserva::where('codigo_reserva', $codigo)->exists()) {
            $codigo = strtoupper($this->faker->bothify('??####')) . date('ymd');
        }
        
        return $codigo;
    }

    /**
     * Reserva en carrito
     */
    public function enCarrito(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => Reserva::ESTADO_EN_CARRITO,
            'codigo_reserva' => null, // Las reservas en carrito no tienen código aún
        ]);
    }

    /**
     * Reserva pendiente
     */
    public function pendiente(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => Reserva::ESTADO_PENDIENTE,
        ]);
    }

    /**
     * Reserva confirmada
     */
    public function confirmada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => Reserva::ESTADO_CONFIRMADA,
        ]);
    }

    /**
     * Reserva cancelada
     */
    public function cancelada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => Reserva::ESTADO_CANCELADA,
        ]);
    }

    /**
     * Reserva completada
     */
    public function completada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => Reserva::ESTADO_COMPLETADA,
        ]);
    }

    /**
     * Reserva con usuario específico
     */
    public function conUsuario(int $usuarioId): static
    {
        return $this->state(fn (array $attributes) => [
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Reserva con notas
     */
    public function conNotas(): static
    {
        return $this->state(fn (array $attributes) => [
            'notas' => $this->faker->paragraph(3),
        ]);
    }

    /**
     * Reserva turística típica
     */
    public function turistica(): static
    {
        return $this->state(fn (array $attributes) => [
            'notas' => $this->faker->randomElement([
                'Viaje familiar al Lago Titicaca, interesados en actividades culturales',
                'Luna de miel, buscan experiencias románticas y tranquilas',
                'Grupo de amigos aventureros, les gusta el turismo activo',
                'Viaje de estudios, enfoque en cultura y tradiciones locales',
                'Turismo gastronómico, especial interés en comida tradicional'
            ]),
        ]);
    }

    /**
     * Reserva con código específico
     */
    public function conCodigo(string $codigo): static
    {
        return $this->state(fn (array $attributes) => [
            'codigo_reserva' => $codigo,
        ]);
    }
}