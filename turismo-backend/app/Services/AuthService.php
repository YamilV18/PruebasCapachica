<?php

namespace App\Services;

use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthService
{
    /**
     * Register a new user
     *
     * @param array $data
     * @param UploadedFile|null $profilePhoto
     * @return User
     */
    public function register(array $data, ?UploadedFile $profilePhoto = null): User
    {
        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'country' => $data['country'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'address' => $data['address'] ?? null,
            'gender' => $data['gender'] ?? null,
            'preferred_language' => $data['preferred_language'] ?? null,
            'active' => true,
        ];
        
        // Process profile photo if provided
        if ($profilePhoto) {
            $userData['foto_perfil'] = $profilePhoto->store('fotos_perfil', 'public');
        }

        $user = User::create($userData);
        
        // Assign default user role
        $user->assignRole('user');
        
        // Dispatch registered event to trigger verification email
        event(new Registered($user));
        
        return $user;
    }
    
    /**
     * Handle user login
     *
     * @param string $email
     * @param string $password
     * @return array|null
     */
    public function login(string $email, string $password): ?array
    {
        if (!auth()->attempt(['email' => $email, 'password' => $password])) {
            return null;
        }
        
        $user = User::where('email', $email)->firstOrFail();
        
        // Check if the user is active
        if (!$user->active) {
            return ['error' => 'inactive_user'];
        }
        
        // Update last_login timestamp
        $user->update(['last_login' => now()]);
        
        // Delete previous tokens
        $user->tokens()->delete();
        
        $token = $user->createToken('auth_token')->plainTextToken;
        
        return [
            'user' => $user,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'administra_emprendimientos' => $user->administraEmprendimientos(),
            'access_token' => $token,
            'token_type' => 'Bearer',
            'email_verified' => $user->hasVerifiedEmail(),
        ];
    }
    
    /**
     * Handle Google OAuth login
     *
     * @return array
     */
    public function handleGoogleCallback(): array
    {
        try {
            // Configure Socialite to ignore SSL verification only in development
            if (app()->environment('local')) {
                $client = new Client(['verify' => false]);
                Socialite::driver('google')->setHttpClient($client);
            }
            
            $googleUser = Socialite::driver('google')->stateless()->user();
            
            // Search user by google_id or email
            $user = User::where('google_id', $googleUser->id)
                        ->orWhere('email', $googleUser->email)
                        ->first();
            
            // Extract available data from Google
            $locale = $googleUser->user['locale'] ?? null;
            
            // If the user doesn't exist, create a new one
            if (!$user) {
                $user = User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'password' => Hash::make(Str::random(16)),
                    'google_id' => $googleUser->id,
                    'avatar' => $googleUser->avatar,
                    'email_verified_at' => now(), // Mark as verified since it comes from Google
                    'preferred_language' => $locale ? substr($locale, 0, 2) : null, // Extract language code from locale
                    'active' => true,
                    'last_login' => now(),
                ]);
                
                // Assign user role
                $user->assignRole('user');
            } 
            // If the user exists but doesn't have google_id, update it
            else if (!$user->google_id) {
                $updateData = [
                    'google_id' => $googleUser->id,
                    'avatar' => $googleUser->avatar,
                    'email_verified_at' => now(), // Mark as verified
                    'last_login' => now(),
                ];
                
                // Add preferred language if not already set and available from Google
                if (!$user->preferred_language && $locale) {
                    $updateData['preferred_language'] = substr($locale, 0, 2);
                }
                
                $user->update($updateData);
            } else {
                // Update last login time
                $user->update(['last_login' => now()]);
            }
            
            // Delete previous tokens
            $user->tokens()->delete();
            
            // Create new token
            $token = $user->createToken('auth_token')->plainTextToken;
            
            return [
                'user' => $user,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'administra_emprendimientos' => $user->administraEmprendimientos(),
                'access_token' => $token,
                'token_type' => 'Bearer',
                'email_verified' => true,
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'google_auth_failed',
                'message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Update user profile
     *
     * @param User $user
     * @param array $data
     * @param UploadedFile|null $profilePhoto
     * @return User
     */
    public function updateProfile(User $user, array $data, ?UploadedFile $profilePhoto = null): User
    {
        $userData = array_filter($data, function ($key) {
            return in_array($key, [
                'name', 'email', 'phone', 'country', 
                'birth_date', 'address', 'gender', 'preferred_language'
            ]);
        }, ARRAY_FILTER_USE_KEY);
        
        // Update password if provided
        if (!empty($data['password'])) {
            $userData['password'] = Hash::make($data['password']);
        }
        
        // Process profile photo if provided
        if ($profilePhoto && $profilePhoto->isValid()) {
            // Delete previous photo if exists
            if ($user->foto_perfil && !filter_var($user->foto_perfil, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($user->foto_perfil);
            }
            
            $userData['foto_perfil'] = $profilePhoto->store('fotos_perfil', 'public');
        }
        
        // If email changes, mark as not verified
        if (isset($userData['email']) && $userData['email'] !== $user->email) {
            $userData['email_verified_at'] = null;
        }
        
        $user->update($userData);
        
        // If email changed, send verification
        if (isset($userData['email']) && $userData['email'] !== $user->getOriginal('email')) {
            $user->sendEmailVerificationNotification();
        }
        
        return $user;
    }
    
    /**
     * Send password reset link
     *
     * @param string $email
     * @return string
     */
    public function sendPasswordResetLink(string $email): string
    {
        $status = Password::sendResetLink(['email' => $email]);
        
        return $status;
    }
    
    /**
     * Reset password
     *
     * @param array $data
     * @return string
     */
    public function resetPassword(array $data): string
    {
        $status = Password::reset(
            $data,
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));
                
                $user->save();
                
                event(new PasswordReset($user));
            }
        );
        
        return $status;
    }
    
    /**
     * Verify email
     *
     * @param int $id
     * @param string $hash
     * @return bool
     */
    public function verifyEmail(int $id, string $hash): bool
    {
        $user = User::findOrFail($id);
        
        if (!hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return false;
        }
        
        if ($user->hasVerifiedEmail()) {
            return true;
        }
        
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }
        
        return true;
    }
    
    /**
     * Resend verification email
     *
     * @param User $user
     * @return void
     */
    public function resendVerificationEmail(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            throw new \Exception('Email already verified');
        }
        
        $user->sendEmailVerificationNotification();
    }
}