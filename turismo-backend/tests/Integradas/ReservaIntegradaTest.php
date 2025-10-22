<?php

namespace Tests\Integradas;

use App\Models\Reserva;
use App\Models\ReservaServicio;
use App\Models\Servicio;
use App\Models\Emprendedor;
use App\Models\Asociacion;
use App\Models\Municipalidad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Carbon\Carbon;

class ReservaIntegradaTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @var \App\Models\User */
    protected $user;

    /** @var \App\Models\User */
    protected $admin;

    /** @var \App\Models\Emprendedor */
    protected $emprendedor;

    /** @var \App\Models\Servicio */
    protected $servicio;

    /** @var \App\Models\Asociacion */
    protected $asociacion;

    protected function setUp(): void
    {
        parent::setUp();

        // Permisos y roles
        Permission::create(['name' => 'ver reservas']);
        Permission::create(['name' => 'crear reservas']);
        Permission::create(['name' => 'editar reservas']);
        Permission::create(['name' => 'eliminar reservas']);

        $adminRole = Role::create(['name' => 'admin']);
        $userRole  = Role::create(['name' => 'user']);

        $adminRole->givePermissionTo(['ver reservas', 'crear reservas', 'editar reservas', 'eliminar reservas']);
        $userRole->givePermissionTo(['ver reservas', 'crear reservas']);

        // Usuarios
        /** @var \App\Models\User $user */
        $this->user = User::factory()->create();
        $this->user->assignRole('user');

        /** @var \App\Models\User $admin */
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        // Estructura base
        /** @var \App\Models\Municipalidad $municipalidad */
        $municipalidad     = Municipalidad::factory()->create();
        /** @var \App\Models\Asociacion $asociacion */
        $this->asociacion  = Asociacion::factory()->create(['municipalidad_id' => $municipalidad->id]);
        /** @var \App\Models\Emprendedor $emprendedor */
        $this->emprendedor = Emprendedor::factory()->create(['asociacion_id' => $this->asociacion->id]);
        /** @var \App\Models\Servicio $servicio */
        $this->servicio    = Servicio::factory()->create(['emprendedor_id' => $this->emprendedor->id]);
    }

    /** Helper: extrae ítems sin importar si la respuesta es paginada o plana */
    private function itemsFrom(TestResponse $response): array
    {
        $payload = $response->json();
        return data_get($payload, 'data.data', data_get($payload, 'data', [])) ?? [];
    }

    // ========== TESTS DE MODELO (Unit/Models) ==========

    #[Test]
    public function modelo_puede_crear_reserva_con_datos_validos()
    {
        // Arrange
        $data = [
            'usuario_id' => $this->user->id,
            'codigo_reserva' => 'ABC123240801',
            'estado' => Reserva::ESTADO_PENDIENTE,
            'notas' => 'Reserva para viaje familiar al Lago Titicaca'
        ];

        // Act
        $reserva = Reserva::create($data);

        // Assert
        $this->assertInstanceOf(Reserva::class, $reserva);
        $this->assertEquals($data['usuario_id'], $reserva->usuario_id);
        $this->assertEquals($data['codigo_reserva'], $reserva->codigo_reserva);
        $this->assertEquals($data['estado'], $reserva->estado);
        $this->assertEquals($data['notas'], $reserva->notas);
        
        $this->assertDatabaseHas('reservas', [
            'usuario_id' => $this->user->id,
            'codigo_reserva' => $data['codigo_reserva'],
            'estado' => $data['estado']
        ]);
    }

    #[Test]
    public function modelo_fillable_permite_campos_correctos()
    {
        // Arrange
        $reserva = new Reserva();
        $data = [
            'usuario_id' => $this->user->id,
            'codigo_reserva' => 'TEST123',
            'estado' => Reserva::ESTADO_CONFIRMADA,
            'notas' => 'Notas de prueba',
            'campo_no_permitido' => 'no debe ser asignado'
        ];

        // Act
        $reserva->fill($data);

        // Assert
        $this->assertEquals($this->user->id, $reserva->usuario_id);
        $this->assertEquals('TEST123', $reserva->codigo_reserva);
        $this->assertEquals(Reserva::ESTADO_CONFIRMADA, $reserva->estado);
        $this->assertEquals('Notas de prueba', $reserva->notas);
        $this->assertNull($reserva->campo_no_permitido);
    }

    #[Test]
    public function modelo_constantes_de_estado_estan_definidas()
    {
        // Assert
        $this->assertEquals('en_carrito', Reserva::ESTADO_EN_CARRITO);
        $this->assertEquals('pendiente', Reserva::ESTADO_PENDIENTE);
        $this->assertEquals('confirmada', Reserva::ESTADO_CONFIRMADA);
        $this->assertEquals('cancelada', Reserva::ESTADO_CANCELADA);
        $this->assertEquals('completada', Reserva::ESTADO_COMPLETADA);
    }

    #[Test]
    public function modelo_relacion_usuario_funciona_correctamente()
    {
        // Arrange
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);

        // Act
        $usuarioRelacionado = $reserva->usuario;

        // Assert
        $this->assertInstanceOf(User::class, $usuarioRelacionado);
        $this->assertEquals($this->user->id, $usuarioRelacionado->id);
        $this->assertEquals($this->user->name, $usuarioRelacionado->name);
        $this->assertEquals($this->user->email, $usuarioRelacionado->email);
    }

    #[Test]
    public function modelo_relacion_servicios_funciona_correctamente()
    {
        // Arrange
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);
        
        $serviciosReservados = ReservaServicio::factory()->count(3)->create([
            'reserva_id' => $reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $serviciosRelacionados = $reserva->servicios;

        // Assert
        $this->assertCount(3, $serviciosRelacionados);
        foreach ($serviciosReservados as $servicioReservado) {
            $this->assertTrue(
                $serviciosRelacionados->contains('id', $servicioReservado->id)
            );
        }
    }

    #[Test]
    public function modelo_puede_generar_codigo_reserva_unico()
    {
        // Act
        $codigo1 = Reserva::generarCodigoReserva();
        $codigo2 = Reserva::generarCodigoReserva();

        // Assert
        $this->assertIsString($codigo1);
        $this->assertIsString($codigo2);
        $this->assertNotEquals($codigo1, $codigo2);
        
        // Verificar formato: 6 caracteres + fecha (YYMMDD)
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6}\d{6}$/', $codigo1);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6}\d{6}$/', $codigo2);
        
        // Verificar que incluye la fecha actual
        $fechaActual = date('ymd');
        $this->assertStringEndsWith($fechaActual, $codigo1);
        $this->assertStringEndsWith($fechaActual, $codigo2);
    }

    #[Test]
    public function modelo_codigo_reserva_no_se_repite()
    {
        // Arrange - Crear reserva con código específico
        $codigoExistente = 'ABC123' . date('ymd');
        Reserva::factory()->create([
            'usuario_id' => $this->user->id,
            'codigo_reserva' => $codigoExistente
        ]);

        // Act - Generar nuevos códigos
        $codigos = [];
        for ($i = 0; $i < 10; $i++) {
            $codigos[] = Reserva::generarCodigoReserva();
        }

        // Assert
        $this->assertNotContains($codigoExistente, $codigos);
        $this->assertEquals(count($codigos), count(array_unique($codigos))); // Todos únicos
    }

    #[Test]
    public function modelo_atributo_total_servicios_calcula_correctamente()
    {
        // Arrange
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);
        
        ReservaServicio::factory()->count(4)->create([
            'reserva_id' => $reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $totalServicios = $reserva->total_servicios;

        // Assert
        $this->assertEquals(4, $totalServicios);
    }

    #[Test]
    public function modelo_atributo_fecha_inicio_obtiene_primer_servicio()
    {
        // Arrange
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);
        
        ReservaServicio::factory()->create([
            'reserva_id' => $reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'fecha_inicio' => '2024-08-20'
        ]);
        
        ReservaServicio::factory()->create([
            'reserva_id' => $reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'fecha_inicio' => '2024-08-15' // Primera fecha
        ]);

        // Act
        $fechaInicio = $reserva->fecha_inicio;

        // Assert
        $this->assertEquals('2024-08-15 00:00:00', $fechaInicio);
    }

    #[Test]
    public function modelo_atributo_fecha_fin_obtiene_ultimo_servicio()
    {
        // Arrange
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);
        
        ReservaServicio::factory()->create([
            'reserva_id' => $reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'fecha_inicio' => '2024-08-15',
            'fecha_fin' => '2024-08-17'
        ]);
        
        ReservaServicio::factory()->create([
            'reserva_id' => $reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'fecha_inicio' => '2024-08-18',
            'fecha_fin' => '2024-08-25' // Última fecha
        ]);

        // Act
        $fechaFin = $reserva->fecha_fin;

        // Assert
        $this->assertEquals('2024-08-25 00:00:00', $fechaFin);
    }

    #[Test]
    public function modelo_atributo_fecha_fin_usa_fecha_inicio_si_no_hay_fecha_fin()
    {
        // Arrange
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);
        
        ReservaServicio::factory()->create([
            'reserva_id' => $reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'fecha_inicio' => '2024-08-15',
            'fecha_fin' => null
        ]);

        // Act
        $fechaFin = $reserva->fecha_fin;

        // Assert
        $this->assertEquals('2024-08-15 00:00:00', $fechaFin);
    }

    #[Test]
    public function modelo_atributo_precio_total_calcula_correctamente()
    {
        // Arrange
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);
        
        ReservaServicio::factory()->create([
            'reserva_id' => $reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'precio' => 100.00,
            'cantidad' => 2
        ]);
        
        ReservaServicio::factory()->create([
            'reserva_id' => $reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'precio' => 75.50,
            'cantidad' => 1
        ]);

        // Act
        $precioTotal = $reserva->precio_total;

        // Assert
        $this->assertEquals(275.50, $precioTotal); // (100 * 2) + (75.50 * 1)
    }

    #[Test]
    public function modelo_atributo_precio_total_maneja_valores_nulos()
    {
        // Arrange
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);
        
        ReservaServicio::factory()->create([
            'reserva_id' => $reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'precio' => null,
            'cantidad' => 2
        ]);
        
        ReservaServicio::factory()->create([
            'reserva_id' => $reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'precio' => 50.00,
            'cantidad' => 1
        ]);

        // Act
        $precioTotal = $reserva->precio_total;

        // Assert
        $this->assertEquals(50.00, $precioTotal); // (0 * 2) + (50 * 1)
    }

    #[Test]
    public function modelo_puede_actualizar_campos_individuales()
    {
        // Arrange
        $reserva = Reserva::factory()->create([
            'usuario_id' => $this->user->id,
            'estado' => Reserva::ESTADO_PENDIENTE,
            'notas' => 'Notas originales'
        ]);

        // Act
        $reserva->update([
            'estado' => Reserva::ESTADO_CONFIRMADA,
            'notas' => 'Notas actualizadas'
        ]);

        // Assert
        $this->assertEquals(Reserva::ESTADO_CONFIRMADA, $reserva->fresh()->estado);
        $this->assertEquals('Notas actualizadas', $reserva->fresh()->notas);
    }

    #[Test]
    public function modelo_puede_eliminar_reserva()
    {
        // Arrange
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);
        $id = $reserva->id;

        // Act
        $result = $reserva->delete();

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('reservas', ['id' => $id]);
    }

    #[Test]
    public function modelo_maneja_valores_nulos_correctamente()
    {
        // Arrange & Act
        $reserva = Reserva::factory()->create([
            'usuario_id' => $this->user->id,
            'codigo_reserva' => 'RES-TEST-' . uniqid(),
            'notas' => null
        ]);

        // Assert
        $this->assertNotNull($reserva->codigo_reserva);
        $this->assertNull($reserva->notas);
        $this->assertNotNull($reserva->usuario_id);
        $this->assertNotNull($reserva->estado);
    }

    #[Test]
    public function modelo_created_at_y_updated_at_se_establecen_automaticamente()
    {
        // Arrange & Act
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);

        // Assert
        $this->assertNotNull($reserva->created_at);
        $this->assertNotNull($reserva->updated_at);
        $this->assertInstanceOf(Carbon::class, $reserva->created_at);
        $this->assertInstanceOf(Carbon::class, $reserva->updated_at);
    }

    #[Test]
    public function modelo_puede_convertir_a_array()
    {
        // Arrange
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);

        // Act
        $array = $reserva->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('usuario_id', $array);
        $this->assertArrayHasKey('codigo_reserva', $array);
        $this->assertArrayHasKey('estado', $array);
        $this->assertArrayHasKey('notas', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    #[Test]
    public function modelo_puede_convertir_a_json()
    {
        // Arrange
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);

        // Act
        $json = $reserva->toJson();
        $data = json_decode($json, true);

        // Assert
        $this->assertIsString($json);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('usuario_id', $data);
        $this->assertArrayHasKey('estado', $data);
    }

    #[Test]
    public function modelo_tabla_correcta_es_utilizada()
    {
        // Arrange
        $reserva = new Reserva();

        // Act
        $tabla = $reserva->getTable();

        // Assert
        $this->assertEquals('reservas', $tabla);
    }

    #[Test]
    public function modelo_primary_key_es_id_por_defecto()
    {
        // Arrange
        $reserva = new Reserva();

        // Act
        $primaryKey = $reserva->getKeyName();

        // Assert
        $this->assertEquals('id', $primaryKey);
    }

    #[Test]
    public function modelo_timestamps_estan_habilitados()
    {
        // Arrange
        $reserva = new Reserva();

        // Act
        $timestamps = $reserva->usesTimestamps();

        // Assert
        $this->assertTrue($timestamps);
    }

    #[Test]
    public function modelo_puede_cargar_relaciones_eager()
    {
        // Arrange
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);
        ReservaServicio::factory()->count(2)->create([
            'reserva_id' => $reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $reservaConRelaciones = Reserva::with(['usuario', 'servicios'])->find($reserva->id);

        // Assert
        $this->assertTrue($reservaConRelaciones->relationLoaded('usuario'));
        $this->assertTrue($reservaConRelaciones->relationLoaded('servicios'));
        $this->assertInstanceOf(User::class, $reservaConRelaciones->usuario);
        $this->assertCount(2, $reservaConRelaciones->servicios);
    }

    #[Test]
    public function modelo_puede_filtrar_por_estado()
    {
        // Arrange
        $reservaPendiente = Reserva::factory()->pendiente()->create(['usuario_id' => $this->user->id]);
        $reservaConfirmada = Reserva::factory()->confirmada()->create(['usuario_id' => $this->user->id]);
        $reservaCancelada = Reserva::factory()->cancelada()->create(['usuario_id' => $this->user->id]);

        // Act
        $reservasPendientes = Reserva::where('estado', Reserva::ESTADO_PENDIENTE)->get();
        $reservasConfirmadas = Reserva::where('estado', Reserva::ESTADO_CONFIRMADA)->get();

        // Assert
        $this->assertTrue($reservasPendientes->contains('id', $reservaPendiente->id));
        $this->assertFalse($reservasPendientes->contains('id', $reservaConfirmada->id));
        
        $this->assertTrue($reservasConfirmadas->contains('id', $reservaConfirmada->id));
        $this->assertFalse($reservasConfirmadas->contains('id', $reservaPendiente->id));
    }

    #[Test]
    public function modelo_puede_filtrar_por_usuario()
    {
        // Arrange
        $otroUsuario = User::factory()->create();
        
        $reservasUsuario1 = Reserva::factory()->count(3)->create(['usuario_id' => $this->user->id]);
        $reservasUsuario2 = Reserva::factory()->count(2)->create(['usuario_id' => $otroUsuario->id]);

        // Act
        $reservasDelUsuario1 = Reserva::where('usuario_id', $this->user->id)->get();
        $reservasDelUsuario2 = Reserva::where('usuario_id', $otroUsuario->id)->get();

        // Assert
        $this->assertCount(3, $reservasDelUsuario1);
        $this->assertCount(2, $reservasDelUsuario2);
        
        foreach ($reservasDelUsuario1 as $reserva) {
            $this->assertEquals($this->user->id, $reserva->usuario_id);
        }
    }

    #[Test]
    public function modelo_puede_buscar_por_codigo_reserva()
    {
        // Arrange
        $codigoEspecifico = 'TEST123' . date('ymd');
        $reserva = Reserva::factory()->create([
            'usuario_id' => $this->user->id,
            'codigo_reserva' => $codigoEspecifico
        ]);

        // Act
        $reservaEncontrada = Reserva::where('codigo_reserva', $codigoEspecifico)->first();

        // Assert
        $this->assertNotNull($reservaEncontrada);
        $this->assertEquals($reserva->id, $reservaEncontrada->id);
        $this->assertEquals($codigoEspecifico, $reservaEncontrada->codigo_reserva);
    }

    #[Test]
    public function modelo_atributos_calculados_manejan_reserva_sin_servicios()
    {
        // Arrange
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);

        // Act & Assert
        $this->assertEquals(0, $reserva->total_servicios);
        $this->assertNull($reserva->fecha_inicio);
        $this->assertNull($reserva->fecha_fin);
        $this->assertEquals(0.0, $reserva->precio_total);
    }

    #[Test]
    public function modelo_puede_ordenar_por_fecha_creacion()
    {
        // Arrange
        $reserva1 = Reserva::factory()->create([
            'usuario_id' => $this->user->id,
            'created_at' => now()->subDays(2)
        ]);
        
        $reserva2 = Reserva::factory()->create([
            'usuario_id' => $this->user->id,
            'created_at' => now()->subDays(1)
        ]);
        
        $reserva3 = Reserva::factory()->create([
            'usuario_id' => $this->user->id,
            'created_at' => now()
        ]);

        // Act
        $reservasOrdenadas = Reserva::orderBy('created_at', 'desc')->get();

        // Assert
        $this->assertEquals($reserva3->id, $reservasOrdenadas[0]->id); // Más reciente
        $this->assertEquals($reserva2->id, $reservasOrdenadas[1]->id);
        $this->assertEquals($reserva1->id, $reservasOrdenadas[2]->id); // Más antigua
    }

    // ========== TESTS DE CONTROLADOR (Feature/Controllers) ==========

    #[Test]
    public function controlador_admin_puede_obtener_todas_las_reservas()
    {
        Sanctum::actingAs($this->admin, ['*']);

        Reserva::factory()->count(5)->create(['usuario_id' => $this->user->id]);
        Reserva::factory()->count(3)->create(['usuario_id' => $this->admin->id]);

        $response = $this->getJson('/api/reservas');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'id',
                            'codigo_reserva',
                            'estado',
                            'usuario',
                            'servicios',
                        ]
                    ],
                    'total'
                ]
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals(8, $response->json('data.total'));
    }

    #[Test]
    public function controlador_usuario_solo_puede_ver_sus_propias_reservas()
    {
        Sanctum::actingAs($this->user, ['*']);

        Reserva::factory()->count(3)->create(['usuario_id' => $this->user->id]);
        Reserva::factory()->count(2)->create(['usuario_id' => $this->admin->id]);

        $response = $this->getJson('/api/reservas');

        $response->assertStatus(200);

        $items = $this->itemsFrom($response);
        $this->assertCount(3, $items);

        foreach ($items as $reserva) {
            $this->assertEquals($this->user->id, $reserva['usuario_id'] ?? data_get($reserva, 'usuario.id'));
        }
    }

    #[Test]
    public function controlador_puede_obtener_reserva_por_id()
    {
        Sanctum::actingAs($this->user, ['*']);
        /** @var \App\Models\Reserva $reserva */
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);

        $response = $this->getJson("/api/reservas/{$reserva->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $reserva->id,
                    'codigo_reserva' => $reserva->codigo_reserva,
                    'estado' => $reserva->estado,
                    'usuario_id' => $this->user->id,
                ]
            ]);
    }

    #[Test]
    public function controlador_usuario_no_puede_ver_reserva_de_otro_usuario()
    {
        Sanctum::actingAs($this->user, ['*']);
        /** @var \App\Models\Reserva $reservaOtroUsuario */
        $reservaOtroUsuario = Reserva::factory()->create(['usuario_id' => $this->admin->id]);

        $response = $this->getJson("/api/reservas/{$reservaOtroUsuario->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'No tienes permiso para ver esta reserva'
            ]);
    }

    #[Test]
    public function controlador_admin_puede_ver_cualquier_reserva()
    {
        Sanctum::actingAs($this->admin, ['*']);
        /** @var \App\Models\Reserva $reservaUsuario */
        $reservaUsuario = Reserva::factory()->create(['usuario_id' => $this->user->id]);

        $response = $this->getJson("/api/reservas/{$reservaUsuario->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $reservaUsuario->id,
                    'usuario_id' => $this->user->id
                ]
            ]);
    }

    #[Test]
    public function controlador_puede_crear_reserva_con_servicios()
    {
        Sanctum::actingAs($this->user, ['*']);

        $data = [
            'notas' => 'Reserva para viaje familiar',
            'servicios' => [
                [
                    'servicio_id' => $this->servicio->id,
                    'emprendedor_id' => $this->emprendedor->id,
                    'fecha_inicio' => '2024-08-15',
                    'fecha_fin' => '2024-08-15',
                    'hora_inicio' => '09:00:00',
                    'hora_fin' => '17:00:00',
                    'duracion_minutos' => 480,
                    'cantidad' => 2,
                    'precio' => 150.00,
                    'notas_cliente' => 'Preferencia por la mañana'
                ]
            ]
        ];

        $response = $this->postJson('/api/reservas', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Reserva creada exitosamente'
            ]);

        $this->assertDatabaseHas('reservas', [
            'usuario_id' => $this->user->id,
            'notas' => $data['notas'],
        ]);

        $this->assertDatabaseHas('reserva_servicios', [
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'cantidad' => 2,
            'precio' => 150.00,
        ]);
    }

    #[Test]
    public function controlador_valida_datos_requeridos_al_crear_reserva()
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/reservas', []);

        $response->assertStatus(422);
    }

    #[Test]
    public function controlador_puede_eliminar_reserva_propia()
    {
        Sanctum::actingAs($this->user, ['*']);
        /** @var \App\Models\Reserva $reserva */
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);

        $response = $this->deleteJson("/api/reservas/{$reserva->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Reserva eliminada exitosamente'
            ]);

        $this->assertDatabaseMissing('reservas', ['id' => $reserva->id]);
    }

    #[Test]
    public function controlador_no_puede_eliminar_reserva_de_otro_usuario()
    {
        Sanctum::actingAs($this->user, ['*']);
        /** @var \App\Models\Reserva $reservaOtroUsuario */
        $reservaOtroUsuario = Reserva::factory()->create(['usuario_id' => $this->admin->id]);

        $response = $this->deleteJson("/api/reservas/{$reservaOtroUsuario->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'No tienes permiso para eliminar esta reserva'
            ]);
    }

    #[Test]
    public function controlador_puede_cambiar_estado_de_reserva()
    {
        Sanctum::actingAs($this->user, ['*']);
        /** @var \App\Models\Reserva $reserva */
        $reserva = Reserva::factory()->pendiente()->create(['usuario_id' => $this->user->id]);

        $response = $this->putJson("/api/reservas/{$reserva->id}/estado", [
            'estado' => 'confirmada'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Estado de reserva actualizado exitosamente'
            ]);

        $this->assertDatabaseHas('reservas', [
            'id' => $reserva->id,
            'estado' => 'confirmada'
        ]);
    }

    #[Test]
    public function controlador_valida_estados_validos_al_cambiar_estado()
    {
        Sanctum::actingAs($this->user, ['*']);
        /** @var \App\Models\Reserva $reserva */
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);

        $response = $this->putJson("/api/reservas/{$reserva->id}/estado", [
            'estado' => 'estado_invalido'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['estado']);
    }

    #[Test]
    public function controlador_puede_obtener_reservas_por_emprendedor()
    {
        Sanctum::actingAs($this->admin, ['*']);

        /** @var \App\Models\Reserva $reserva */
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);
        ReservaServicio::factory()->create([
            'reserva_id' => $reserva->id,
            'emprendedor_id' => $this->emprendedor->id,
            'servicio_id' => $this->servicio->id,
        ]);

        $response = $this->getJson("/api/reservas/emprendedor/{$this->emprendedor->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $items = $this->itemsFrom($response);
        $this->assertNotEmpty($items);
    }

    #[Test]
    public function controlador_puede_obtener_reservas_por_servicio()
    {
        Sanctum::actingAs($this->admin, ['*']);

        /** @var \App\Models\Reserva $reserva */
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);
        ReservaServicio::factory()->create([
            'reserva_id' => $reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
        ]);

        $response = $this->getJson("/api/reservas/servicio/{$this->servicio->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $items = $this->itemsFrom($response);
        $this->assertNotEmpty($items);
    }

    #[Test]
    public function controlador_puede_crear_reserva_rapida_para_usuario_autenticado()
    {
        Sanctum::actingAs($this->user, ['*']);

        $data = [
            'servicios' => [
                [
                    'servicio_id' => $this->servicio->id,
                    'emprendedor_id' => $this->emprendedor->id,
                    'fecha_inicio' => '2024-08-15',
                    'hora_inicio'  => '09:00:00',
                    'hora_fin'     => '17:00:00',
                    'duracion_minutos' => 480,
                    'cantidad' => 1,
                    'notas_cliente' => 'Reserva rápida'
                ]
            ],
            'notas' => 'Reserva creada rápidamente'
        ];

        $response = $this->postJson('/api/reservas/mis-reservas', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Reserva creada exitosamente'
            ]);

        $this->assertDatabaseHas('reservas', [
            'usuario_id' => $this->user->id,
            'estado' => Reserva::ESTADO_PENDIENTE
        ]);
    }

    #[Test]
    public function controlador_puede_obtener_mis_reservas()
    {
        Sanctum::actingAs($this->user, ['*']);

        Reserva::factory()->count(3)->create(['usuario_id' => $this->user->id]);
        Reserva::factory()->count(2)->create(['usuario_id' => $this->admin->id]);

        $response = $this->getJson('/api/reservas/mis-reservas');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $items = $this->itemsFrom($response);
        $this->assertCount(3, $items);

        foreach ($items as $reserva) {
            $this->assertEquals($this->user->id, $reserva['usuario_id'] ?? data_get($reserva, 'usuario.id'));
        }
    }

    #[Test]
    public function controlador_valida_servicios_requeridos_en_reserva_rapida()
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/reservas/mis-reservas', [
            'notas' => 'Sin servicios'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['servicios']);
    }

    #[Test]
    public function controlador_valida_datos_de_servicio_en_reserva_rapida()
    {
        Sanctum::actingAs($this->user, ['*']);

        $data = [
            'servicios' => [
                [
                    'servicio_id' => 999, // No existe
                    'emprendedor_id' => $this->emprendedor->id,
                    'fecha_inicio' => 'fecha_invalida',
                    'hora_inicio' => '09:00:00',
                    'hora_fin' => '17:00:00',
                    'duracion_minutos' => 480
                ]
            ]
        ];

        $response = $this->postJson('/api/reservas/mis-reservas', $data);

        $response->assertStatus(422);
    }

    #[Test]
    public function controlador_puede_crear_reserva_con_multiples_servicios()
    {
        Sanctum::actingAs($this->user, ['*']);
        /** @var \App\Models\Servicio $servicio2 */
        $servicio2 = Servicio::factory()->create(['emprendedor_id' => $this->emprendedor->id]);

        $data = [
            'notas' => 'Reserva con múltiples servicios',
            'servicios' => [
                [
                    'servicio_id' => $this->servicio->id,
                    'emprendedor_id' => $this->emprendedor->id,
                    'fecha_inicio' => '2024-08-15',
                    'hora_inicio' => '09:00:00',
                    'hora_fin' => '12:00:00',
                    'duracion_minutos' => 180,
                    'cantidad' => 1,
                    'precio' => 75.00
                ],
                [
                    'servicio_id' => $servicio2->id,
                    'emprendedor_id' => $this->emprendedor->id,
                    'fecha_inicio' => '2024-08-15',
                    'hora_inicio' => '14:00:00',
                    'hora_fin' => '17:00:00',
                    'duracion_minutos' => 180,
                    'cantidad' => 2,
                    'precio' => 100.00
                ]
            ]
        ];

        $response = $this->postJson('/api/reservas', $data);

        $response->assertStatus(201);

        /** @var \App\Models\Reserva $reserva */
        $reserva = Reserva::where('usuario_id', $this->user->id)->latest()->first();
        $this->assertCount(2, $reserva->servicios);
    }

    #[Test]
    public function controlador_puede_manejar_reservas_en_diferentes_estados()
    {
        Sanctum::actingAs($this->user, ['*']);

        Reserva::factory()->pendiente()->create(['usuario_id' => $this->user->id]);
        Reserva::factory()->confirmada()->create(['usuario_id' => $this->user->id]);
        Reserva::factory()->cancelada()->create(['usuario_id' => $this->user->id]);

        $response = $this->getJson('/api/reservas');

        $response->assertStatus(200);

        $items = $this->itemsFrom($response);
        $estados = array_map(fn ($r) => $r['estado'], $items);

        $this->assertContains('pendiente', $estados);
        $this->assertContains('confirmada', $estados);
        $this->assertContains('cancelada', $estados);
    }

    #[Test]
    public function controlador_requiere_autenticacion_para_todas_las_operaciones()
    {
        $this->getJson('/api/reservas')->assertStatus(401);
        $this->postJson('/api/reservas', [])->assertStatus(401);
        $this->putJson('/api/reservas/1', [])->assertStatus(401);
        $this->deleteJson('/api/reservas/1')->assertStatus(401);
        $this->getJson('/api/reservas/mis-reservas')->assertStatus(401);
    }

    #[Test]
    public function controlador_puede_crear_reserva_con_servicios_de_diferentes_tipos()
    {
        Sanctum::actingAs($this->user, ['*']);

        $data = [
            'servicios' => [
                [
                    'servicio_id' => $this->servicio->id,
                    'emprendedor_id' => $this->emprendedor->id,
                    'fecha_inicio' => '2024-08-15',
                    'fecha_fin' => '2024-08-17',
                    'hora_inicio' => '09:00:00',
                    'hora_fin' => '17:00:00',
                    'duracion_minutos' => 480,
                    'cantidad' => 3,
                    'notas_cliente' => 'Servicio de aventura familiar'
                ]
            ],
            'notas' => 'Paquete turístico completo'
        ];

        $response = $this->postJson('/api/reservas', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('reserva_servicios', [
            'servicio_id'   => $this->servicio->id,
            'fecha_inicio'  => '2024-08-15',
            'fecha_fin'     => '2024-08-17',
            'cantidad'      => 3,
        ]);
    }
}