<?php
    require_once __DIR__ . '/../vendor/autoload.php';
    use Firebase\JWT\JWT;
    use Firebase\JWT\Key;

    class AuthMiddleware {
        public function __invoke($request, $handler) {
            $token = $this->getTokenFromHeader($request);
            
            if (!$token) {
                return $this->unauthorizedResponse('Token no proporcionado');
            }

            try {
                $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
                $request = $request->withAttribute('user_id', $decoded->user_id);
                return $handler->handle($request);
            } catch (Exception $e) {
                return $this->unauthorizedResponse('Token inválido');
            }
        }

        private function getTokenFromHeader($request) {
            $header = $request->getHeaderLine('Authorization');
            if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
                return $matches[1];
            }
            return null;
        }

        private function unauthorizedResponse($message) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $message
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }
    }
?>