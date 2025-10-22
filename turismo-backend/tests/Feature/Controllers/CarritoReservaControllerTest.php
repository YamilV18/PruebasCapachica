<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\Models\User;
use App\Models\Reserva;
use App\Models\ReservaServicio;
use App\Models\Servicio;
use App\Models\Emprendedor;
use App\Models\Asociacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use App\Repository\ReservaServicioRepository;

class CarritoReservaControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Servicio $servicio;
    protected Emprendedor $emprendedor;

    protected function setUp(): void
    {
        parent::setUp();

        // Stubear disponibilidad para no depender de lógica externa
        $fakeRepo = new class {
            public function verificarDisponibilidad($servicioId, $fechaInicio, $fechaFin, $horaInicio, $horaFin)
            {
                return true;
            }
        };
        $this->app->instance(ReservaServicioRepository::class, $fakeRepo);

        $this->user = User::factory()->create();

        $asociacion = Asociacion::factory()->create();
        $this->emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $asociacion->id,
        ]);

        // OJO: no usar categoria_id (columna no existente en tu tabla)
        $this->servicio = Servicio::factory()->create([
            'emprendedor_id'     => $this->emprendedor->id,
            'precio_referencial' => 100.00,
        ]);

        // ⚠️ IMPORTANTE: NO authenticamos aquí.
        // Autenticamos dentro de cada test que lo requiera con Sanctum::actingAs($this->user);
    }

    #[Test]
    public function user_can_get_empty_cart()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/reservas/carrito')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'usuario_id' => $this->user->id,
                    'estado'     => Reserva::ESTADO_EN_CARRITO,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'usuario_id',
                    'codigo_reserva',
                    'estado',
                ],
            ]);

        $payload = $response->json();
        if (array_key_exists('servicios', $payload['data'] ?? [])) {
            $this->assertIsArray($payload['data']['servicios'], "'servicios' debe ser un array si está presente");
        }
    }

    #[Test]
    public function user_can_get_existing_cart()
    {
        Sanctum::actingAs($this->user);

        $carrito = Reserva::factory()->create([
            'usuario_id' => $this->user->id,
            'estado'     => Reserva::ESTADO_EN_CARRITO,
        ]);

        $this->getJson('/api/reservas/carrito')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id'         => $carrito->id,
                    'usuario_id' => $this->user->id,
                    'estado'     => Reserva::ESTADO_EN_CARRITO,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'usuario_id',
                    'codigo_reserva',
                    'estado',
                ],
            ]);
    }

    #[Test]
    public function user_can_add_service_to_cart()
    {
        Sanctum::actingAs($this->user);

        $serviceData = [
            'servicio_id'      => $this->servicio->id,
            'emprendedor_id'   => $this->emprendedor->id,
            'fecha_inicio'     => '2024-07-01',
            'fecha_fin'        => '2024-07-01',
            'hora_inicio'      => '10:00:00',
            'hora_fin'         => '12:00:00',
            'duracion_minutos' => 120,
            'cantidad'         => 1,
            'notas_cliente'    => 'Nota de prueba',
        ];

        $this->postJson('/api/reservas/carrito/agregar', $serviceData)
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Servicio agregado al carrito exitosamente',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'servicios',
                ],
            ]);

        $this->assertDatabaseHas('reserva_servicios', [
            'servicio_id'    => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'estado'         => ReservaServicio::ESTADO_EN_CARRITO,
            'notas_cliente'  => 'Nota de prueba',
        ]);
    }

    #[Test]
    public function add_to_cart_validates_required_fields()
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/reservas/carrito/agregar', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'servicio_id',
                'emprendedor_id',
                'fecha_inicio',
                'hora_inicio',
                'hora_fin',
                'duracion_minutos',
            ]);
    }

    #[Test]
    public function add_to_cart_validates_service_exists()
    {
        Sanctum::actingAs($this->user);

        $serviceData = [
            'servicio_id'      => 999999,
            'emprendedor_id'   => $this->emprendedor->id,
            'fecha_inicio'     => '2024-07-01',
            'fecha_fin'        => '2024-07-01',
            'hora_inicio'      => '10:00:00',
            'hora_fin'         => '12:00:00',
            'duracion_minutos' => 120,
        ];

        $this->postJson('/api/reservas/carrito/agregar', $serviceData)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['servicio_id']);
    }

    #[Test]
    public function add_to_cart_validates_date_format()
    {
        Sanctum::actingAs($this->user);

        $serviceData = [
            'servicio_id'      => $this->servicio->id,
            'emprendedor_id'   => $this->emprendedor->id,
            'fecha_inicio'     => 'invalid-date',
            'fecha_fin'        => '2024-07-01',
            'hora_inicio'      => '10:00:00',
            'hora_fin'         => '12:00:00',
            'duracion_minutos' => 120,
        ];

        $this->postJson('/api/reservas/carrito/agregar', $serviceData)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_inicio']);
    }

    #[Test]
    public function add_to_cart_validates_time_format()
    {
        Sanctum::actingAs($this->user);

        $serviceData = [
            'servicio_id'      => $this->servicio->id,
            'emprendedor_id'   => $this->emprendedor->id,
            'fecha_inicio'     => '2024-07-01',
            'fecha_fin'        => '2024-07-01',
            'hora_inicio'      => 'invalid-time',
            'hora_fin'         => '12:00:00',
            'duracion_minutos' => 120,
        ];

        $this->postJson('/api/reservas/carrito/agregar', $serviceData)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hora_inicio']);
    }

    #[Test]
    public function user_can_remove_service_from_cart()
    {
        Sanctum::actingAs($this->user);

        $carrito = Reserva::factory()->create([
            'usuario_id' => $this->user->id,
            'estado'     => Reserva::ESTADO_EN_CARRITO,
        ]);

        $servicioCarrito = ReservaServicio::factory()->create([
            'reserva_id'     => $carrito->id,
            'servicio_id'    => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'estado'         => ReservaServicio::ESTADO_EN_CARRITO,
        ]);

        $this->deleteJson("/api/reservas/carrito/servicio/{$servicioCarrito->id}")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Servicio eliminado del carrito exitosamente',
            ]);

        $this->assertDatabaseMissing('reserva_servicios', [
            'id' => $servicioCarrito->id,
        ]);
    }

    #[Test]
    public function user_cannot_remove_nonexistent_service_from_cart()
    {
        Sanctum::actingAs($this->user);

        $this->deleteJson('/api/reservas/carrito/servicio/999999')
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Servicio no encontrado en el carrito',
            ]);
    }

    #[Test]
    public function user_cannot_remove_service_from_other_users_cart()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();

        $carrito = Reserva::factory()->create([
            'usuario_id' => $otherUser->id,
            'estado'     => Reserva::ESTADO_EN_CARRITO,
        ]);

        $servicioCarrito = ReservaServicio::factory()->create([
            'reserva_id'     => $carrito->id,
            'servicio_id'    => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'estado'         => ReservaServicio::ESTADO_EN_CARRITO,
        ]);

        $this->deleteJson("/api/reservas/carrito/servicio/{$servicioCarrito->id}")
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'No tienes permiso para eliminar este servicio',
            ]);
    }

    #[Test]
    public function user_can_confirm_cart()
    {
        Sanctum::actingAs($this->user);

        $carrito = Reserva::factory()->create([
            'usuario_id' => $this->user->id,
            'estado'     => Reserva::ESTADO_EN_CARRITO,
        ]);

        ReservaServicio::factory()->create([
            'reserva_id'     => $carrito->id,
            'servicio_id'    => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'estado'         => ReservaServicio::ESTADO_EN_CARRITO,
        ]);

        $this->postJson('/api/reservas/carrito/confirmar', [
            'notas' => 'Reserva confirmada de prueba',
        ])
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Reserva creada exitosamente',
            ]);

        $this->assertDatabaseHas('reservas', [
            'id'     => $carrito->id,
            'estado' => Reserva::ESTADO_PENDIENTE,
            'notas'  => 'Reserva confirmada de prueba',
        ]);

        $this->assertDatabaseHas('reserva_servicios', [
            'reserva_id' => $carrito->id,
            'estado'     => ReservaServicio::ESTADO_PENDIENTE,
        ]);
    }

    #[Test]
    public function cannot_confirm_empty_cart()
    {
        Sanctum::actingAs($this->user);

        Reserva::factory()->create([
            'usuario_id' => $this->user->id,
            'estado'     => Reserva::ESTADO_EN_CARRITO,
        ]);

        $this->postJson('/api/reservas/carrito/confirmar')
            ->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'El carrito está vacío. Agregue servicios antes de confirmar.',
            ]);
    }

    #[Test]
    public function cannot_confirm_nonexistent_cart()
    {
        Sanctum::actingAs($this->user);

        Reserva::where('usuario_id', $this->user->id)
            ->where('estado', Reserva::ESTADO_EN_CARRITO)
            ->delete();

        $this->postJson('/api/reservas/carrito/confirmar')
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'No se encontró un carrito de reservas',
            ]);
    }

    #[Test]
    public function user_can_empty_cart()
    {
        Sanctum::actingAs($this->user);

        $carrito = Reserva::factory()->create([
            'usuario_id' => $this->user->id,
            'estado'     => Reserva::ESTADO_EN_CARRITO,
        ]);

        ReservaServicio::factory()->count(2)->create([
            'reserva_id'     => $carrito->id,
            'servicio_id'    => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'estado'         => ReservaServicio::ESTADO_EN_CARRITO,
        ]);

        $this->deleteJson('/api/reservas/carrito/vaciar')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Carrito vaciado exitosamente',
            ]);

        $this->assertDatabaseMissing('reserva_servicios', [
            'reserva_id' => $carrito->id,
        ]);
    }

    #[Test]
    public function cannot_empty_nonexistent_cart()
    {
        Sanctum::actingAs($this->user);

        Reserva::where('usuario_id', $this->user->id)
            ->where('estado', Reserva::ESTADO_EN_CARRITO)
            ->delete();

        $this->deleteJson('/api/reservas/carrito/vaciar')
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'No se encontró un carrito de reservas',
            ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_cart_endpoints()
    {
        // ❌ No autenticamos aquí (a diferencia del resto)
        // Cualquier request sin Sanctum debe devolver 401

        $this->getJson('/api/reservas/carrito')->assertStatus(401);
        $this->postJson('/api/reservas/carrito/agregar', [])->assertStatus(401);
        $this->deleteJson('/api/reservas/carrito/servicio/1')->assertStatus(401);
        $this->postJson('/api/reservas/carrito/confirmar')->assertStatus(401);
        $this->deleteJson('/api/reservas/carrito/vaciar')->assertStatus(401);
    }

    #[Test]
    public function add_to_cart_uses_service_reference_price()
    {
        Sanctum::actingAs($this->user);

        $serviceData = [
            'servicio_id'      => $this->servicio->id,
            'emprendedor_id'   => $this->emprendedor->id,
            'fecha_inicio'     => '2024-07-01',
            'fecha_fin'        => '2024-07-01',
            'hora_inicio'      => '10:00:00',
            'hora_fin'         => '12:00:00',
            'duracion_minutos' => 120,
            'cantidad'         => 1,
        ];

        $this->postJson('/api/reservas/carrito/agregar', $serviceData)
            ->assertStatus(200);

        $this->assertDatabaseHas('reserva_servicios', [
            'servicio_id' => $this->servicio->id,
            'precio'      => $this->servicio->precio_referencial,
        ]);
    }

    #[Test]
    public function cannot_remove_service_from_confirmed_reservation()
    {
        Sanctum::actingAs($this->user);

        $reserva = Reserva::factory()->create([
            'usuario_id' => $this->user->id,
            'estado'     => Reserva::ESTADO_PENDIENTE,
        ]);

        $servicioReserva = ReservaServicio::factory()->create([
            'reserva_id'     => $reserva->id,
            'servicio_id'    => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'estado'         => ReservaServicio::ESTADO_PENDIENTE,
        ]);

        $this->deleteJson("/api/reservas/carrito/servicio/{$servicioReserva->id}")
            ->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Este servicio ya no está en el carrito',
            ]);
    }
}
