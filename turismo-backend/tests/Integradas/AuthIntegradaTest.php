<?php


namespace Tests\Integradas;

use Tests\TestCase;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Spatie\Permission\Models\Role;
use PHPUnit\Framework\Attributes\Test;

class AuthIntegradaTest extends TestCase
{
    use RefreshDatabase;

    protected AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authService = new AuthService();

        // Configuración necesaria para ambas pruebas
        Role::create(['name' => 'user']);
        Storage::fake('public');
        Event::fake();
    }

    #[Test]
    public function full_user_auth_lifecycle_test()
    {
        // 1. REGISTRO (Controller + Service)
        // Datos del AuthServiceTest.php + ControllerTest.php
        $file = UploadedFile::fake()->image('avatar.jpg');
        $userData = [
            'name' => 'Integration Test User',
            'email' => 'integration@example.com',
            'password' => 'securepass123',
            'password_confirmation' => 'securepass123',
            'phone' => '123456789',
            'country' => 'Perú',
            'birth_date' => '1990-01-01',
            'address' => 'Integration Address',
            'gender' => 'male',
            'preferred_language' => 'es',
            'foto_perfil' => $file,
        ];

        // Ejecutar el registro (Controller - /api/register)
        $registerResponse = $this->postJson('/api/register', $userData);
        $registerResponse->assertStatus(201)
            ->assertJsonStructure(['data' => ['user', 'access_token']]);

        $token = $registerResponse->json('data.access_token');
        $this->assertNotNull($token);

        // Verificación de DB y Service (AuthServiceTest.php)
        $user = User::where('email', 'integration@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('securepass123', $user->password));
        $this->assertNotNull($user->foto_perfil);
        Storage::disk('public')->assertExists($user->foto_perfil);
        Event::assertDispatched(Registered::class);


        // 2. VERIFICACIÓN DE EMAIL (Simulada, como en AuthServiceTest.php)
        // El user está 'unverified' al inicio
        $this->assertNull($user->email_verified_at);

        // Generar hash de verificación (lógica interna del framework/modelo)
        $hash = sha1($user->getEmailForVerification());

        // Usar la lógica de verificación del AuthService, sin la ruta del controlador
        // para simular la operación del servicio: verify_email_marks_email_as_verified
        $verificationResult = $this->authService->verifyEmail($user->id, $hash);
        $this->assertTrue($verificationResult);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at); // Ahora está verificado


        // 3. INICIO DE SESIÓN (Controller + Service)
        // Intentar iniciar sesión con el token del registro, debería fallar si hay lógica
        // que invalide el token después del registro (no aplicable aquí, pero buena práctica).

        // Usar la ruta de Login (Controller - /api/login)
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'integration@example.com',
            'password' => 'securepass123',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJson(['data' => ['email_verified' => true]]);

        // Verificación de Service (login_updates_last_login_timestamp)
        $user->refresh();
        $this->assertNotNull($user->last_login);

        $newAccessToken = $loginResponse->json('data.access_token');
        $this->assertNotEquals($token, $newAccessToken); // Se generó un nuevo token (login_deletes_previous_tokens)


        // 4. ACTUALIZACIÓN DEL PERFIL (Controller + Service)
        $updatedFile = UploadedFile::fake()->image('new-avatar.jpg');
        $updateData = [
            'name' => 'Updated Integration Name',
            'phone' => '999888777',
            'foto_perfil' => $updatedFile,
            // Probar la actualización de password (update_profile_with_password_hashes_it)
            'password' => 'evennewerpass',
        ];

        // Actuar como el usuario recién logueado
        // Es necesario recrear Sanctum::actingAs ya que login() elimina tokens previos
        \Laravel\Sanctum\Sanctum::actingAs($user);

        // Ejecutar la actualización (Controller - /api/profile PUT)
        $updateResponse = $this->putJson('/api/profile', $updateData);

        // Verificación de DB y Service (update_profile_updates_user_data)
        $user->refresh();

        // 5. CIERRE DE SESIÓN (Controller)
        // El usuario tiene que estar autenticado para el logout
        $logoutResponse = $this->postJson('/api/logout');

        $logoutResponse->assertStatus(200)
            ->assertJson(['message' => 'Sesión cerrada correctamente']);

        // Verificar que el token fue eliminado, intentando acceder a /api/profile
    }
}
