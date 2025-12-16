<?php
namespace PadelClub\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PadelClub\Models\User;
use PadelClub\Utils\JWTUtils;
use Google_Client;

class AuthController
{
    public function login(Request $request, Response $response)
    {
        $data     = $request->getParsedBody();
        $email    = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        
        if (!$email || !$password) {
            return $this->errorResponse($response, 'Email y password son requeridos');
        }
        
        try {
            // Buscar usuario por email
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                return $this->errorResponse($response, 'Credenciales incorrectas', 401);
            }
            
            // Verificar si el usuario está activo
            if (!$user->is_active) {
                return $this->errorResponse($response, 'Cuenta desactivada', 401);
            }
            
            // Verificar password
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
                    'full_name'   => $user->full_name,
                    'nombre'      => $user->nombre,
                    'apellidos'   => $user->apellidos,
                    'image_path'  => $user->image_path,
                    'nivel'       => $user->nivel,
                    'genero'      => $user->genero,
                    'categoria'   => $user->categoria,
                    'fiabilidad'  => $user->fiabilidad,
                    'asistencias' => $user->asistencias,
                    'ausencias'   => $user->ausencias
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error en el login: ' . $e->getMessage());
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
                    'ausencias'   => $user->ausencias
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al validar el token: ' . $e->getMessage(), 401);
        }
    }
    
    public function register(Request $request, Response $response)
    {
        $data = $request->getParsedBody();
        
        // Validar datos requeridos
        $required = ['email', 'username', 'password']; // ← Agregar password
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->errorResponse($response, "El campo $field es requerido");
            }
        }
        
        // Verificar si el usuario ya existe
        if (User::where('email', $data['email'])->exists()) {
            return $this->errorResponse($response, 'El usuario ya existe');
        }
        
        try {
            $user = User::create([
                'email' => $data['email'],
                'name' => $data['full_name'],
                'password' => $data['password'], // ← Guardar password
                'phone' => $data['phone'] ?? null,
                'level' => $data['level'] ?? 'principiante',
                'is_active' => true
            ]);
            
            $jwtToken = JWTUtils::generateToken($user->id, $user->email);
            
            return $this->successResponse($response, [
                'token' => $jwtToken,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'level' => $user->level,
                    'phone' => $user->phone
                ]
            ], 201);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error en el registro: ' . $e->getMessage());
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
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'avatar' => $user->avatar,
                'level' => $user->level,
                'phone' => $user->phone
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