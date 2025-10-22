<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use App\Traits\ApiResponseTrait;
use Google\Client as GoogleClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    use ApiResponseTrait;
    
    protected $authService;
    
    /**
     * Constructor
     * 
     * @param AuthService $authService
     */
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }
    
    /**
     * Redirect to Google OAuth
     * 
     * @return JsonResponse
     */
    public function redirectToGoogle(): JsonResponse
    {
        // Configurar Socialite para ignorar verificación SSL solo en desarrollo
        if (app()->environment('local')) {
            $client = new \GuzzleHttp\Client(['verify' => false]);
            \Laravel\Socialite\Facades\Socialite::driver('google')->setHttpClient($client);
        }
        
        $url = Socialite::driver('google')
            ->stateless()
            ->redirect()
            ->getTargetUrl();
            
        return $this->successResponse([
            'url' => $url
        ]);
    }
    
    /**
     * Handle Google callback
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            // Si tenemos el código pero no state, estamos recibiendo la redirección de Google
            if ($request->has('code')) {
                $result = $this->authService->handleGoogleCallback();
                
                if (isset($result['error'])) {
                    // Para depuración (eliminar en producción)
                    \Log::error('Error Google OAuth: ' . $result['message']);
                    
                    // Redirigir al frontend con error
                    $frontendUrl = config('app.frontend_url', 'http://localhost:4200');
                    return redirect()->away($frontendUrl . '/auth/error?message=' . urlencode('Error al autenticar con Google'));
                }
                
                // Obtener token y redirigir al frontend
                $token = $result['access_token'];
                $frontendUrl = config('app.frontend_url', 'http://localhost:4200');
                
                return redirect()->away($frontendUrl . '/auth/google/callback?token=' . $token);
            }
            
            return $this->errorResponse('Parámetros inválidos', 400);
        } catch (\Exception $e) {
            \Log::error('Error en Google callback: ' . $e->getMessage());
            
            // Redirigir al frontend con error
            $frontendUrl = config('app.frontend_url', 'http://localhost:4200');
            return redirect()->away($frontendUrl . '/auth/error?message=' . urlencode('Error en la autenticación con Google'));
        }
    }
    
    /**
     * Verify Google ID token from frontend
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyGoogleToken(Request $request): JsonResponse
    {
        try {
            $idToken = $request->input('token');
            
            if (!$idToken) {
                return $this->errorResponse('Token no proporcionado', 400);
            }
            
            // Verify token with Google API
            $client = new GoogleClient(['client_id' => config('services.google.client_id')]);
            
            // Configure client to ignore SSL verification only in development
            if (app()->environment('local')) {
                $client->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));
            }
            
            $payload = $client->verifyIdToken($idToken);
            
            if (!$payload) {
                return $this->errorResponse('Token inválido', 400);
            }
            
            // Process payload information
            $googleId = $payload['sub'];
            $email = $payload['email'];
            $name = $payload['name'] ?? '';
            $picture = $payload['picture'] ?? null;
            $locale = $payload['locale'] ?? null;
            
            // Find or create user
            $user = User::where('google_id', $googleId)
                        ->orWhere('email', $email)
                        ->first();
            
            if (!$user) {
                // Create new user
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make(Str::random(16)),
                    'google_id' => $googleId,
                    'avatar' => $picture,
                    'preferred_language' => $locale ? substr($locale, 0, 2) : null,
                    'email_verified_at' => now(), // Consider Google email verified
                    'active' => true,
                    'last_login' => now()
                ]);
                
                // Assign default role
                $user->assignRole('user');
            } else if (!$user->google_id) {
                // Update existing user with google_id
                $updateData = [
                    'google_id' => $googleId,
                    'avatar' => $picture,
                    'email_verified_at' => now(),
                    'active' => true,
                    'last_login' => now()
                ];
                
                // Add preferred language if not set and available from Google
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
            
            return $this->successResponse([
                'user' => new UserResource($user),
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'administra_emprendimientos' => $user->administraEmprendimientos(),
                'access_token' => $token,
                'token_type' => 'Bearer',
                'email_verified' => true,
            ], 'Autenticación con Google exitosa');
            
        } catch (\Exception $e) {
            \Log::error('Error al verificar token de Google: ' . $e->getMessage());
            return $this->errorResponse('Error al verificar token: ' . $e->getMessage(), 500);
        }
    }
}