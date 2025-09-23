<?php
use Slim\App;
use PadelClub\Controllers\AuthController;
use PadelClub\Controllers\SystemController;
use PadelClub\Controllers\PartidoController;
use PadelClub\Middleware\AuthMiddleware;

return function (App $app) {
    // Rutas del sistema (públicas)
    $app->get('/version', [SystemController::class, 'getVersion']);
    $app->get('/health', [SystemController::class, 'healthCheck']);
    
    // Rutas de autenticación (públicas)
    $app->post('/auth/google', [AuthController::class, 'loginWithGoogle']);
    $app->post('/auth/register', [AuthController::class, 'register']);
    
    // Rutas protegidas - Partidos
    $app->get('/partidos', [PartidoController::class, 'listarPartidos'])
        ->add(new AuthMiddleware());
        
    $app->get('/partidos/{id}', [PartidoController::class, 'obtenerPartido'])
        ->add(new AuthMiddleware());
        
    $app->post('/partidos', [PartidoController::class, 'crearPartido'])
        ->add(new AuthMiddleware());
        
    $app->post('/partidos/{id}/inscribirse', [PartidoController::class, 'inscribirsePartido'])
        ->add(new AuthMiddleware());
        
    $app->delete('/partidos/{id}/inscripcion', [PartidoController::class, 'cancelarInscripcion'])
        ->add(new AuthMiddleware());
        
    $app->get('/mis-inscripciones', [PartidoController::class, 'misInscripciones'])
        ->add(new AuthMiddleware());
    
    // Rutas de perfil (protegidas)
    $app->get('/auth/profile', [AuthController::class, 'getProfile'])
        ->add(new AuthMiddleware());
        
    $app->put('/auth/profile', [AuthController::class, 'updateProfile'])
        ->add(new AuthMiddleware());
        
    // Ruta de bienvenida
    $app->get('/', function ($request, $response) {
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
                ],
                'partidos' => [
                    'GET /partidos' => 'Listar partidos (protegido)',
                    'GET /partidos/{id}' => 'Obtener partido (protegido)',
                    'POST /partidos' => 'Crear partido (protegido)',
                    'POST /partidos/{id}/inscribirse' => 'Inscribirse a partido (protegido)',
                    'DELETE /partidos/{id}/inscripcion' => 'Cancelar inscripción (protegido)',
                    'GET /mis-inscripciones' => 'Mis inscripciones (protegido)'
                ]
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    });
};