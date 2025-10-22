<?php

namespace Database\Factories;

use App\Models\Emprendedor;
use App\Models\Asociacion;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmprendedorFactory extends Factory
{
    protected $model = Emprendedor::class;

    public function definition(): array
    {
        // Categorías de ejemplo
        $categorias = [
            'Turismo',
            'Gastronomía',
            'Artesanías',
            'Hospedaje',
            'Transporte',
            'Entretenimiento',
            'Deportes',
            'Salud y Bienestar'
        ];

        // Tipos de servicio
        $tiposServicio = [
            'Restaurante',
            'Hotel',
            'Guía turístico',
            'Artesanía',
            'Transporte',
            'Entretenimiento',
            'Spa',
            'Tour operator'
        ];

        // Métodos de pago
        $metodosPago = [
            ['efectivo', 'tarjeta_credito'],
            ['efectivo', 'tarjeta_debito'],
            ['efectivo', 'transferencia'],
            ['efectivo', 'tarjeta_credito', 'tarjeta_debito'],
            ['efectivo', 'yape', 'plin'],
            ['efectivo', 'tarjeta_credito', 'yape', 'plin', 'transferencia']
        ];

        // Rangos de precio
        $preciosRango = [
            'S/ 10 - S/ 50',
            'S/ 50 - S/ 100',
            'S/ 100 - S/ 200',
            'S/ 200 - S/ 500',
            'S/ 500 - S/ 1000',
            'S/ 1000 a más'
        ];

        // Horarios de atención
        $horariosAtencion = [
            'Lunes a Viernes: 8:00 AM - 6:00 PM',
            'Lunes a Sábado: 9:00 AM - 8:00 PM',
            'Todos los días: 10:00 AM - 10:00 PM',
            'Martes a Domingo: 8:00 AM - 5:00 PM',
            'Lunes a Domingo: 24 horas'
        ];

        // Idiomas
        $idiomas = [
            ['español'],
            ['español', 'inglés'],
            ['español', 'inglés', 'quechua'],
            ['español', 'inglés', 'francés'],
            ['español', 'quechua']
        ];

        // Certificaciones
        $certificaciones = [
            ['CALTUR'],
            ['DIRCETUR'],
            ['ISO 9001'],
            ['CALTUR', 'DIRCETUR'],
            ['Certificado sanitario'],
            []
        ];

        // Opciones de acceso
        $opcionesAcceso = [
            ['vehiculo_propio'],
            ['transporte_publico'],
            ['vehiculo_propio', 'transporte_publico'],
            ['vehiculo_propio', 'taxi'],
            ['transporte_publico', 'taxi', 'a_pie']
        ];

        // Generar imágenes ficticias
        $imagenes = [];
        $numImagenes = $this->faker->numberBetween(1, 5);
        for ($i = 0; $i < $numImagenes; $i++) {
            $imagenes[] = $this->faker->imageUrl(800, 600, 'business', true, 'emprendimiento');
        }

        return [
            'nombre' => $this->faker->company() . ' ' . $this->faker->randomElement(['SAC', 'EIRL', 'SRL', '']),
            'tipo_servicio' => $this->faker->randomElement($tiposServicio),
            'descripcion' => $this->faker->paragraph(3) . ' ' . $this->faker->sentence(10),
            'ubicacion' => $this->faker->address(),
            'telefono' => $this->faker->randomElement([
                $this->faker->numerify('9########'),
                $this->faker->numerify('(01) ###-####'),
                $this->faker->numerify('+51 9########')
            ]),
            'email' => $this->faker->unique()->companyEmail(),
            'pagina_web' => $this->faker->optional(0.4)->url(),
            'horario_atencion' => $this->faker->randomElement($horariosAtencion),
            'precio_rango' => $this->faker->randomElement($preciosRango),
            'metodos_pago' => $this->faker->randomElement($metodosPago),
            'capacidad_aforo' => $this->faker->numberBetween(10, 200),
            'numero_personas_atiende' => $this->faker->numberBetween(1, 50),
            'comentarios_resenas' => $this->faker->optional(0.7)->paragraph(2),
            'imagenes' => $imagenes,
            'categoria' => $this->faker->randomElement($categorias),
            'certificaciones' => $this->faker->randomElement($certificaciones),
            'idiomas_hablados' => $this->faker->randomElement($idiomas),
            'opciones_acceso' => $this->faker->randomElement($opcionesAcceso),
            'facilidades_discapacidad' => $this->faker->boolean(30), // 30% de probabilidad
            'asociacion_id' => Asociacion::factory(),
            'estado' => $this->faker->boolean(85), // 85% activos
        ];
    }

    /**
     * Emprendedor activo
     */
    public function activo(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => true,
        ]);
    }

    /**
     * Emprendedor inactivo
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => false,
        ]);
    }

    /**
     * Emprendedor de categoría específica
     */
    public function categoria(string $categoria): static
    {
        return $this->state(fn (array $attributes) => [
            'categoria' => $categoria,
        ]);
    }

    /**
     * Emprendedor gastronómico
     */
    public function gastronomico(): static
    {
        return $this->state(fn (array $attributes) => [
            'categoria' => 'Gastronomía',
            'tipo_servicio' => $this->faker->randomElement(['Restaurante', 'Café', 'Bar', 'Pollería', 'Cevichería']),
            'capacidad_aforo' => $this->faker->numberBetween(20, 150),
            'horario_atencion' => 'Lunes a Domingo: 11:00 AM - 10:00 PM',
            'certificaciones' => ['Certificado sanitario', 'DIRCETUR'],
        ]);
    }

    /**
     * Emprendedor de hospedaje
     */
    public function hospedaje(): static
    {
        return $this->state(fn (array $attributes) => [
            'categoria' => 'Hospedaje',
            'tipo_servicio' => $this->faker->randomElement(['Hotel', 'Hostal', 'Casa rural', 'Lodge']),
            'capacidad_aforo' => $this->faker->numberBetween(10, 100),
            'horario_atencion' => 'Recepción 24 horas',
            'certificaciones' => ['CALTUR', 'DIRCETUR'],
            'facilidades_discapacidad' => $this->faker->boolean(60),
        ]);
    }

    /**
     * Emprendedor turístico
     */
    public function turistico(): static
    {
        return $this->state(fn (array $attributes) => [
            'categoria' => 'Turismo',
            'tipo_servicio' => $this->faker->randomElement(['Guía turístico', 'Tour operator', 'Agencia de viajes']),
            'idiomas_hablados' => ['español', 'inglés', 'quechua'],
            'certificaciones' => ['CALTUR', 'DIRCETUR'],
            'opciones_acceso' => ['vehiculo_propio', 'transporte_publico'],
        ]);
    }

    /**
     * Emprendedor con asociación específica
     */
    public function conAsociacion(int $asociacionId): static
    {
        return $this->state(fn (array $attributes) => [
            'asociacion_id' => $asociacionId,
        ]);
    }

    /**
     * Emprendedor con facilidades para discapacidad
     */
    public function accesible(): static
    {
        return $this->state(fn (array $attributes) => [
            'facilidades_discapacidad' => true,
        ]);
    }

    /**
     * Emprendedor premium (con más características)
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'pagina_web' => $this->faker->url(),
            'certificaciones' => ['CALTUR', 'DIRCETUR', 'ISO 9001'],
            'idiomas_hablados' => ['español', 'inglés', 'francés'],
            'metodos_pago' => ['efectivo', 'tarjeta_credito', 'tarjeta_debito', 'yape', 'plin', 'transferencia'],
            'facilidades_discapacidad' => true,
            'precio_rango' => 'S/ 200 - S/ 500',
        ]);
    }
}