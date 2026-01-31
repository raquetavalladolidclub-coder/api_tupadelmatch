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
                    'imagePath'  => $user->imagePath,
                    'nivel'       => $user->nivel,
                    'genero'      => $user->genero,
                    'categoria'   => $user->categoria,
                    'fiabilidad'  => $user->fiabilidad,
                    'asistencias' => $user->asistencias,
                    'ausencias'   => $user->ausencias,
                    'codLiga'     => $user->codLiga,
                    'encuesta'    => $user->encuesta,
                    'notificacionesPush'  => $user->notificacionesPush,
                    'notificacionesEmail' => $user->notificacionesEmail
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
                    'imagePath'  => $user->imagePath,
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
        
        // Campos permitidos para actualizar
        $allowedFields = [
            'nombre',
            'apellidos', 
            'phone',
            'genero',
            'categoria',
            'liga',
            'codLiga'
        ];
        
        // Validar campos recibidos
        $updates = [];
        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updates[$field] = $value;
            }
        }
        
        if (empty($updates)) {
            return $this->errorResponse($response, 'No se proporcionaron datos válidos para actualizar');
        }
        
        try {
            // Actualizar campos
            $user->update($updates);
            
            return $this->successResponse($response, [
                'success' => true,
                'message' => 'Perfil actualizado correctamente',
                'user' => [
                    'id' => $user->id,
                    'nombre' => $user->nombre,
                    'apellidos' => $user->apellidos,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'genero' => $user->genero,
                    'categoria' => $user->categoria,
                    'liga' => $user->liga,
                    'codLiga' => $user->codLiga,
                    'imagePath' => $user->imagePath,
                    'nivel_puntuacion' => $user->nivel_puntuacion
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al actualizar el perfil: ' . $e->getMessage());
        }
    }

    public function changePassword(Request $request, Response $response)
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();
        
        // Validar campos requeridos
        if (empty($data['current_password']) || empty($data['new_password']) || empty($data['confirm_password'])) {
            return $this->errorResponse($response, 'Todos los campos son requeridos');
        }
        
        $currentPassword = $data['current_password'];
        $newPassword = $data['new_password'];
        $confirmPassword = $data['confirm_password'];
        
        // Validar que las nuevas contraseñas coincidan
        if ($newPassword !== $confirmPassword) {
            return $this->errorResponse($response, 'Las nuevas contraseñas no coinciden');
        }
        
        // Validar longitud mínima
        if (strlen($newPassword) < 6) {
            return $this->errorResponse($response, 'La nueva contraseña debe tener al menos 6 caracteres');
        }
        
        $user = User::find($userId);
        
        if (!$user) {
            return $this->errorResponse($response, 'Usuario no encontrado');
        }
        
        try {
            // Verificar contraseña actual
            if (!password_verify($currentPassword, $user->password)) {
                return $this->errorResponse($response, 'La contraseña actual es incorrecta');
            }
            
            // Verificar que la nueva contraseña sea diferente
            if (password_verify($newPassword, $user->password)) {
                return $this->errorResponse($response, 'La nueva contraseña debe ser diferente a la actual');
            }
            
            // Hashear nueva contraseña
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Actualizar contraseña
            $user->password = $hashedPassword;
            $user->save();
            
            // Opcional: Invalidar tokens JWT existentes
            // $this->invalidateUserTokens($userId);
            
            return $this->successResponse($response, [
                'success' => true,
                'message' => 'Contraseña cambiada correctamente'
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al cambiar la contraseña: ' . $e->getMessage());
        }
    }

    public function uploadProfileImage(Request $request, Response $response)
    {
        $userId = $request->getAttribute('user_id');
        
        $uploadedFiles = $request->getUploadedFiles();
        
        if (empty($uploadedFiles['profile_image'])) {
            return $this->errorResponse($response, 'No se ha proporcionado ninguna imagen');
        }
        
        $uploadedFile = $uploadedFiles['profile_image'];
        
        // Validar que sea una imagen
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            return $this->errorResponse($response, 'Error al subir la imagen');
        }
        
        // Validar tipo de archivo
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $fileType = $uploadedFile->getClientMediaType();
        
        if (!in_array($fileType, $allowedTypes)) {
            return $this->errorResponse($response, 'Solo se permiten imágenes JPEG, PNG o GIF');
        }
        
        // Validar tamaño (máximo 5MB)
        if ($uploadedFile->getSize() > 5 * 1024 * 1024) {
            return $this->errorResponse($response, 'La imagen no debe superar los 5MB');
        }
        
        $user = User::find($userId);
        
        if (!$user) {
            return $this->errorResponse($response, 'Usuario no encontrado');
        }
        
        try {
            // Generar nombre único para el archivo
            $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
            $filename = 'profile_' . $userId . '_' . time() . '.' . $extension;
            
            // Directorio de uploads (ajusta la ruta según tu estructura)
            $uploadDirectory = __DIR__ . '/../../public/uploads/profiles/';
            
            // Crear directorio si no existe
            if (!is_dir($uploadDirectory)) {
                mkdir($uploadDirectory, 0755, true);
            }
            
            // Mover archivo
            $uploadedFile->moveTo($uploadDirectory . $filename);
            
            // Ruta relativa para guardar en BD
            $imagePath = '/uploads/profiles/' . $filename;
            
            // Actualizar en base de datos
            $user->imagePath = $imagePath;
            $user->save();
            
            return $this->successResponse($response, [
                'success' => true,
                'message' => 'Imagen de perfil actualizada correctamente',
                'data' => [
                    'imagePath' => $imagePath,
                    'full_url' => 'http://' . $_SERVER['HTTP_HOST'] . $imagePath
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al subir la imagen: ' . $e->getMessage());
        }
    }

    public function updateUserInfo(Request $request, Response $response)
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();
        
        $user = User::find($userId);
        
        if (!$user) {
            return $this->errorResponse($response, 'Usuario no encontrado');
        }
        
        try {
            $updates = [];
            $messages = [];
            
            // Actualizar campos básicos si están presentes
            if (isset($data['nombre'])) {
                $user->nombre = $data['nombre'];
                $messages[] = 'Nombre actualizado';
            }
            
            if (isset($data['apellidos'])) {
                $user->apellidos = $data['apellidos'];
                $messages[] = 'Apellidos actualizados';
            }
            
            if (isset($data['phone'])) {
                $user->phone = $data['phone'];
                $messages[] = 'Teléfono actualizado';
            }
            
            if (isset($data['genero']) && in_array($data['genero'], ['masculino', 'femenino'])) {
                $user->genero = $data['genero'];
                $messages[] = 'Género actualizado';
            }
            
            if (isset($data['categoria']) && in_array($data['categoria'], ['promesas', 'cobre', 'bronce', 'plata', 'diamante', 'oro', 'pro'])) {
                $user->categoria = $data['categoria'];
                $messages[] = 'Categoría actualizada';
            }
            
            // Guardar todos los cambios
            $user->save();
            
            return $this->successResponse($response, [
                'success' => true,
                'message' => count($messages) > 0 ? implode(', ', $messages) : 'Datos actualizados',
                'user' => [
                    'id' => $user->id,
                    'nombre' => $user->nombre,
                    'apellidos' => $user->apellidos,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'genero' => $user->genero,
                    'categoria' => $user->categoria,
                    'imagePath' => $user->imagePath,
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al actualizar: ' . $e->getMessage());
        }
    }
    
    public function updateProfileOLD(Request $request, Response $response)
    {
        $userId = $request->getAttribute('user_id');
        $data   = $request->getParsedBody();
        
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

    public function deleteAccount(Request $request, Response $response)
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();
        
        // Validar confirmación
        /*$confirmacion = $data['confirmar'] ?? null;
        
        if ($confirmacion !== 'ELIMINAR') {
            return $this->errorResponse($response, 'Para eliminar la cuenta debes escribir: ELIMINAR');
        }*/
        
        $user = User::find($userId);
        
        if (!$user) {
            return $this->errorResponse($response, 'Usuario no encontrado');
        }
        
        try {
            // Soft delete: marcar como inactivo
            $user->is_active = 0;
            $user->save();
            
            // O si quieres hard delete (eliminar permanentemente):
            // $user->delete();
            
            return $this->successResponse($response, [
                'success' => true,
                'message' => 'Cuenta eliminada correctamente',
                'data' => null
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al eliminar la cuenta: ' . $e->getMessage());
        }
    }

    public function updateEncuesta(Request $request, Response $response)
    {
        $userId = $request->getAttribute('user_id');
        $data   = $request->getParsedBody();
        
        $user = User::find($userId);
        
        if (!$user) {
            return $this->errorResponse($response, 'Usuario no encontrado');
        }
        
        try {
            $user->update(['encuesta' => $data['name'] ?? $user->encuesta]);
            
            return $this->successResponse($response, [
                'message' => 'Perfil actualizado correctamente',
                'user' => [
                    'id'          => $user->id,
                    'email'       => $user->email,
                    'username'    => $user->username ?? null,
                    'full_name'   => $user->full_name,
                    'nombre'      => $user->nombre,
                    'apellidos'   => $user->apellidos,
                    'imagePath'  => $user->imagePath,
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

    public function updateUserField(Request $request, Response $response)
    {
        $userId = $request->getAttribute('user_id');
        $data   = $request->getParsedBody();
        
        // Obtener campo y valor del body
        $field = $data['campo'] ?? null;
        $value = $data['valor'] ?? null;
        
        // Validar que se enviaron ambos parámetros
        if (!$field || $value === null) {
            return $this->errorResponse($response, 'Se requiere "campo" y "valor" en el body');
        }
        
        // Buscar usuario
        $user = User::find($userId);
        if (!$user) {
            return $this->errorResponse($response, 'Usuario no encontrado');
        }
        
        try {
            // Actualizar solo el campo específico
            $user->$field = $value;
            $user->save();
            
            return $this->successResponse($response, [
                'success' => true,
                'message' => 'Campo actualizado correctamente',
                'campo'   => $field,
                'valor_actualizado' => $value
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al actualizar: ' . $e->getMessage());
        }
    }
}