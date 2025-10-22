<?php

namespace Tests\Feature\Controllers;

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

class ReservaControllerTest extends TestCase
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

    #[Test]
    public function admin_puede_obtener_todas_las_reservas()
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
    public function usuario_solo_puede_ver_sus_propias_reservas()
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
    public function puede_obtener_reserva_por_id()
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
    public function usuario_no_puede_ver_reserva_de_otro_usuario()
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
    public function admin_puede_ver_cualquier_reserva()
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
    public function puede_crear_reserva_con_servicios()
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
    public function valida_datos_requeridos_al_crear_reserva()
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/reservas', []);

        $response->assertStatus(422);
    }





    #[Test]
    public function puede_eliminar_reserva_propia()
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
    public function no_puede_eliminar_reserva_de_otro_usuario()
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
    public function puede_cambiar_estado_de_reserva()
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
    public function valida_estados_validos_al_cambiar_estado()
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
    public function puede_obtener_reservas_por_emprendedor()
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
    public function puede_obtener_reservas_por_servicio()
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
    public function puede_crear_reserva_rapida_para_usuario_autenticado()
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
    public function puede_obtener_mis_reservas()
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
    public function valida_servicios_requeridos_en_reserva_rapida()
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/reservas/mis-reservas', [
            'notas' => 'Sin servicios'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['servicios']);
    }

    #[Test]
    public function valida_datos_de_servicio_en_reserva_rapida()
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
    public function puede_crear_reserva_con_multiples_servicios()
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
    public function puede_manejar_reservas_en_diferentes_estados()
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
    public function requiere_autenticacion_para_todas_las_operaciones()
    {
        $this->getJson('/api/reservas')->assertStatus(401);
        $this->postJson('/api/reservas', [])->assertStatus(401);
        $this->putJson('/api/reservas/1', [])->assertStatus(401);
        $this->deleteJson('/api/reservas/1')->assertStatus(401);
        $this->getJson('/api/reservas/mis-reservas')->assertStatus(401);
    }

    #[Test]
    public function puede_crear_reserva_con_servicios_de_diferentes_tipos()
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
