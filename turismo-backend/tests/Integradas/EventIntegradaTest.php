<?php

namespace Tests\Integradas;

use App\Models\Emprendedor;
use App\Models\Asociacion;
use App\Models\Municipalidad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Prueba de Integración Completa para el EventController.
 * Cubre la interacción del Controller con la API, el Repositorio y la Base de Datos.
 */
class EventIntegradaTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Emprendedor $emprendedor;
    protected Asociacion $asociacion;

    /**
     * Configuración inicial: roles, permisos y usuario autenticado.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 1. Configuración de Roles y Permisos (Controller Setup)
        Permission::firstOrCreate(['name' => 'ver eventos']);
        Permission::firstOrCreate(['name' => 'crear eventos']);
        Permission::firstOrCreate(['name' => 'editar eventos']);
        Permission::firstOrCreate(['name' => 'eliminar eventos']);

        $role = Role::firstOrCreate(['name' => 'administrador']);
        $role->givePermissionTo(['ver eventos', 'crear eventos', 'editar eventos', 'eliminar eventos']);

        $this->user = User::factory()->create();
        $this->user->assignRole('administrador');

        // 2. Creación de Entidades Base (Model/Repository Setup)
        $municipalidad = Municipalidad::factory()->create();
        $this->asociacion   = Asociacion::factory()->create(['municipalidad_id' => $municipalidad->id]);
        $this->emprendedor  = Emprendedor::factory()->create(['asociacion_id' => $this->asociacion->id]);

        // 3. Autenticación de Usuario
        Sanctum::actingAs($this->user, ['*']);
    }

    /** Helper: Proporciona datos válidos para la creación de un Evento. */
    private function getValidEventData(array $overrides = []): array
    {
        $base = [
            'nombre'            => 'Evento de Prueba Integrada',
            'descripcion'       => $this->faker->sentence(8),
            'tipo_evento'       => 'General',
            'idioma_principal'  => 'Español',
            'fecha_inicio'      => now()->addDays(3)->format('Y-m-d'),
            'hora_inicio'       => '10:00:00',
            'fecha_fin'         => now()->addDays(4)->format('Y-m-d'),
            'hora_fin'          => '18:00:00',
            'duracion_horas'    => 8,
            'coordenada_x'      => -69.85,
            'coordenada_y'      => -15.61,
            'id_emprendedor'    => $this->emprendedor->id,
            'que_llevar'        => 'Gorra, agua',
        ];

        return array_merge($base, $overrides);
    }

    #[Test]
    public function test_ciclo_de_vida_completo_del_evento()
    {
        // ----------------------------------------------------
        // FASE 1: CREACIÓN (C - Create)
        // Se llama al Controller, se ejecuta el Repository::create(), se persiste en el Model.
        // ----------------------------------------------------
        $crearData = $this->getValidEventData();

        $response = $this->postJson('/api/eventos', $crearData);

        // Assert: Respuesta de la API y persistencia en DB
        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['id', 'nombre']]);

        $eventoCreado = $response->json('data');
        $eventoId     = $eventoCreado['id'];

        $this->assertDatabaseHas('eventos', [
            'id'             => $eventoId,
            'nombre'         => $crearData['nombre'],
            'id_emprendedor' => $this->emprendedor->id
        ]);


        // ----------------------------------------------------
        // FASE 2: LECTURA (R - Read)
        // Se llama al Controller, se ejecuta el Repository::getById(), se retorna el Model.
        // ----------------------------------------------------
        $response = $this->getJson("/api/eventos/{$eventoId}");

        // Assert: Respuesta de la API y consistencia de datos
        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJson(['data' => ['id' => $eventoId, 'nombre' => $crearData['nombre']]]);


        // ----------------------------------------------------
        // FASE 3: ACTUALIZACIÓN (U - Update)
        // Se llama al Controller, se ejecuta el Repository::update(), se modifica el Model.
        // ----------------------------------------------------
        $actualizarData = [
            'nombre'       => 'Nombre Actualizado Integral',
            'tipo_evento'  => 'Actividad Gastronómica',
            'duracion_horas' => 10,
        ];

        $response = $this->putJson("/api/eventos/{$eventoId}", $actualizarData);

        // Assert: Respuesta de la API y persistencia en DB
        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJson(['data' => ['id' => $eventoId, 'nombre' => $actualizarData['nombre']]]);

        $this->assertDatabaseHas('eventos', [
            'id'             => $eventoId,
            'nombre'         => $actualizarData['nombre'],
            'tipo_evento'    => $actualizarData['tipo_evento'],
            'duracion_horas' => $actualizarData['duracion_horas'],
        ]);


        // ----------------------------------------------------
        // FASE 4: ELIMINACIÓN (D - Delete)
        // Se llama al Controller, se ejecuta el Repository::delete(), se elimina el Model.
        // ----------------------------------------------------
        $response = $this->deleteJson("/api/eventos/{$eventoId}");

        // Assert: Respuesta de la API y desaparición de DB
        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Evento eliminado exitosamente']);

        $this->assertDatabaseMissing('eventos', [
            'id'     => $eventoId,
            'nombre' => $actualizarData['nombre']
        ]);
    }
}
