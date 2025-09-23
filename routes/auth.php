<?php
    use Slim\Routing\RouteCollectorProxy;

    require_once __DIR__ . '/../controllers/AuthController.php';

    return function ($app) {
        // Ruta de bienvenida
        $app->get('/', function ($request, $response) {
            $response->getBody()->write(json_encode([
                'message' => 'API Padel Club - Bienvenido',
                'version' => '1.0',
                'endpoints' => [
                    'GET /' => 'Información de la API',
                    'GET /test/status' => 'Estado del sistema',
                    'POST /test/google-token' => 'Test de tokens Google',
                    'POST /auth/google' => 'Login con Google',
                    'GET /auth/verify' => 'Verificar token JWT',
                    'GET /test/protected' => 'Ruta protegida de test (requiere JWT)'
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $app->group('/auth', function (RouteCollectorProxy $group) {
            $authController = new AuthController();
            
            $group->post('/google', [$authController, 'loginWithGoogle']);
            $group->get('/verify', [$authController, 'verifyToken']);
        });
    };
?>