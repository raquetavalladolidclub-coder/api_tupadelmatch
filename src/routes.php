<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use PadelClub\Controllers\AuthController;
use PadelClub\Controllers\SystemController;
use PadelClub\Middleware\AuthMiddleware;

return function (App $app) {
    // Rutas del sistema (públicas)
    $app->get('/version', [SystemController::class, 'getVersion']);
    $app->get('/health', [SystemController::class, 'healthCheck']);
    
    // Rutas de autenticación (públicas)
    $app->post('/auth/google', [AuthController::class, 'loginWithGoogle']);
    $app->post('/auth/register', [AuthController::class, 'register']);
    
    // Rutas protegidas
    $app->get('/auth/profile', [AuthController::class, 'getProfile'])
        ->add(new AuthMiddleware());
        
    $app->put('/auth/profile', [AuthController::class, 'updateProfile'])
        ->add(new AuthMiddleware());
        
    // Ruta de bienvenida
    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write(json_encode([
            'message' => 'Bienvenido a la API del Club de Pádel',
            'version' => '1.0.0',
            'endpoints' => [
                'system' => [
                    'GET /version' => 'Información de la versión',
                    'GET /health' => 'Estado del sistema'
                ],
                'auth' => [
                    'POST /auth/google' => 'Login con Google',
                    'POST /auth/register' => 'Registro tradicional',
                    'GET /auth/profile' => 'Obtener perfil (protegido)',
                    'PUT /auth/profile' => 'Actualizar perfil (protegido)'
                ]
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    });
};