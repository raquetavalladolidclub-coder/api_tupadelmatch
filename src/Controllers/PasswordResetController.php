<?php
// app/Controllers/PasswordResetController.php

namespace PadelClub\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PadelClub\Models\User;
use PadelClub\Models\PasswordResetToken;
use PadelClub\Services\NotificationService;
use PadelClub\Utils\PasswordHelper;
use Illuminate\Support\Str;

class PasswordResetController
{
    private $notificationService;
    
    public function __construct()
    {
        $this->notificationService = new NotificationService();
    }
    
    /**
     * Solicitar recuperación de contraseña
     * POST /auth/forgot-password
     */
    public function requestReset(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;
        
        if (!$email) {
            return $this->errorResponse($response, 'El email es requerido');
        }
        
        try {
            $user = User::where('email', $email)->first();
            
            // Siempre devolvemos éxito por seguridad
            if (!$user) {
                return $this->successResponse($response, [
                    'message' => 'Si el email existe, recibirás instrucciones para restablecer tu contraseña'
                ]);
            }
            
            // Invalidar tokens anteriores
            PasswordResetToken::where('user_id', $user->id)
                ->update(['is_used' => true]);
            
            // Generar nuevo token
            $token = Str::random(64);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Guardar token
            PasswordResetToken::create([
                'user_id' => $user->id,
                'token' => $token,
                'expires_at' => $expiresAt,
                'is_used' => false
            ]);
            
            // Generar enlace
            $resetLink = $this->generateResetLink($token);
            
            // Enviar email
            $this->notificationService->sendPasswordResetEmail($user, $resetLink);
            
            return $this->successResponse($response, [
                'message' => 'Si el email existe, recibirás instrucciones para restablecer tu contraseña'
            ]);
            
        } catch (\Exception $e) {
            error_log('Error en requestReset: ' . $e->getMessage());
            return $this->errorResponse($response, 'Error al procesar la solicitud');
        }
    }
    
    /**
     * Validar token de recuperación
     * POST /auth/validate-token
     */
    public function validateToken(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $token = $data['token'] ?? null;
        
        if (!$token) {
            return $this->errorResponse($response, 'Token requerido');
        }
        
        try {
            $resetToken = PasswordResetToken::where('token', $token)
                ->where('expires_at', '>', date('Y-m-d H:i:s'))
                ->where('is_used', false)
                ->with('user')
                ->first();
            
            if (!$resetToken) {
                return $this->errorResponse($response, 'Token inválido o expirado');
            }
            
            return $this->successResponse($response, [
                'valid' => true,
                'email' => $resetToken->user->email,
                'expires_at' => $resetToken->expires_at
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al validar el token');
        }
    }
    
    /**
     * Restablecer contraseña con token
     * POST /auth/reset-password
     */
    public function resetPassword(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $token = $data['token'] ?? null;
        $newPassword = $data['password'] ?? null;
        $confirmPassword = $data['confirm_password'] ?? null;
        
        // Validaciones
        if (!$token) {
            return $this->errorResponse($response, 'Token requerido');
        }
        
        if (!$newPassword || !$confirmPassword) {
            return $this->errorResponse($response, 'La nueva contraseña y confirmación son requeridas');
        }
        
        if ($newPassword !== $confirmPassword) {
            return $this->errorResponse($response, 'Las contraseñas no coinciden');
        }
        
        if (strlen($newPassword) < 6) {
            return $this->errorResponse($response, 'La contraseña debe tener al menos 6 caracteres');
        }
        
        try {
            // Buscar token válido
            $resetToken = PasswordResetToken::where('token', $token)
                ->where('expires_at', '>', date('Y-m-d H:i:s'))
                ->where('is_used', false)
                ->with('user')
                ->first();
            
            if (!$resetToken) {
                return $this->errorResponse($response, 'Token inválido o expirado');
            }
            
            $user = $resetToken->user;
            
            // Verificar que la nueva contraseña sea diferente
            if ($user->verifyPassword($newPassword)) {
                return $this->errorResponse($response, 'La nueva contraseña debe ser diferente a la actual');
            }
            
            // Actualizar contraseña
            $user->password = $newPassword; // El mutator hará el hash
            $user->save();
            
            // Marcar token como usado
            $resetToken->is_used = true;
            $resetToken->save();
            
            // Invalidar otros tokens del usuario
            PasswordResetToken::where('user_id', $user->id)
                ->where('is_used', false)
                ->update(['is_used' => true]);
            
            // Enviar email de confirmación
            $this->notificationService->sendPasswordChangedConfirmation($user);
            
            return $this->successResponse($response, [
                'message' => 'Contraseña restablecida exitosamente. Ya puedes iniciar sesión.'
            ]);
            
        } catch (\Exception $e) {
            error_log('Error en resetPassword: ' . $e->getMessage());
            return $this->errorResponse($response, 'Error al restablecer la contraseña');
        }
    }
    
    /**
     * Enviar nueva contraseña generada automáticamente
     * POST /auth/send-new-password
     */
    public function sendNewPassword(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;
        
        if (!$email) {
            return $this->errorResponse($response, 'El email es requerido');
        }
        
        try {
            $user = User::where('email', $email)->first();
            
            // Por seguridad, siempre devolvemos éxito
            if (!$user) {
                return $this->successResponse($response, [
                    'message' => 'Si el email existe, recibirás una nueva contraseña por email'
                ]);
            }
            
            // Generar contraseña aleatoria
            $newPassword = Str::random(12);
            
            // Actualizar contraseña
            $user->password = $newPassword; // El mutator hará el hash
            $user->save();
            
            // Enviar email con nueva contraseña
            $this->notificationService->sendNewPasswordEmail($user, $newPassword);
            
            // Invalidar tokens existentes
            PasswordResetToken::where('user_id', $user->id)
                ->update(['is_used' => true]);
            
            return $this->successResponse($response, [
                'message' => 'Si el email existe, recibirás una nueva contraseña por email'
            ]);
            
        } catch (\Exception $e) {
            error_log('Error en sendNewPassword: ' . $e->getMessage());
            return $this->errorResponse($response, 'Error al procesar la solicitud');
        }
    }
    
    /**
     * Generar enlace de recuperación
     */
    private function generateResetLink(string $token): string
    {
        // Configurar en .env
        $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'https://tudominio.com';
        return $frontendUrl . '/reset-password?token=' . $token;
    }
    
    private function successResponse(Response $response, $data, $statusCode = 200): Response
    {
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $data
        ]));
        
        return $response->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json');
    }
    
    private function errorResponse(Response $response, $message, $statusCode = 400): Response
    {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message
        ]));
        
        return $response->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json');
    }
}