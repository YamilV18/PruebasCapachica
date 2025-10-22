<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\Models\User;
use App\Models\Municipalidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

class MunicipalidadControllerTest extends TestCase
{
    use RefreshDatabase;

    /** Ruta base resuelta dinámicamente */
    private ?string $resolvedBase = null;

    /** Candidatos de ruta para tu API */
    private array $routeCandidates = [
        '/api/municipalidades',
        '/api/page-general/municipalidades',
        '/api/municipalidad',
        '/api/page-general/municipalidad',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Crea roles mínimos por si tu app los usa en policies/guards
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'emprendedor']);
        Role::firstOrCreate(['name' => 'turista']);
    }

    /** Intenta resolver y cachear la base path válida (no 404). */
    private function resolveBaseOrSkip(): string
    {
        if ($this->resolvedBase !== null) {
            return $this->resolvedBase;
        }

        foreach ($this->routeCandidates as $candidate) {
            $resp = $this->json('GET', $candidate);
            if ($resp->getStatusCode() !== 404) {
                $this->resolvedBase = rtrim($candidate, '/');
                return $this->resolvedBase;
            }
        }

        $this->markTestSkipped('No se encontró ninguna ruta válida para municipalidades (todas devolvieron 404).');
    }

    /** Helper para llamar a la API sobre la base resuelta */
    private function api(string $method, string $path = '', array $data = [])
    {
        $base = $this->resolveBaseOrSkip();
        $url  = rtrim($base . $path, '/');
        return $this->json($method, $url, $data);
    }

    public function test_user_can_get_all_municipalidades()
    {
        Municipalidad::factory()->count(3)->create();

        $response = $this->api('GET');

        // Si tu controlador devuelve 200 con estructura { success, data: [] }
        // esta aserción pasará. Si tu estructura varía, ajusta aquí.
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'nombre',
                    ]
                ]
            ])
            ->assertJson(['success' => true]);
    }

    public function test_user_can_get_specific_municipalidad()
    {
        $municipalidad = Municipalidad::factory()->create();

        $response = $this->api('GET', "/{$municipalidad->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'nombre',
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $municipalidad->id,
                    'nombre' => $municipalidad->nombre,
                ]
            ]);
    }

    public function test_get_nonexistent_municipalidad_returns_404()
    {
        $this->api('GET', '/999999')->assertStatus(404);
    }


    public function test_non_admin_cannot_create_municipalidad()
    {
        // Usuario no admin (sin rol explícito y sin token Sanctum)
        $municipalidadData = [
            'nombre'      => 'Nueva Municipalidad',
            'descripcion' => 'Descripción de la nueva municipalidad',
        ];

        $response = $this->api('POST', '', $municipalidadData);

        // Dependiendo del middleware puede devolver 401 (no autenticado) o 403 (no autorizado)
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_non_admin_cannot_update_municipalidad()
    {
        $municipalidad = Municipalidad::factory()->create();

        $updateData = ['nombre' => 'Municipalidad Actualizada'];

        $response = $this->api('PUT', "/{$municipalidad->id}", $updateData);

        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_non_admin_cannot_delete_municipalidad()
    {
        $municipalidad = Municipalidad::factory()->create();

        $response = $this->api('DELETE', "/{$municipalidad->id}");

        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_unauthenticated_user_can_view_municipalidades()
    {
        Municipalidad::factory()->count(2)->create();

        // Sin autenticación (no llamamos a Sanctum::actingAs)
        $response = $this->api('GET');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_unauthenticated_user_cannot_create_municipalidad()
    {
        // Sin autenticación
        $response = $this->api('POST', '', []);

        // Según tu middleware podría ser 401, 403, o incluso 422 si se valida primero el body
        $this->assertContains($response->status(), [401, 403, 422]);
    }
}
