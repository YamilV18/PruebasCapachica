<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'phone' => $this->faker->phoneNumber(),
            'active' => true,
            'foto_perfil' => null,
            'google_id' => null,
            'avatar' => $this->faker->optional()->imageUrl(200, 200, 'people'),
            'country' => 'Perú',
            'birth_date' => $this->faker->date('Y-m-d', '-18 years'),
            'address' => $this->faker->address(),
            'gender' => $this->faker->randomElement(['masculino', 'femenino', 'otro', 'prefiero_no_decir']),
            'preferred_language' => $this->faker->randomElement(['es', 'en', 'qu']),
            'last_login' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Usuario no verificado
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Usuario inactivo
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Usuario con Google OAuth
     */
    public function withGoogle(): static
    {
        return $this->state(fn (array $attributes) => [
            'google_id' => $this->faker->uuid(),
            'avatar' => $this->faker->imageUrl(200, 200, 'people'),
            'password' => null,
        ]);
    }

    /**
     * Usuario administrador
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Admin User',
            'email' => 'admin@test.com',
        ]);
    }

    /**
     * Usuario emprendedor
     */
    public function emprendedor(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Emprendedor User',
        ]);
    }

    /**
     * Usuario turista
     */
    public function turista(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Turista User',
        ]);
    }
}