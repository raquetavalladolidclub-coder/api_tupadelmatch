<?php
namespace PadelClub\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PadelClub\Models\User;
use PadelClub\Utils\JWTUtils;
use PadelClub\Services\NotificationService;
use Google_Client;

class AuthController
{
    private $notificationService;

    public function __construct()
    {
        $this->notificationService = new NotificationService();
    }

    public function login(Request $request, Response $response)
    {
        $data = $request->getParsedBody();
        
        // Aceptar email o username
        $login = $data['email'] ?? $data['username'] ?? null;
        $password = $data['password'] ?? null;
        
        if (!$login || !$password) {
            return $this->errorResponse($response, 'Email/Usuario y password son requeridos');
        }
        
        try {
            // Buscar usuario por email o username
            $user = User::where('email', $login)
                        ->orWhere('username', $login)
                        ->first();
            
            if (!$user) {
                return $this->errorResponse($response, 'Credenciales incorrectas', 401);
            }
            
            if (!$user->is_active) {
                return $this->errorResponse($response, 'Cuenta desactivada', 401);
            }
            
            // Verificar contraseña usando el método del modelo
            if (!$user->verifyPassword($password)) {
                return $this->errorResponse($response, 'Credenciales incorrectas', 401);
            }
            
            // Generar JWT
            $jwtToken = JWTUtils::generateToken($user->id, $user->email);
            
            return $this->successResponse($response, [
                'token' => $jwtToken,
                'user' => [
                    'id'          => $user->id,
                    'email'       => $user->email,
                    'username'    => $user->username ?? null,
                    'full_name'   => $user->full_name,
                    'nombre'      => $user->nombre,
                    'apellidos'   => $user->apellidos,
                    'image_path'  => $user->image_path,
                    'nivel'       => $user->nivel,
                    'genero'      => $user->genero,
                    'categoria'   => $user->categoria,
                    'fiabilidad'  => $user->fiabilidad,
                    'asistencias' => $user->asistencias,
                    'ausencias'   => $user->ausencias,
                    'codLiga'     => $user->codLiga,
                    'encuesta'    => $user->encuesta
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error en el login');
        }
    }

    public function register(Request $request, Response $response)
    {
        $data = $request->getParsedBody();

        // Campos requeridos
        $required = ['email', 'username', 'password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->errorResponse($response, "El campo $field es requerido");
            }
        }

        // Validaciones previas
        if (User::where('username', $data['username'])->exists()) {
            return $this->errorResponse($response, 'El username ya está registrado');
        }

        if (User::where('email', $data['email'])->exists()) {
            return $this->errorResponse($response, 'El email ya está registrado');
        }

        try {
            // Usar PasswordHelper para hash consistente
            $hashedPassword = \PadelClub\Utils\PasswordHelper::hash($data['password']);
            
            $user = User::create([
                'username'  => $data['username'],
                'email'     => $data['email'],
                'full_name' => $data['full_name'] ?? "",
                'nombre'    => $data['nombre'] ?? "",
                'apellidos' => $data['apellidos'] ?? "",
                'password'  => $hashedPassword,
                'categoria' => $data['categoria'] != "" ? $data['categoria'] : 'promesas',
                'codLiga'   => $data['codLiga'] ?? '',
                'is_active' => true
            ]);

            // Generar JWT
            $jwtToken = JWTUtils::generateToken($user->id, $user->email);

            return $this->successResponse($response, [
                'token' => $jwtToken,
                'user' => [
                    'id'          => $user->id,
                    'username'    => $user->username,
                    'email'       => $user->email,
                    'full_name'   => $user->full_name,
                    'nombre'      => $user->nombre,
                    'apellidos'   => $user->apellidos,
                    'categoria'   => $user->categoria,
                    'codLiga'     => $user->codLiga,
                    'is_active'   => $user->is_active
                ]
            ], 201);

        } catch (\PDOException $e) {
            if ($e->errorInfo[1] === 1062) {
                return $this->errorResponse($response, 'Usuario o email ya existente');
            }
            return $this->errorResponse($response, 'Error de base de datos');
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error interno del servidor');
        }
    }

    public function updatePassword(Request $request, Response $response)
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();
        
        $currentPassword = $data['current_password'] ?? null;
        $newPassword = $data['new_password'] ?? null;
        
        if (!$currentPassword || !$newPassword) {
            return $this->errorResponse($response, 'Contraseña actual y nueva son requeridas');
        }
        
        if (strlen($newPassword) < 6) {
            return $this->errorResponse($response, 'La nueva contraseña debe tener al menos 6 caracteres');
        }
        
        try {
            $user = User::find($userId);
            
            if (!$user) {
                return $this->errorResponse($response, 'Usuario no encontrado');
            }
            
            if (!$user->verifyPassword($currentPassword)) {
                return $this->errorResponse($response, 'Contraseña actual incorrecta', 401);
            }
            
            $user->password = $newPassword; // El mutator hará el hash
            $user->save();
            
            return $this->successResponse($response, [
                'message' => 'Contraseña actualizada correctamente'
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al actualizar contraseña');
        }
    }

    /**
     * Enviar email de bienvenida en segundo plano
     */
    private function sendWelcomeEmailAsync(User $user, $plainPassword)
    {
        // Puedes usar diferentes estrategias para enviar en segundo plano:
        // 1. Usar un queue system (Redis, RabbitMQ)
        // 2. Usar procesos en background
        // 3. Para desarrollo, enviar directamente
        
        try {
            $this->notificationService->sendWelcomeEmail($user, $plainPassword);
        } catch (\Exception $e) {
            // Registrar error pero no fallar el registro
            error_log('Error enviando email de bienvenida: ' . $e->getMessage());
        }
    }

    /**
     * Nuevo endpoint para recuperación de contraseña
     */
    public function forgotPassword(Request $request, Response $response)
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;

        if (!$email) {
            return $this->errorResponse($response, 'Email es requerido');
        }

        try {
            $user = User::where('email', $email)->first();

            if (!$user) {
                // Por seguridad, no revelamos si el email existe o no
                return $this->successResponse($response, [
                    'message' => 'Si el email existe, recibirás instrucciones para restablecer tu contraseña'
                ]);
            }

            // Generar token de recuperación
            $resetToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

            // Guardar token en la base de datos (necesitarás crear una tabla para esto)
            // Por ahora, usaremos un campo temporal en el usuario
            $user->password_reset_token = $resetToken;
            $user->password_reset_expires = $expiresAt;
            $user->save();

            // Enviar email de recuperación
            $this->notificationService->sendPasswordResetEmail($user, $resetToken);

            return $this->successResponse($response, [
                'message' => 'Si el email existe, recibirás instrucciones para restablecer tu contraseña'
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al procesar la solicitud');
        }
    }

    /**
     * Verificar cuenta por email
     */
    public function sendVerificationEmail(Request $request, Response $response)
    {
        $userId = $request->getAttribute('user_id');
        
        try {
            $user = User::find($userId);
            
            if (!$user) {
                return $this->errorResponse($response, 'Usuario no encontrado');
            }

            if ($user->email_verified) {
                return $this->errorResponse($response, 'La cuenta ya está verificada');
            }

            // Generar token de verificación
            $verificationToken = bin2hex(random_bytes(32));
            
            // Guardar token (en un sistema real, usarías una tabla separada)
            $user->verification_token = $verificationToken;
            $user->verification_token_expires = date('Y-m-d H:i:s', strtotime('+48 hours'));
            $user->save();

            // Enviar email de verificación
            $this->notificationService->sendAccountVerificationEmail($user, $verificationToken);

            return $this->successResponse($response, [
                'message' => 'Email de verificación enviado'
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al enviar el email de verificación');
        }
    }

    public function loginWithGoogle(Request $request, Response $response)
    {
        $data = $request->getParsedBody();
        $googleToken = $data['google_token'] ?? null;
        
        if (!$googleToken) {
            return $this->errorResponse($response, 'Token de Google requerido');
        }
        
        try {
            // Validar token con Google
            $client = new Google_Client(['client_id' => $_ENV['GOOGLE_CLIENT_ID']]);
            $payload = $client->verifyIdToken($googleToken);
            
            if ($payload) {
                $googleId = $payload['sub'];
                $email = $payload['email'];
                $name = $payload['name'] ?? '';
                $avatar = $payload['picture'] ?? '';
                
                // Buscar o crear usuario
                $user = User::where('google_id', $googleId)->first();
                
                if (!$user) {
                    $user = User::where('email', $email)->first();
                    
                    if ($user) {
                        // Actualizar con Google ID si el email ya existe
                        $user->google_id = $googleId;
                        $user->save();
                    } else {
                        // Crear nuevo usuario
                        $user = User::create([
                            'google_id' => $googleId,
                            'email' => $email,
                            'name' => $name,
                            'avatar' => $avatar,
                            'is_active' => true
                        ]);
                    }
                }
                
                // Generar JWT
                $jwtToken = JWTUtils::generateToken($user->id, $user->email);
                
                return $this->successResponse($response, [
                    'token' => $jwtToken,
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'name' => $user->name,
                        'avatar' => $user->avatar,
                        'level' => $user->level,
                        'phone' => $user->phone
                    ]
                ]);
                
            } else {
                return $this->errorResponse($response, 'Token de Google inválido');
            }
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error en la autenticación: ' . $e->getMessage());
        }
    }

    public function validateToken(Request $request, Response $response)
    {
        $authHeader = $request->getHeader('Authorization');
        
        if (empty($authHeader)) {
            return $this->errorResponse($response, 'Token no proporcionado', 401);
        }
        
        // Extraer el token del header "Bearer {token}"
        $token = str_replace('Bearer ', '', $authHeader[0] ?? '');
        
        if (empty($token)) {
            return $this->errorResponse($response, 'Formato de token inválido', 401);
        }
        
        try {
            // Validar el token usando JWTUtils
            $decoded = JWTUtils::validateToken($token);
            
            if (!$decoded) {
                return $this->errorResponse($response, 'Token inválido o expirado', 401);
            }
            
            // Opcional: Buscar el usuario para verificar que aún existe y está activo
            $user = User::find($decoded["sub"]);
            
            if (!$user) {
                return $this->errorResponse($response, 'Usuario no encontrado', 401);
            }
            
            if (!$user->is_active) {
                return $this->errorResponse($response, 'Cuenta desactivada', 401);
            }
            
            return $this->successResponse($response, [
                'valid' => true,
                'user' => [
                    'id'          => $user->id,
                    'email'       => $user->email,
                    'full_name'   => $user->full_name,
                    'nombre'      => $user->nombre,
                    'apellidos'   => $user->apellidos,
                    'image_path'  => $user->image_path,
                    'nivel'       => $user->nivel,
                    'genero'      => $user->genero,
                    'categoria'   => $user->categoria,
                    'fiabilidad'  => $user->fiabilidad,
                    'asistencias' => $user->asistencias,
                    'ausencias'   => $user->ausencias,
                    'codLiga'     => $user->codLiga
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al validar el token: ' . $e->getMessage(), 401);
        }
    }

    public function getProfile(Request $request, Response $response)
    {
        $userId = $request->getAttribute('user_id');
        
        $user = User::find($userId);
        
        if (!$user) {
            return $this->errorResponse($response, 'Usuario no encontrado');
        }
        
        return $this->successResponse($response, [
            'user' => [
                'id'      => $user->id,
                'email'   => $user->email,
                'name'    => $user->name,
                'avatar'  => $user->avatar,
                'level'   => $user->level,
                'phone'   => $user->phone,
                'codLiga' => $user->codLiga
            ]
        ]);
    }
    
    public function updateProfile(Request $request, Response $response)
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();
        
        $user = User::find($userId);
        
        if (!$user) {
            return $this->errorResponse($response, 'Usuario no encontrado');
        }
        
        try {
            $user->update([
                'name' => $data['name'] ?? $user->name,
                'phone' => $data['phone'] ?? $user->phone,
                'level' => $data['level'] ?? $user->level
            ]);
            
            return $this->successResponse($response, [
                'message' => 'Perfil actualizado correctamente',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'avatar' => $user->avatar,
                    'level' => $user->level,
                    'phone' => $user->phone
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al actualizar el perfil: ' . $e->getMessage());
        }
    }
    
    private function successResponse(Response $response, $data, $statusCode = 200)
    {
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $data
        ]));
        
        return $response->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json');
    }
    
    private function errorResponse(Response $response, $message, $statusCode = 400)
    {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message
        ]));
        
        return $response->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json');
    }
}