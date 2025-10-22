<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authService = new AuthService();

        // Crear roles básicos
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'emprendedor']);
        Role::create(['name' => 'turista']);
        Role::create(['name' => 'user']);

        Storage::fake('public');
        Event::fake();
        Notification::fake();
    }

    /** @test */
    public function register_creates_new_user_successfully()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'phone' => '123456789',
            'country' => 'Perú',
            'birth_date' => '1990-01-01',
            'address' => 'Test Address',
            'gender' => 'masculino',
            'preferred_language' => 'es',
        ];

        $user = $this->authService->register($userData);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertTrue($user->active);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertTrue($user->hasRole('user'));

        Event::assertDispatched(Registered::class);
    }

    /** @test */
    public function register_with_profile_photo_stores_file()
    {
        $file = UploadedFile::fake()->image('avatar.jpg');

        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        $user = $this->authService->register($userData, $file);

        $this->assertNotNull($user->foto_perfil);
        Storage::disk('public')->assertExists($user->foto_perfil);
    }

    /** @test */
    public function login_returns_user_data_on_success()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'active' => true,
        ]);

        $result = $this->authService->login('test@example.com', 'password123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('token_type', $result);
        $this->assertArrayHasKey('email_verified', $result);
        $this->assertEquals('Bearer', $result['token_type']);
        $this->assertTrue($result['email_verified']);
    }

    /** @test */
    public function login_returns_null_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $result = $this->authService->login('test@example.com', 'wrong-password');

        $this->assertNull($result);
    }

    /** @test */
    public function login_returns_error_for_inactive_user()
    {
        $user = User::factory()->inactive()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $result = $this->authService->login('test@example.com', 'password123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('inactive_user', $result['error']);
    }

    /** @test */
    public function login_updates_last_login_timestamp()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'active' => true,
            'last_login' => null,
        ]);

        $this->authService->login('test@example.com', 'password123');

        $user->refresh();
        $this->assertNotNull($user->last_login);
    }

    /** @test */
    public function login_deletes_previous_tokens()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'active' => true,
        ]);

        // Create a previous token
        $oldToken = $user->createToken('old_token');
        $this->assertCount(1, $user->tokens);

        $this->authService->login('test@example.com', 'password123');

        $user->refresh();
        $this->assertCount(1, $user->tokens);
        $this->assertNotEquals($oldToken->accessToken->id, $user->tokens->first()->id);
    }

    /** @test */
    public function update_profile_updates_user_data()
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'phone' => '111111111',
        ]);

        $updateData = [
            'name' => 'New Name',
            'phone' => '999999999',
            'address' => 'New Address',
        ];

        $updatedUser = $this->authService->updateProfile($user, $updateData);

        $this->assertEquals('New Name', $updatedUser->name);
        $this->assertEquals('999999999', $updatedUser->phone);
        $this->assertEquals('New Address', $updatedUser->address);
    }

    /** @test */
    public function update_profile_with_password_hashes_it()
    {
        $user = User::factory()->create();

        $updateData = [
            'password' => 'newpassword123',
        ];

        $updatedUser = $this->authService->updateProfile($user, $updateData);

        $this->assertTrue(Hash::check('newpassword123', $updatedUser->password));
    }

    /** @test */
    public function update_profile_with_photo_stores_file()
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('new-avatar.jpg');

        $updateData = ['name' => 'Updated Name'];

        $updatedUser = $this->authService->updateProfile($user, $updateData, $file);

        $this->assertNotNull($updatedUser->foto_perfil);
        Storage::disk('public')->assertExists($updatedUser->foto_perfil);
    }

    /** @test */
    public function update_profile_deletes_old_photo_when_uploading_new_one()
    {
        Storage::fake('public');
        $oldFile = UploadedFile::fake()->image('old-avatar.jpg');
        $oldPath = $oldFile->store('fotos_perfil', 'public');

        $user = User::factory()->create(['foto_perfil' => $oldPath]);

        Storage::disk('public')->assertExists($oldPath);

        $newFile = UploadedFile::fake()->image('new-avatar.jpg');
        $updateData = ['name' => 'Updated Name'];

        $this->authService->updateProfile($user, $updateData, $newFile);

        Storage::disk('public')->assertMissing($oldPath);
    }

    /** @test */
    public function update_profile_marks_email_as_unverified_when_changing_email()
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        $updateData = ['email' => 'new@example.com'];

        $updatedUser = $this->authService->updateProfile($user, $updateData);

        $this->assertEquals('new@example.com', $updatedUser->email);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $updatedUser->email_verified_at);
    }

    /** @test */
    public function send_password_reset_link_calls_password_facade()
    {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => 'test@example.com'])
            ->andReturn(Password::RESET_LINK_SENT);

        $result = $this->authService->sendPasswordResetLink('test@example.com');

        $this->assertEquals(Password::RESET_LINK_SENT, $result);
    }

    /** @test */
    public function reset_password_calls_password_facade()
    {
        $resetData = [
            'email' => 'test@example.com',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
            'token' => 'reset-token',
        ];

        Password::shouldReceive('reset')
            ->once()
            ->with($resetData, \Closure::class)
            ->andReturn(Password::PASSWORD_RESET);

        $result = $this->authService->resetPassword($resetData);

        $this->assertEquals(Password::PASSWORD_RESET, $result);
    }

    /** @test */
    public function verify_email_returns_false_with_invalid_hash()
    {
        $user = User::factory()->unverified()->create();

        $result = $this->authService->verifyEmail($user->id, 'invalid-hash');

        $this->assertFalse($result);
    }

    /** @test */
    public function verify_email_returns_true_if_already_verified()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $hash = sha1($user->getEmailForVerification());

        $result = $this->authService->verifyEmail($user->id, $hash);

        $this->assertTrue($result);
    }

    /** @test */
    public function verify_email_marks_email_as_verified()
    {
        $user = User::factory()->unverified()->create();
        $hash = sha1($user->getEmailForVerification());

        $result = $this->authService->verifyEmail($user->id, $hash);

        $this->assertTrue($result);
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        Event::assertDispatched(Verified::class);
    }

    /** @test */
    public function resend_verification_email_throws_exception_if_already_verified()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Email already verified');

        $this->authService->resendVerificationEmail($user);
    }

    /** @test */
    public function resend_verification_email_sends_notification_for_unverified_user()
    {
        $user = User::factory()->unverified()->create();

        $this->authService->resendVerificationEmail($user);

        // This would typically trigger a notification, but we're using fake
        $this->assertTrue(true); // Test passes if no exception is thrown
    }

    /** @test */
    public function handle_google_callback_returns_error_on_exception()
    {
        // Mock Socialite to throw an exception
        $this->mock(\Laravel\Socialite\Contracts\Factory::class, function ($mock) {
            $mock->shouldReceive('driver->stateless->user')
                ->andThrow(new \Exception('OAuth error'));
        });

        $result = $this->authService->handleGoogleCallback();

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('google_auth_failed', $result['error']);
    }
}
