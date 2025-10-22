<?php

namespace Tests\Unit\Models;

use App\Models\ReservaServicio;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\Emprendedor;
use App\Models\Asociacion;
use App\Models\Municipalidad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Carbon\Carbon;

class ReservaServicioTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected Reserva $reserva;
    protected Servicio $servicio;
    protected Emprendedor $emprendedor;
    protected User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear estructura básica
        $this->usuario = User::factory()->create();

        $municipalidad = Municipalidad::factory()->create();
        $asociacion = Asociacion::factory()->create(['municipalidad_id' => $municipalidad->id]);
        $this->emprendedor = Emprendedor::factory()->create(['asociacion_id' => $asociacion->id]);
        $this->servicio = Servicio::factory()->create(['emprendedor_id' => $this->emprendedor->id]);
        $this->reserva = Reserva::factory()->create(['usuario_id' => $this->usuario->id]);
    }

    #[Test]
    public function puede_crear_reserva_servicio_con_datos_validos()
    {
        // Arrange
        $data = [
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'fecha_inicio' => '2024-08-15',
            'fecha_fin' => '2024-08-17',
            'hora_inicio' => '09:00:00',
            'hora_fin' => '17:00:00',
            'duracion_minutos' => 480,
            'cantidad' => 3,
            'precio' => 250.50,
            'estado' => ReservaServicio::ESTADO_PENDIENTE,
            'notas_cliente' => 'Preferencia por actividades matutinas',
            'notas_emprendedor' => 'Cliente VIP, atención especial'
        ];

        // Act
        $reservaServicio = ReservaServicio::create($data);

        // Assert
        $this->assertInstanceOf(ReservaServicio::class, $reservaServicio);
        $this->assertEquals($data['reserva_id'], $reservaServicio->reserva_id);
        $this->assertEquals($data['servicio_id'], $reservaServicio->servicio_id);
        $this->assertEquals($data['emprendedor_id'], $reservaServicio->emprendedor_id);
        $this->assertEquals($data['fecha_inicio'], $reservaServicio->fecha_inicio->format('Y-m-d'));
        $this->assertEquals($data['precio'], $reservaServicio->precio);
        $this->assertEquals($data['estado'], $reservaServicio->estado);

        $this->assertDatabaseHas('reserva_servicios', [
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'precio' => 250.50,
            'cantidad' => 3
        ]);
    }



    #[Test]
    public function tabla_correcta_es_utilizada()
    {
        // Arrange
        $reservaServicio = new ReservaServicio();

        // Act
        $tabla = $reservaServicio->getTable();

        // Assert
        $this->assertEquals('reserva_servicios', $tabla);
    }



    #[Test]
    public function constantes_de_estado_estan_definidas()
    {
        // Assert
        $this->assertEquals('en_carrito', ReservaServicio::ESTADO_EN_CARRITO);
        $this->assertEquals('pendiente', ReservaServicio::ESTADO_PENDIENTE);
        $this->assertEquals('confirmado', ReservaServicio::ESTADO_CONFIRMADO);
        $this->assertEquals('cancelado', ReservaServicio::ESTADO_CANCELADO);
        $this->assertEquals('completado', ReservaServicio::ESTADO_COMPLETADO);
    }

    #[Test]
    public function relacion_reserva_funciona_correctamente()
    {
        // Arrange
        $reservaServicio = ReservaServicio::factory()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $reservaRelacionada = $reservaServicio->reserva;

        // Assert
        $this->assertInstanceOf(Reserva::class, $reservaRelacionada);
        $this->assertEquals($this->reserva->id, $reservaRelacionada->id);
        $this->assertEquals($this->reserva->codigo_reserva, $reservaRelacionada->codigo_reserva);
    }

    #[Test]
    public function relacion_servicio_funciona_correctamente()
    {
        // Arrange
        $reservaServicio = ReservaServicio::factory()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $servicioRelacionado = $reservaServicio->servicio;

        // Assert
        $this->assertInstanceOf(Servicio::class, $servicioRelacionado);
        $this->assertEquals($this->servicio->id, $servicioRelacionado->id);
        $this->assertEquals($this->servicio->nombre, $servicioRelacionado->nombre);
    }

    #[Test]
    public function relacion_emprendedor_funciona_correctamente()
    {
        // Arrange
        $reservaServicio = ReservaServicio::factory()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $emprendedorRelacionado = $reservaServicio->emprendedor;

        // Assert
        $this->assertInstanceOf(Emprendedor::class, $emprendedorRelacionado);
        $this->assertEquals($this->emprendedor->id, $emprendedorRelacionado->id);
        $this->assertEquals($this->emprendedor->nombre, $emprendedorRelacionado->nombre);
    }

    #[Test]
    public function atributo_subtotal_calcula_correctamente()
    {
        // Arrange
        $reservaServicio = ReservaServicio::factory()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'precio' => 75.50,
            'cantidad' => 3
        ]);

        // Act
        $subtotal = $reservaServicio->subtotal;

        // Assert
        $this->assertEquals(226.50, $subtotal); // 75.50 * 3
    }

    #[Test]
    public function atributo_subtotal_maneja_valores_nulos()
    {
        // Arrange
        $reservaServicio = ReservaServicio::factory()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'precio' => null,
            'cantidad' => 2
        ]);

        // Act
        $subtotal = $reservaServicio->subtotal;

        // Assert
        $this->assertEquals(0.0, $subtotal); // 0 * 2
    }



    #[Test]
    public function puede_verificar_solapamiento_basico()
    {
        // Arrange
        $servicioExistente = ReservaServicio::factory()->confirmado()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'fecha_inicio' => '2024-08-15',
            'fecha_fin' => '2024-08-15',
            'hora_inicio' => '09:00:00',
            'hora_fin' => '17:00:00'
        ]);

        // Act - Verificar solapamiento con mismo horario
        $haySolapamiento = ReservaServicio::verificarSolapamiento(
            $this->servicio->id,
            '2024-08-15',
            '2024-08-15',
            '10:00:00',
            '16:00:00'
        );

        // Assert
        $this->assertTrue($haySolapamiento);
    }

    #[Test]
    public function no_verifica_solapamiento_con_servicios_en_carrito()
    {
        // Arrange
        $servicioEnCarrito = ReservaServicio::factory()->enCarrito()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'fecha_inicio' => '2024-08-15',
            'fecha_fin' => '2024-08-15',
            'hora_inicio' => '09:00:00',
            'hora_fin' => '17:00:00'
        ]);

        // Act - Verificar solapamiento con mismo horario
        $haySolapamiento = ReservaServicio::verificarSolapamiento(
            $this->servicio->id,
            '2024-08-15',
            '2024-08-15',
            '10:00:00',
            '16:00:00'
        );

        // Assert
        $this->assertFalse($haySolapamiento); // No debe considerar servicios en carrito
    }

    #[Test]
    public function puede_verificar_solapamiento_con_rangos_de_fechas()
    {
        // Arrange
        $servicioExistente = ReservaServicio::factory()->confirmado()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'fecha_inicio' => '2024-08-15',
            'fecha_fin' => '2024-08-20',
            'hora_inicio' => '09:00:00',
            'hora_fin' => '17:00:00'
        ]);

        // Act - Verificar solapamiento con rango que se cruza
        $haySolapamiento = ReservaServicio::verificarSolapamiento(
            $this->servicio->id,
            '2024-08-18',
            '2024-08-25',
            '10:00:00',
            '16:00:00'
        );

        // Assert
        $this->assertTrue($haySolapamiento);
    }

    #[Test]
    public function no_detecta_solapamiento_con_fechas_diferentes()
    {
        // Arrange
        $servicioExistente = ReservaServicio::factory()->confirmado()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'fecha_inicio' => '2024-08-15',
            'fecha_fin' => '2024-08-15',
            'hora_inicio' => '09:00:00',
            'hora_fin' => '17:00:00'
        ]);

        // Act - Verificar con fecha diferente
        $haySolapamiento = ReservaServicio::verificarSolapamiento(
            $this->servicio->id,
            '2024-08-20',
            '2024-08-20',
            '10:00:00',
            '16:00:00'
        );

        // Assert
        $this->assertFalse($haySolapamiento);
    }

    #[Test]
    public function no_detecta_solapamiento_con_horarios_diferentes()
    {
        // Arrange
        $servicioExistente = ReservaServicio::factory()->confirmado()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'fecha_inicio' => '2024-08-15',
            'fecha_fin' => '2024-08-15',
            'hora_inicio' => '09:00:00',
            'hora_fin' => '12:00:00'
        ]);

        // Act - Verificar con horario posterior sin solapamiento
        $haySolapamiento = ReservaServicio::verificarSolapamiento(
            $this->servicio->id,
            '2024-08-15',
            '2024-08-15',
            '13:00:00',
            '17:00:00'
        );

        // Assert
        $this->assertFalse($haySolapamiento);
    }

    #[Test]
    public function puede_excluir_reserva_actual_al_verificar_solapamiento()
    {
        // Arrange
        $servicioExistente = ReservaServicio::factory()->confirmado()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'fecha_inicio' => '2024-08-15',
            'fecha_fin' => '2024-08-15',
            'hora_inicio' => '09:00:00',
            'hora_fin' => '17:00:00'
        ]);

        // Act - Verificar solapamiento excluyendo la reserva actual
        $haySolapamiento = ReservaServicio::verificarSolapamiento(
            $this->servicio->id,
            '2024-08-15',
            '2024-08-15',
            '10:00:00',
            '16:00:00',
            $servicioExistente->id
        );

        // Assert
        $this->assertFalse($haySolapamiento); // No debe detectar solapamiento consigo mismo
    }

    #[Test]
    public function puede_actualizar_campos_individuales()
    {
        // Arrange
        $reservaServicio = ReservaServicio::factory()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'precio' => 100.00,
            'cantidad' => 1,
            'estado' => ReservaServicio::ESTADO_PENDIENTE
        ]);

        // Act
        $reservaServicio->update([
            'precio' => 150.00,
            'cantidad' => 2,
            'estado' => ReservaServicio::ESTADO_CONFIRMADO,
            'notas_emprendedor' => 'Confirmado por el emprendedor'
        ]);

        // Assert
        $this->assertEquals(150.00, $reservaServicio->fresh()->precio);
        $this->assertEquals(2, $reservaServicio->fresh()->cantidad);
        $this->assertEquals(ReservaServicio::ESTADO_CONFIRMADO, $reservaServicio->fresh()->estado);
        $this->assertEquals('Confirmado por el emprendedor', $reservaServicio->fresh()->notas_emprendedor);
    }

    #[Test]
    public function puede_eliminar_reserva_servicio()
    {
        // Arrange
        $reservaServicio = ReservaServicio::factory()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);
        $id = $reservaServicio->id;

        // Act
        $result = $reservaServicio->delete();

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('reserva_servicios', ['id' => $id]);
    }


    #[Test]
    public function created_at_y_updated_at_se_establecen_automaticamente()
    {
        // Arrange & Act
        $reservaServicio = ReservaServicio::factory()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Assert
        $this->assertNotNull($reservaServicio->created_at);
        $this->assertNotNull($reservaServicio->updated_at);
        $this->assertInstanceOf(Carbon::class, $reservaServicio->created_at);
        $this->assertInstanceOf(Carbon::class, $reservaServicio->updated_at);
    }

    #[Test]
    public function puede_convertir_a_array()
    {
        // Arrange
        $reservaServicio = ReservaServicio::factory()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $array = $reservaServicio->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('reserva_id', $array);
        $this->assertArrayHasKey('servicio_id', $array);
        $this->assertArrayHasKey('emprendedor_id', $array);
        $this->assertArrayHasKey('fecha_inicio', $array);
        $this->assertArrayHasKey('precio', $array);
        $this->assertArrayHasKey('cantidad', $array);
        $this->assertArrayHasKey('estado', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    #[Test]
    public function puede_convertir_a_json()
    {
        // Arrange
        $reservaServicio = ReservaServicio::factory()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $json = $reservaServicio->toJson();
        $data = json_decode($json, true);

        // Assert
        $this->assertIsString($json);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('reserva_id', $data);
        $this->assertArrayHasKey('servicio_id', $data);
    }

    #[Test]
    public function puede_cargar_relaciones_eager()
    {
        // Arrange
        $reservaServicio = ReservaServicio::factory()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $reservaServicioConRelaciones = ReservaServicio::with(['reserva', 'servicio', 'emprendedor'])
                                                      ->find($reservaServicio->id);

        // Assert
        $this->assertTrue($reservaServicioConRelaciones->relationLoaded('reserva'));
        $this->assertTrue($reservaServicioConRelaciones->relationLoaded('servicio'));
        $this->assertTrue($reservaServicioConRelaciones->relationLoaded('emprendedor'));
        $this->assertInstanceOf(Reserva::class, $reservaServicioConRelaciones->reserva);
        $this->assertInstanceOf(Servicio::class, $reservaServicioConRelaciones->servicio);
        $this->assertInstanceOf(Emprendedor::class, $reservaServicioConRelaciones->emprendedor);
    }

    #[Test]
    public function puede_filtrar_por_estado()
    {
        // Arrange
        $serviciosPendientes = ReservaServicio::factory()->count(3)->pendiente()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        $serviciosConfirmados = ReservaServicio::factory()->count(2)->confirmado()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $pendientes = ReservaServicio::where('estado', ReservaServicio::ESTADO_PENDIENTE)->get();
        $confirmados = ReservaServicio::where('estado', ReservaServicio::ESTADO_CONFIRMADO)->get();

        // Assert
        $this->assertCount(3, $pendientes);
        $this->assertCount(2, $confirmados);

        foreach ($pendientes as $servicio) {
            $this->assertEquals(ReservaServicio::ESTADO_PENDIENTE, $servicio->estado);
        }

        foreach ($confirmados as $servicio) {
            $this->assertEquals(ReservaServicio::ESTADO_CONFIRMADO, $servicio->estado);
        }
    }

    #[Test]
    public function puede_filtrar_por_fechas()
    {
        // Arrange
        $fecha1 = '2024-08-15';
        $fecha2 = '2024-08-20';

        $serviciosFecha1 = ReservaServicio::factory()->count(2)->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'fecha_inicio' => $fecha1
        ]);

        $serviciosFecha2 = ReservaServicio::factory()->count(3)->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'fecha_inicio' => $fecha2
        ]);

        // Act
        $serviciosDelDia1 = ReservaServicio::whereDate('fecha_inicio', $fecha1)->get();
        $serviciosDelDia2 = ReservaServicio::whereDate('fecha_inicio', $fecha2)->get();

        // Assert
        $this->assertCount(2, $serviciosDelDia1);
        $this->assertCount(3, $serviciosDelDia2);
    }

    #[Test]
    public function puede_ordenar_por_fecha_y_hora()
    {
        // Arrange
        $servicio1 = ReservaServicio::factory()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'fecha_inicio' => '2024-08-15',
            'hora_inicio' => '10:00:00'
        ]);

        $servicio2 = ReservaServicio::factory()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'fecha_inicio' => '2024-08-15',
            'hora_inicio' => '08:00:00'
        ]);

        $servicio3 = ReservaServicio::factory()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'fecha_inicio' => '2024-08-14',
            'hora_inicio' => '15:00:00'
        ]);

        // Act
        $serviciosOrdenados = ReservaServicio::orderBy('fecha_inicio')
                                           ->orderBy('hora_inicio')
                                           ->get();

        // Assert
        $this->assertEquals($servicio3->id, $serviciosOrdenados[0]->id); // 14 ago, 15:00
        $this->assertEquals($servicio2->id, $serviciosOrdenados[1]->id); // 15 ago, 08:00
        $this->assertEquals($servicio1->id, $serviciosOrdenados[2]->id); // 15 ago, 10:00
    }

    #[Test]
    public function primary_key_es_id_por_defecto()
    {
        // Arrange
        $reservaServicio = new ReservaServicio();

        // Act
        $primaryKey = $reservaServicio->getKeyName();

        // Assert
        $this->assertEquals('id', $primaryKey);
    }

    #[Test]
    public function timestamps_estan_habilitados()
    {
        // Arrange
        $reservaServicio = new ReservaServicio();

        // Act
        $timestamps = $reservaServicio->usesTimestamps();

        // Assert
        $this->assertTrue($timestamps);
    }

    #[Test]
    public function puede_manejar_servicios_de_un_dia_completo()
    {
        // Arrange & Act
        $reservaServicio = ReservaServicio::factory()->unDia()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Assert
        $this->assertEquals($reservaServicio->fecha_inicio->format('Y-m-d'), $reservaServicio->fecha_fin->format('Y-m-d'));
        $this->assertEquals('08:00:00', $reservaServicio->hora_inicio);
        $this->assertEquals('17:00:00', $reservaServicio->hora_fin);
        $this->assertEquals(540, $reservaServicio->duracion_minutos); // 9 horas
    }

    #[Test]
    public function puede_manejar_servicios_de_multiples_dias()
    {
        // Arrange & Act
        $reservaServicio = ReservaServicio::factory()->multipleDias()->create([
            'reserva_id' => $this->reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Assert
        $this->assertNotEquals($reservaServicio->fecha_inicio->format('Y-m-d'), $reservaServicio->fecha_fin->format('Y-m-d'));
        $this->assertGreaterThan($reservaServicio->fecha_inicio, $reservaServicio->fecha_fin);
        $this->assertGreaterThanOrEqual(240, $reservaServicio->duracion_minutos); // Mínimo 4 horas
    }
}
