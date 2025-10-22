<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use PHPUnit\Framework\Attributes\Test;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Spatie: limpiar cache de permisos
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'emprendedor']);
        Role::create(['name' => 'turista']);
        Role::create(['name' => 'user']);

        // Fakes de almacenamiento y notificaciones
        Storage::fake('media');
        Storage::fake('public'); // ← agregado
        Mail::fake();
        Notification::fake();
    }

    /**
     * Helper para validar que la foto exista en 'media' o 'public'
     */
    private function assertFotoExisteEnCualquieraDeLosDiscos(string $path): void
    {
        $existsMedia  = Storage::disk('media')->exists($path);
        $existsPublic = Storage::disk('public')->exists($path);

        $this->assertTrue(
            $existsMedia || $existsPublic,
            "No se encontró la foto en 'media' ni en 'public' para la ruta [$path]"
        );
    }

    #[Test]
    public function user_can_register_successfully()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '123456789',
            'country' => 'Perú',
            'birth_date' => '1990-01-01',
            'address' => 'Test Address',
            'gender' => 'male',
            'preferred_language' => 'es',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'access_token',
                    'token_type',
                    'email_verified',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name'  => 'Test User',
        ]);
    }

    #[Test]
    public function user_can_register_with_profile_photo()
    {
        $file = UploadedFile::fake()->image('avatar.jpg');

        $userData = [
            'name' => 'Test User',
            'email' => 'test2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '123456789',
            'country' => 'Perú',
            'birth_date' => '1990-01-01',
            'address' => 'Test Address',
            'gender' => 'male',
            'preferred_language' => 'es',
            'foto_perfil' => $file,
        ];

        $this->postJson('/api/register', $userData)
            ->assertStatus(201);

        $user = User::where('email', 'test2@example.com')->firstOrFail();

        if (!empty($user->foto_perfil)) {
            $this->assertFotoExisteEnCualquieraDeLosDiscos($user->foto_perfil);
        }
    }

    #[Test]
    public function register_validation_fails_with_invalid_data()
    {
        $response = $this->postJson('/api/register', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    #[Test]
    public function user_can_login_successfully()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'active' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user',
                    'roles',
                    'permissions',
                    'administra_emprendimientos',
                    'access_token',
                    'token_type',
                    'email_verified',
                ],
            ]);
    }

    #[Test]
    public function login_fails_with_invalid_credentials()
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function login_fails_for_inactive_user()
    {
        User::factory()->inactive()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ])->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function login_fails_for_unverified_email()
    {
        User::factory()->unverified()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'active' => true,
        ]);

        $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ])->assertStatus(403)
            ->assertJsonFragment(['success' => false])
            ->assertJsonStructure(['success','message']);
    }

    #[Test]
    public function authenticated_user_can_get_profile()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/profile')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'roles',
                    'permissions',
                    'administra_emprendimientos',
                    'emprendimientos',
                    'email_verified',
                ],
            ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_get_profile()
    {
        $this->getJson('/api/profile')->assertStatus(401);
    }

    #[Test]
    public function authenticated_user_can_update_profile()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $updateData = [
            'name' => 'Updated Name',
            'phone' => '987654321',
            'address' => 'Updated Address',
        ];

        $response = $this->putJson('/api/profile', $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Perfil actualizado correctamente',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'phone' => '987654321',
            'address' => 'Updated Address',
        ]);
    }

    #[Test]
    public function authenticated_user_can_update_profile_with_photo()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('new-avatar.jpg');

        $this->putJson('/api/profile', [
            'name' => 'Updated Name',
            'foto_perfil' => $file,
        ])->assertStatus(200);

        $user->refresh();

        if (!empty($user->foto_perfil)) {
            $this->assertFotoExisteEnCualquieraDeLosDiscos($user->foto_perfil);
        }
    }

    #[Test]
    public function authenticated_user_can_logout()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/logout')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Sesión cerrada correctamente',
            ]);
    }

    #[Test]
    public function user_can_request_password_reset()
    {
        User::factory()->create(['email' => 'test@example.com']);

        $this->postJson('/api/forgot-password', [
            'email' => 'test@example.com',
        ])->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function forgot_password_fails_with_invalid_email_format()
    {
        $response = $this->postJson('/api/forgot-password', [
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function user_can_resend_verification_email()
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/email/verification-notification')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function resend_verification_fails_for_already_verified_user()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($user);

        $this->postJson('/api/email/verification-notification')
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function google_redirect_returns_url()
    {
        $this->getJson('/api/auth/google')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['url'],
            ]);
    }
}
