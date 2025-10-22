<?php

namespace Tests\Unit\Models;

use App\Models\Emprendedor;
use App\Models\Asociacion;
use App\Models\Municipalidad;
use App\Models\User;
use App\Models\Servicio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmprendedorTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected Asociacion $asociacion;

    protected function setUp(): void
    {
        parent::setUp();

        $municipalidad = Municipalidad::factory()->create();
        $this->asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $municipalidad->id
        ]);
    }

    #[Test]
    public function puede_crear_emprendedor_con_datos_validos()
    {
        $data = [
            'nombre' => 'Restaurante El Lago',
            'tipo_servicio' => 'Restaurante',
            'descripcion' => 'Especialidad en trucha fresca del lago',
            'ubicacion' => 'Av. Principal 123, Capachica',
            'telefono' => '987654321',
            'email' => 'contacto@ellago.com',
            'categoria' => 'Gastronomía',
            'precio_rango' => 'S/ 50 - S/ 100',
            'metodos_pago' => ['efectivo', 'tarjeta_credito'],
            'capacidad_aforo' => 80,
            'numero_personas_atiende' => 25,
            'horario_atencion' => 'Lunes a Domingo: 11:00 AM - 10:00 PM',
            'idiomas_hablados' => ['español', 'inglés'],
            'certificaciones' => ['DIRCETUR', 'Certificado sanitario'],
            'opciones_acceso' => ['vehiculo_propio', 'transporte_publico'],
            'facilidades_discapacidad' => true,
            'asociacion_id' => $this->asociacion->id,
            'estado' => true
        ];

        $emprendedor = Emprendedor::create($data);

        $this->assertInstanceOf(Emprendedor::class, $emprendedor);
        $this->assertEquals($data['nombre'], $emprendedor->nombre);
        $this->assertEquals($data['categoria'], $emprendedor->categoria);
        $this->assertTrue($emprendedor->estado);
        $this->assertTrue($emprendedor->facilidades_discapacidad);
        $this->assertDatabaseHas('emprendedores', [
            'nombre' => $data['nombre'],
            'email' => $data['email']
        ]);
    }

    #[Test]
    public function fillable_permite_campos_correctos()
    {
        $emprendedor = new Emprendedor();
        $data = [
            'nombre' => 'Test Emprendedor',
            'categoria' => 'Turismo',
            'estado' => true,
            'asociacion_id' => $this->asociacion->id,
            'campo_no_permitido' => 'no debe ser asignado'
        ];

        $emprendedor->fill($data);

        $this->assertEquals('Test Emprendedor', $emprendedor->nombre);
        $this->assertEquals('Turismo', $emprendedor->categoria);
        $this->assertTrue($emprendedor->estado);
        $this->assertFalse(property_exists($emprendedor, 'campo_no_permitido'));
    }

    #[Test]
    public function casts_convierte_tipos_correctamente()
    {
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id,
            'metodos_pago' => ['efectivo', 'tarjeta_credito'],
            'imagenes' => ['imagen1.jpg', 'imagen2.jpg'],
            'certificaciones' => ['CALTUR', 'DIRCETUR'],
            'idiomas_hablados' => ['español', 'inglés'],
            'opciones_acceso' => ['vehiculo_propio'],
            'facilidades_discapacidad' => '1',
            'estado' => '1'
        ]);

        $this->assertIsArray($emprendedor->metodos_pago);
        $this->assertIsArray($emprendedor->imagenes);
        $this->assertIsArray($emprendedor->certificaciones);
        $this->assertIsArray($emprendedor->idiomas_hablados);
        $this->assertIsArray($emprendedor->opciones_acceso);
        $this->assertIsBool($emprendedor->facilidades_discapacidad);
        $this->assertTrue($emprendedor->facilidades_discapacidad);
        $this->assertIsBool($emprendedor->estado);
        $this->assertTrue($emprendedor->estado);
    }

    #[Test]
    public function relacion_asociacion_funciona_correctamente()
    {
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        $asociacionRelacionada = $emprendedor->asociacion;

        $this->assertInstanceOf(Asociacion::class, $asociacionRelacionada);
        $this->assertEquals($this->asociacion->id, $asociacionRelacionada->id);
    }

    #[Test]
    public function relacion_administradores_funciona_correctamente()
    {
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        $usuarios = User::factory()->count(3)->create();

        $emprendedor->administradores()->attach([
            $usuarios[0]->id => ['es_principal' => true, 'rol' => 'administrador'],
            $usuarios[1]->id => ['es_principal' => false, 'rol' => 'administrador'],
            $usuarios[2]->id => ['es_principal' => false, 'rol' => 'colaborador']
        ]);

        $administradoresRelacionados = $emprendedor->administradores;

        $this->assertCount(3, $administradoresRelacionados);
    }

    #[Test]
    public function relacion_sliders_principales_filtra_correctamente()
    {
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        $relation = $emprendedor->slidersPrincipales();
        $sql = $relation->toSql();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $relation);
        $this->assertStringContainsString('`es_principal` = ?', $sql);
    }



    #[Test]
    public function created_at_y_updated_at_se_establecen_automaticamente()
    {
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        $this->assertNotNull($emprendedor->created_at);
        $this->assertNotNull($emprendedor->updated_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $emprendedor->created_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $emprendedor->updated_at);
    }

    #[Test]
    public function estado_y_facilidades_discapacidad_son_booleanos()
    {
        $emprendedorActivo = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id,
            'estado' => true,
            'facilidades_discapacidad' => false
        ]);

        $this->assertIsBool($emprendedorActivo->estado);
        $this->assertIsBool($emprendedorActivo->facilidades_discapacidad);
    }

    #[Test]
    public function puede_convertir_a_array_y_json()
    {
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        $array = $emprendedor->toArray();
        $json = $emprendedor->toJson();

        $this->assertIsArray($array);
        $this->assertIsString($json);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('nombre', $array);
    }
}
