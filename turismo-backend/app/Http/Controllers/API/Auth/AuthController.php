<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponseTrait;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
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
     * Register a new user
     * 
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register(
            $request->validated(),
            $request->hasFile('foto_perfil') ? $request->file('foto_perfil') : null
        );
        
        $token = $user->createToken('auth_token')->plainTextToken;
        
        return $this->successResponse([
            'user' => new UserResource($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
            'email_verified' => $user->hasVerifiedEmail(),
        ], 'Usuario registrado correctamente. Se ha enviado un correo de verificación.', 201);
    }
    
    /**
     * User login
     * 
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->email,
            $request->password
        );
        
        if (!$result) {
            return $this->errorResponse('Credenciales inválidas', 401);
        }
        
        if (isset($result['error']) && $result['error'] === 'inactive_user') {
            return $this->errorResponse('Usuario inactivo', 403);
        }
        // Verificar si el correo está verificado
        if (!$result['email_verified']) {
            return $this->errorResponse('Por favor, verifica tu correo electrónico para poder de iniciar sesión', 403, [
                'verification_required' => true
            ]);
        }
        
        return $this->successResponse([
            'user' => new UserResource($result['user']),
            'roles' => $result['roles'],
            'permissions' => $result['permissions'],
            'administra_emprendimientos' => $result['administra_emprendimientos'],
            'access_token' => $result['access_token'],
            'token_type' => $result['token_type'],
            'email_verified' => $result['email_verified'],
        ], 'Inicio de sesión exitoso');
    }
    
    /**
     * Login with Google
     * 
     * @return JsonResponse
     */
    public function redirectToGoogle(): JsonResponse
    {
        $url = \Laravel\Socialite\Facades\Socialite::driver('google')
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
     * @return JsonResponse
     */
    public function handleGoogleCallback(): JsonResponse
    {
        $result = $this->authService->handleGoogleCallback();
        
        if (isset($result['error'])) {
            return $this->errorResponse('Error al autenticar con Google: ' . $result['message'], 500);
        }
        
        return $this->successResponse([
            'user' => new UserResource($result['user']),
            'roles' => $result['roles'],
            'permissions' => $result['permissions'],
            'administra_emprendimientos' => $result['administra_emprendimientos'],
            'access_token' => $result['access_token'],
            'token_type' => $result['token_type'],
            'email_verified' => $result['email_verified'],
        ], 'Inicio de sesión con Google exitoso');
    }
    
    /**
     * Get authenticated user profile
     * 
     * @return JsonResponse
     */
    public function profile(): JsonResponse
    {
        $user = Auth::user();
        $user->load('emprendimientos.asociacion');
        
        return $this->successResponse([
            'user' => new UserResource($user),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'administra_emprendimientos' => $user->administraEmprendimientos(),
            'emprendimientos' => $user->emprendimientos,
            'email_verified' => $user->hasVerifiedEmail(),
        ]);
    }
    
    /**
     * Update user profile
     * 
     * @param UpdateProfileRequest $request
     * @return JsonResponse
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = $this->authService->updateProfile(
                Auth::user(),
                $request->validated(),
                $request->hasFile('foto_perfil') ? $request->file('foto_perfil') : null
            );
            
            $emailChanged = $request->has('email') && $request->email !== Auth::user()->getOriginal('email');
            
            return $this->successResponse(
                new UserResource($user),
                $emailChanged 
                    ? 'Perfil actualizado correctamente. Se ha enviado un correo de verificación a su nueva dirección de correo.'
                    : 'Perfil actualizado correctamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error al actualizar el perfil: ' . $e->getMessage(),
                500
            );
        }
    }
    
    /**
     * User logout
     * 
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        Auth::user()->currentAccessToken()->delete();
        
        return $this->successResponse(null, 'Sesión cerrada correctamente');
    }
    
    /**
     * Verify email
     * 
     * @param VerifyEmailRequest $request
     * @return JsonResponse
     */
    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        $verified = $this->authService->verifyEmail(
            $request->id,
            $request->hash
        );
        
        if (!$verified) {
            return $this->errorResponse('El enlace de verificación no es válido', 400);
        }
        
        return $this->successResponse(null, 'Correo electrónico verificado correctamente');
    }
    
    /**
     * Resend verification email
     * 
     * @return JsonResponse
     */
    public function resendVerificationEmail(): JsonResponse
    {
        $user = Auth::user();
        
        if ($user->hasVerifiedEmail()) {
            return $this->errorResponse('El correo electrónico ya ha sido verificado', 400);
        }
        
        try {
            $this->authService->resendVerificationEmail($user);
            return $this->successResponse(null, 'Se ha enviado un nuevo correo de verificación');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al enviar el correo de verificación: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Send password reset link
     * 
     * @param ForgotPasswordRequest $request
     * @return JsonResponse
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = $this->authService->sendPasswordResetLink($request->email);
        
        if ($status === Password::RESET_LINK_SENT) {
            return $this->successResponse(null, 'Se ha enviado un enlace de recuperación a su correo electrónico');
        }
        
        return $this->errorResponse('No se pudo enviar el correo de recuperación', 500);
    }
    
    /**
     * Reset password
     * 
     * @param ResetPasswordRequest $request
     * @return JsonResponse
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = $this->authService->resetPassword($request->only('email', 'password', 'password_confirmation', 'token'));
        
        if ($status === Password::PASSWORD_RESET) {
            return $this->successResponse(null, 'Contraseña actualizada correctamente');
        }
        
        return $this->errorResponse('No se pudo actualizar la contraseña', 500);
    }
}