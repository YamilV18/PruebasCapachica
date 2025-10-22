<?php

namespace Database\Factories;

use App\Models\Municipalidad;
use Illuminate\Database\Eloquent\Factories\Factory;

class MunicipalidadFactory extends Factory
{
    protected $model = Municipalidad::class;

    public function definition(): array
    {
        return [
            'nombre' => $this->faker->company,
            'descripcion' => $this->faker->paragraph,
            'red_facebook' => $this->faker->url,
            'red_instagram' => $this->faker->url,
            'red_youtube' => $this->faker->url,
            'coordenadas_x' => $this->faker->latitude,
            'coordenadas_y' => $this->faker->longitude,
            'frase' => $this->faker->sentence,
            'comunidades' => $this->faker->paragraph,
            'historiafamilias' => $this->faker->text(300),
            'historiacapachica' => $this->faker->text(300),
            'comite' => $this->faker->paragraph,
            'mision' => $this->faker->paragraph,
            'vision' => $this->faker->paragraph,
            'valores' => $this->faker->words(5, true),
            'ordenanzamunicipal' => $this->faker->sentence,
            'alianzas' => $this->faker->sentence,
            'correo' => $this->faker->safeEmail,
            'horariodeatencion' => $this->faker->regexify('Lun a Vie de 08:00 a 17:00'),
        ];
    }
}