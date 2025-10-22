<?php

namespace Tests\Feature\Controllers;

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

class EventControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Emprendedor $emprendedor;
    protected Asociacion $asociacion;

    protected function setUp(): void
    {
        parent::setUp();

        // Permisos/roles mínimos
        Permission::firstOrCreate(['name' => 'ver eventos']);
        Permission::firstOrCreate(['name' => 'crear eventos']);
        Permission::firstOrCreate(['name' => 'editar eventos']);
        Permission::firstOrCreate(['name' => 'eliminar eventos']);

        $role = Role::firstOrCreate(['name' => 'administrador']);
        $role->givePermissionTo(['ver eventos', 'crear eventos', 'editar eventos', 'eliminar eventos']);

        $this->user = User::factory()->create();
        $this->user->assignRole('administrador');

        $municipalidad = Municipalidad::factory()->create();
        $this->asociacion   = Asociacion::factory()->create(['municipalidad_id' => $municipalidad->id]);
        $this->emprendedor  = Emprendedor::factory()->create(['asociacion_id' => $this->asociacion->id]);

        Sanctum::actingAs($this->user, ['*']);
    }

    /** Helper: crea un evento vía API y retorna el array de respuesta "data" */
    private function crearEvento(array $overrides = []): array
    {
        $base = [
            'nombre'            => $this->faker->sentence(3),
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

        $payload  = array_merge($base, $overrides);
        $response = $this->postJson('/api/eventos', $payload);
        $response->assertStatus(201)->assertJson(['success' => true]);

        return $response->json('data');
    }

    #[Test]
    public function puede_obtener_lista_paginada_de_eventos()
    {
        // Creamos 15 eventos por API
        for ($i = 0; $i < 15; $i++) $this->crearEvento();

        $response = $this->getJson('/api/eventos');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['current_page','data','total','per_page']
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals(15, $response->json('data.total'));
        $this->assertGreaterThan(0, count($response->json('data.data')));
    }

    #[Test]
    public function puede_obtener_evento_por_id()
    {
        $evento = $this->crearEvento();
        $id     = $evento['id'];

        $this->getJson("/api/eventos/{$id}")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['id' => $id, 'nombre' => $evento['nombre']]]);
    }

    #[Test]
    public function retorna_404_cuando_evento_no_existe()
    {
        $this->getJson('/api/eventos/999')
            ->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Evento no encontrado']);
    }


    #[Test]
    public function valida_datos_requeridos_al_crear_evento()
    {
        $this->postJson('/api/eventos', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre','fecha_inicio','id_emprendedor']);
    }

    #[Test]
    public function valida_emprendedor_existente_al_crear_evento()
    {
        $this->postJson('/api/eventos', [
            'nombre' => 'Evento Test',
            'fecha_inicio' => now()->format('Y-m-d'),
            'id_emprendedor' => 999
        ])->assertStatus(422)->assertJsonValidationErrors(['id_emprendedor']);
    }

    #[Test]
    public function puede_actualizar_evento_existente()
    {
        $evento = $this->crearEvento();
        $id     = $evento['id'];

        $payload = [
            'nombre'       => 'Evento Actualizado',
            'descripcion'  => 'Descripción actualizada',
            'tipo_evento'  => 'Actividad Gastronómica',
            'fecha_inicio' => now()->addDays(10)->format('Y-m-d'),
            'hora_inicio'  => '14:00:00',
        ];

        $this->putJson("/api/eventos/{$id}", $payload)
            ->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Evento actualizado exitosamente',
                'data'    => ['id' => $id, 'nombre' => 'Evento Actualizado', 'tipo_evento' => 'Actividad Gastronómica']]);

        $this->assertDatabaseHas('eventos', ['id' => $id, 'nombre' => 'Evento Actualizado', 'tipo_evento' => 'Actividad Gastronómica']);
    }

    #[Test]
    public function puede_eliminar_evento()
    {
        $evento = $this->crearEvento();
        $id     = $evento['id'];

        $this->deleteJson("/api/eventos/{$id}")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Evento eliminado exitosamente']);

        $this->assertDatabaseMissing('eventos', ['id' => $id]);
    }

    #[Test]
    public function retorna_404_al_eliminar_evento_inexistente()
    {
        $this->deleteJson('/api/eventos/999')
            ->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Evento no encontrado']);
    }

    #[Test]
    public function puede_obtener_eventos_por_emprendedor()
    {
        // otro emprendedor
        $otro = Emprendedor::factory()->create(['asociacion_id' => $this->asociacion->id]);

        // 3 del principal, 2 del otro
        for ($i=0;$i<3;$i++) $this->crearEvento();
        for ($i=0;$i<2;$i++) $this->crearEvento(['id_emprendedor' => $otro->id]);

        $resp = $this->getJson("/api/eventos/emprendedor/{$this->emprendedor->id}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $eventos = $resp->json('data');
        $this->assertCount(3, $eventos);
        foreach ($eventos as $e) $this->assertEquals($this->emprendedor->id, $e['id_emprendedor']);
    }

    #[Test]
    public function puede_obtener_eventos_activos()
    {
        // 3 futuros (activos), 2 pasados
        for ($i=0;$i<3;$i++) $this->crearEvento([
            'fecha_inicio' => now()->addDays(5+$i)->format('Y-m-d'),
            'fecha_fin'    => now()->addDays(6+$i)->format('Y-m-d'),
        ]);
        for ($i=0;$i<2;$i++) $this->crearEvento([
            'fecha_inicio' => now()->subDays(10+$i)->format('Y-m-d'),
            'fecha_fin'    => now()->subDays(9+$i)->format('Y-m-d'),
        ]);

        $resp = $this->getJson('/api/eventos/activos')->assertStatus(200)->assertJson(['success' => true]);

        $this->assertCount(3, $resp->json('data'));
    }

    #[Test]
    public function puede_obtener_proximos_eventos()
    {
        // 10 futuros
        for ($i=0;$i<10;$i++) $this->crearEvento([
            'fecha_inicio' => now()->addDays($i+1)->format('Y-m-d'),
            'fecha_fin'    => now()->addDays($i+2)->format('Y-m-d'),
        ]);

        $resp = $this->getJson('/api/eventos/proximos')->assertStatus(200)->assertJson(['success' => true]);

        $eventos = $resp->json('data');
        $this->assertCount(5, $eventos); // límite por defecto
        for ($i=0; $i < count($eventos)-1; $i++) {
            $this->assertLessThanOrEqual($eventos[$i+1]['fecha_inicio'], $eventos[$i]['fecha_inicio']);
        }
    }

    #[Test]
    public function puede_obtener_proximos_eventos_con_limite_personalizado()
    {
        for ($i=0;$i<10;$i++) $this->crearEvento([
            'fecha_inicio' => now()->addDays($i+1)->format('Y-m-d'),
            'fecha_fin'    => now()->addDays($i+2)->format('Y-m-d'),
        ]);

        $resp = $this->getJson('/api/eventos/proximos?limite=3')->assertStatus(200);
        $this->assertCount(3, $resp->json('data'));
    }

    #[Test]
    public function valida_fechas_coherentes_al_crear_evento()
    {
        $this->postJson('/api/eventos', [
            'nombre'         => 'Evento Test',
            'fecha_inicio'   => '2024-08-20',
            'fecha_fin'      => '2024-08-15', // fin < inicio
            'id_emprendedor' => $this->emprendedor->id,
        ])->assertStatus(422);
    }

    #[Test]
    public function puede_manejar_coordenadas_geograficas()
    {
        $nombre = 'Evento con Ubicación';
        $evento = $this->crearEvento([
            'nombre'        => $nombre,
            'coordenada_x'  => -69.8573,
            'coordenada_y'  => -15.6123,
        ]);

        $this->assertDatabaseHas('eventos', [
            'id'            => $evento['id'],
            'nombre'        => $nombre,
            'coordenada_x'  => -69.8573,
            'coordenada_y'  => -15.6123,
        ]);
    }
}
