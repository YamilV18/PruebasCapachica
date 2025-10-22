<?php

namespace Tests\Unit\Models;

use App\Models\Asociacion;
use App\Models\Emprendedor;
use App\Models\Municipalidad;
use App\Models\Plan;
use App\Models\PlanDia;
use App\Models\PlanInscripcion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlanTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $usuario;
    protected Emprendedor $emprendedor;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear estructura básica
        $this->usuario = User::factory()->create();

        $municipalidad = Municipalidad::factory()->create();
        $asociacion = Asociacion::factory()->create(['municipalidad_id' => $municipalidad->id]);
        $this->emprendedor = Emprendedor::factory()->create(['asociacion_id' => $asociacion->id]);
    }

    #[Test]
    public function puede_crear_plan_con_datos_validos()
    {
        // Arrange
        $data = [
            'nombre' => 'Aventura Completa en el Titicaca',
            'descripcion' => 'Experiencia única de 5 días explorando el lago navegable más alto del mundo',
            'que_incluye' => 'Transporte, hospedaje, comidas, guía especializado, actividades culturales',
            'capacidad' => 15,
            'duracion_dias' => 5,
            'es_publico' => true,
            'estado' => Plan::ESTADO_ACTIVO,
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id,
            'precio_total' => 850.00,
            'dificultad' => Plan::DIFICULTAD_MODERADO,
            'requerimientos' => 'Condición física básica, documentos de identidad',
            'que_llevar' => 'Ropa abrigada, protector solar, zapatos cómodos',
            'imagen_principal' => 'plans/plan123.jpg',
            'imagenes_galeria' => ['plans/gallery/img1.jpg', 'plans/gallery/img2.jpg']
        ];

        // Act
        $plan = Plan::create($data);

        // Assert
        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertEquals($data['nombre'], $plan->nombre);
        $this->assertEquals($data['duracion_dias'], $plan->duracion_dias);
        $this->assertEquals($data['precio_total'], $plan->precio_total);
        $this->assertTrue($plan->es_publico);
        $this->assertEquals(Plan::ESTADO_ACTIVO, $plan->estado);

        $this->assertDatabaseHas('plans', [
            'nombre' => $data['nombre'],
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);
    }

    #[Test]
    public function fillable_permite_campos_correctos()
    {
        // Arrange
        $plan = new Plan();
        $data = [
            'nombre' => 'Plan Test',
            'descripcion' => 'Descripción del plan',
            'capacidad' => 20,
            'duracion_dias' => 3,
            'es_publico' => true,
            'estado' => Plan::ESTADO_ACTIVO,
            'precio_total' => 500.00,
            'dificultad' => Plan::DIFICULTAD_FACIL,
            'campo_no_permitido' => 'no debe ser asignado'
        ];

        // Act
        $plan->fill($data);

        // Assert
        $this->assertEquals('Plan Test', $plan->nombre);
        $this->assertEquals(20, $plan->capacidad);
        $this->assertEquals(3, $plan->duracion_dias);
        $this->assertTrue($plan->es_publico);
        $this->assertEquals(500.00, $plan->precio_total);
        $this->assertNull($plan->campo_no_permitido);
    }



    #[Test]
    public function constantes_de_estado_estan_definidas()
    {
        $this->assertEquals('activo', Plan::ESTADO_ACTIVO);
        $this->assertEquals('inactivo', Plan::ESTADO_INACTIVO);
        $this->assertEquals('borrador', Plan::ESTADO_BORRADOR);
    }

    #[Test]
    public function constantes_de_dificultad_estan_definidas()
    {
        $this->assertEquals('facil', Plan::DIFICULTAD_FACIL);
        $this->assertEquals('moderado', Plan::DIFICULTAD_MODERADO);
        $this->assertEquals('dificil', Plan::DIFICULTAD_DIFICIL);
    }

    #[Test]
    public function relacion_creado_por_funciona_correctamente()
    {
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => Plan::ESTADO_ACTIVO,
        ]);
        $this->assertInstanceOf(User::class, $plan->creadoPor);
        $this->assertEquals($this->usuario->id, $plan->creadoPor->id);
    }

    #[Test]
    public function relacion_emprendedor_legacy_funciona_correctamente()
    {
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => Plan::ESTADO_ACTIVO,
        ]);
        $this->assertInstanceOf(Emprendedor::class, $plan->emprendedor);
        $this->assertEquals($this->emprendedor->id, $plan->emprendedor->id);
    }

    #[Test]
    public function relacion_dias_funciona_correctamente()
    {
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id,
            'duracion_dias' => 3,
            'estado' => Plan::ESTADO_ACTIVO,
        ]);

        PlanDia::factory()->count(3)->state(new Sequence(
            ['numero_dia' => 1],
            ['numero_dia' => 2],
            ['numero_dia' => 3],
        ))->create(['plan_id' => $plan->id]);

        $diasRelacionados = $plan->dias;

        $this->assertCount(3, $diasRelacionados);
        $this->assertEquals([1, 2, 3], $diasRelacionados->pluck('numero_dia')->toArray());
    }

    #[Test]
    public function puede_verificar_cupos_disponibles()
    {
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id,
            'capacidad' => 20,
            'estado' => Plan::ESTADO_ACTIVO,
        ]);

        $this->assertTrue($plan->tieneCuposDisponibles());
        $this->assertEquals(20, $plan->cupos_disponibles);

        PlanInscripcion::factory()->create(['plan_id' => $plan->id, 'estado' => 'confirmada', 'numero_participantes' => 8]);
        PlanInscripcion::factory()->create(['plan_id' => $plan->id, 'estado' => 'confirmada', 'numero_participantes' => 5]);

        $this->assertTrue($plan->fresh()->tieneCuposDisponibles());
        $this->assertEquals(7, $plan->fresh()->cupos_disponibles);
    }

    #[Test]
    public function no_tiene_cupos_cuando_esta_lleno()
    {
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id,
            'capacidad' => 10,
            'estado' => Plan::ESTADO_ACTIVO,
        ]);
        PlanInscripcion::factory()->create(['plan_id' => $plan->id, 'estado' => 'confirmada', 'numero_participantes' => 10]);
        $this->assertFalse($plan->fresh()->tieneCuposDisponibles());
        $this->assertEquals(0, $plan->fresh()->cupos_disponibles);
    }

    #[Test]
    public function inscripciones_pendientes_no_afectan_cupos_disponibles()
    {
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id,
            'capacidad' => 15,
            'estado' => Plan::ESTADO_ACTIVO,
        ]);
        PlanInscripcion::factory()->create(['plan_id' => $plan->id, 'estado' => 'pendiente', 'numero_participantes' => 10]);
        $this->assertTrue($plan->fresh()->tieneCuposDisponibles());
        $this->assertEquals(15, $plan->fresh()->cupos_disponibles);
    }

    #[Test]
    public function puede_obtener_imagen_principal_url()
    {
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id,
            'imagen_principal' => 'plans/test-image.jpg',
            'estado' => Plan::ESTADO_ACTIVO,
        ]);
        $this->assertStringContainsString('plans/test-image.jpg', $plan->imagen_principal_url);
    }

    #[Test]
    public function retorna_null_si_no_hay_imagen_principal()
    {
        $plan = Plan::factory()->create(['imagen_principal' => null, 'estado' => Plan::ESTADO_ACTIVO]);
        $this->assertNull($plan->imagen_principal_url);
    }

    #[Test]
    public function puede_obtener_imagenes_galeria_urls()
    {
        $imagenes = ['plans/gallery/img1.jpg', 'plans/gallery/img2.jpg'];
        $plan = Plan::factory()->create([
            'imagenes_galeria' => $imagenes,
            'estado' => Plan::ESTADO_ACTIVO,
        ]);
        $urls = $plan->imagenes_galeria_urls;
        $this->assertIsArray($urls);
        $this->assertCount(2, $urls);
    }

    #[Test]
    public function retorna_array_vacio_si_no_hay_imagenes_galeria()
    {
        $plan = Plan::factory()->create([
            'imagenes_galeria' => null,
            'estado' => Plan::ESTADO_ACTIVO,
        ]);
        $this->assertIsArray($plan->imagenes_galeria_urls);
        $this->assertEmpty($plan->imagenes_galeria_urls);
    }

    #[Test]
    public function scope_activos_filtra_correctamente()
    {
        $planActivo = Plan::factory()->activo()->create();
        $planInactivo = Plan::factory()->inactivo()->create();
        $planesActivos = Plan::activos()->get();
        $this->assertTrue($planesActivos->contains($planActivo));
        $this->assertFalse($planesActivos->contains($planInactivo));
    }

    #[Test]
    public function scope_publicos_filtra_correctamente()
    {
        $planPublico = Plan::factory()->publico()->create(['estado' => Plan::ESTADO_ACTIVO]);
        $planPrivado = Plan::factory()->privado()->create(['estado' => Plan::ESTADO_ACTIVO]);
        $planesPublicos = Plan::publicos()->get();
        $this->assertTrue($planesPublicos->contains($planPublico));
        $this->assertFalse($planesPublicos->contains($planPrivado));
    }

    #[Test]
    public function scope_por_dificultad_filtra_correctamente()
    {
        $planFacil = Plan::factory()->facil()->create(['estado' => Plan::ESTADO_ACTIVO]);
        $planDificil = Plan::factory()->dificil()->create(['estado' => Plan::ESTADO_ACTIVO]);
        $planesFaciles = Plan::porDificultad(Plan::DIFICULTAD_FACIL)->get();
        $this->assertTrue($planesFaciles->contains($planFacil));
        $this->assertFalse($planesFaciles->contains($planDificil));
    }




    #[Test]
    public function puede_actualizar_campos_individuales()
    {
        $plan = Plan::factory()->create(['nombre' => 'Nombre Original', 'precio_total' => 500.00, 'estado' => Plan::ESTADO_ACTIVO]);
        $plan->update(['nombre' => 'Nombre Actualizado', 'precio_total' => 750.00, 'estado' => Plan::ESTADO_INACTIVO]);
        $this->assertEquals('Nombre Actualizado', $plan->fresh()->nombre);
        $this->assertEquals(750.00, $plan->fresh()->precio_total);
        $this->assertEquals(Plan::ESTADO_INACTIVO, $plan->fresh()->estado);
    }

    #[Test]
    public function puede_eliminar_plan()
    {
        $plan = Plan::factory()->create(['estado' => Plan::ESTADO_ACTIVO]);
        $id = $plan->id;
        $this->assertTrue($plan->delete());
        $this->assertDatabaseMissing('plans', ['id' => $id]);
    }

    #[Test]
    public function created_at_y_updated_at_se_establecen_automaticamente()
    {
        $plan = Plan::factory()->create(['estado' => Plan::ESTADO_ACTIVO]);
        $this->assertNotNull($plan->created_at);
        $this->assertNotNull($plan->updated_at);
        $this->assertInstanceOf(Carbon::class, $plan->created_at);
    }

    #[Test]
    public function puede_convertir_a_array()
    {
        $plan = Plan::factory()->create(['estado' => Plan::ESTADO_ACTIVO]);
        $array = $plan->toArray();
        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('nombre', $array);
        $this->assertArrayHasKey('cupos_disponibles', $array);
        $this->assertArrayHasKey('imagen_principal_url', $array);
    }

    #[Test]
    public function maneja_valores_nulos_correctamente()
    {
        $plan = Plan::factory()->create([
            'estado' => Plan::ESTADO_ACTIVO,
            'descripcion' => null,
            'requerimientos' => null,
            'que_llevar' => null,
            'imagen_principal' => null,
            'imagenes_galeria' => null
        ]);
        $this->assertNull($plan->descripcion);
        $this->assertNull($plan->imagen_principal_url);
        $this->assertEquals([], $plan->imagenes_galeria_urls);
    }

    #[Test]
    public function primary_key_es_id_por_defecto()
    {
        $this->assertEquals('id', (new Plan())->getKeyName());
    }

    #[Test]
    public function timestamps_estan_habilitados()
    {
        $this->assertTrue((new Plan())->usesTimestamps());
    }
}
