<?php
    require_once __DIR__ . '/../models/User.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../vendor/autoload.php';

    use Firebase\JWT\JWT;
    use Firebase\JWT\Key;

    class AuthController {
        private $db;
        private $user;

        public function __construct() {
            $this->db = (new Database())->getConnection();
            $this->user = new User($this->db);
        }

        public function loginWithGoogle($request, $response) {
            $data = json_decode($request->getBody(), true);

            if (!isset($data['google_token'])) {
                return $this->errorResponse($response, 'Token de Google requerido');
            }

            // Verificar token de Google (simplificado - en producción usar Google API)
            $googleUser = $this->verifyGoogleToken($data['google_token']);
            
            if (!$googleUser) {
                return $this->errorResponse($response, 'Token de Google inválido');
            }

            // Buscar usuario por Google ID
            if (!$this->user->findByGoogleId($googleUser['sub'])) {
                // Si no existe, crear nuevo usuario
                $this->user->google_id = $googleUser['sub'];
                $this->user->email = $googleUser['email'];
                $this->user->name = $googleUser['name'];
                $this->user->avatar = $googleUser['picture'];
                $this->user->phone = $data['phone'] ?? '';
                $this->user->level = $data['level'] ?? 'principiante';

                if (!$this->user->create()) {
                    return $this->errorResponse($response, 'Error al crear usuario');
                }
            }

            // Generar JWT
            $token = $this->generateJWT($this->user->id);

            return $this->successResponse($response, [
                'token' => $token,
                'user' => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'avatar' => $this->user->avatar,
                    'phone' => $this->user->phone,
                    'level' => $this->user->level
                ]
            ]);
        }

        public function verifyToken($request, $response) {
            $userId = $request->getAttribute('user_id');
            
            if ($this->user->findByGoogleId($userId)) {
                return $this->successResponse($response, [
                    'user' => [
                        'id' => $this->user->id,
                        'name' => $this->user->name,
                        'email' => $this->user->email,
                        'avatar' => $this->user->avatar,
                        'phone' => $this->user->phone,
                        'level' => $this->user->level
                    ]
                ]);
            }

            return $this->errorResponse($response, 'Usuario no encontrado');
        }

        private function verifyGoogleToken($token) {
            // En producción, implementar verificación real con Google API
            // Por ahora, asumimos que el token es válido y decodificamos
            try {
                $parts = explode('.', $token);
                if (count($parts) !== 3) {
                    return false;
                }
                
                $payload = base64_decode($parts[1]);
                return json_decode($payload, true);
            } catch (Exception $e) {
                return false;
            }
        }

        private function generateJWT($userId) {
            $payload = [
                'iss' => 'padel-club-api',
                'aud' => 'padel-club-app',
                'iat' => time(),
                'exp' => time() + (int)$_ENV['JWT_EXPIRE'],
                'user_id' => $userId
            ];

            return JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
        }

        private function successResponse($response, $data) {
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $data
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        private function errorResponse($response, $message, $status = 400) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $message
            ]));
            return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
        }
    }
?>