<?php
use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PadelClub\Controllers\AuthController;
use PadelClub\Controllers\SystemController;
use PadelClub\Controllers\PartidoController;
use PadelClub\Controllers\LigaController;
use PadelClub\Controllers\SurveyController;
use PadelClub\Middleware\AuthMiddleware;

return function (App $app) {
    // Rutas del sistema (públicas)
    $app->get('/version', [SystemController::class, 'getVersion']);
    $app->get('/health', [SystemController::class, 'healthCheck']);
    
    // Endpoint de debug temporal
    $app->get('/debug-headers', function (Request $request, Response $response) {
        $headers = $request->getHeaders();
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'headers' => $headers,
            'authorization_header' => $request->getHeaderLine('Authorization'),
            'server_vars' => [
                'HTTP_AUTHORIZATION' => $_SERVER['HTTP_AUTHORIZATION'] ?? 'No definido',
                'REDIRECT_HTTP_AUTHORIZATION' => $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 'No definido'
            ]
        ], JSON_PRETTY_PRINT));
        
        return $response->withHeader('Content-Type', 'application/json');
    });
    
    // Rutas de autenticación (públicas)
    $app->post('/auth/google', [AuthController::class, 'loginWithGoogle']);
    $app->post('/auth/register', [AuthController::class, 'register']);
    $app->post('/auth/login', [AuthController::class, 'login']);
    
    // Rutas protegidas - Partidos
    $app->get('/partidos', [PartidoController::class, 'listarPartidos'])->add(new AuthMiddleware());
    $app->get('/misProximosPartidos', [PartidoController::class, 'listarPartidosProximos'])->add(new AuthMiddleware());
        
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
    $app->get('/auth/user', [AuthController::class, 'validateToken'])->add(new AuthMiddleware());
    $app->get('/auth/profile', [AuthController::class, 'getProfile'])->add(new AuthMiddleware());
        
    $app->put('/auth/profile', [AuthController::class, 'updateProfile'])->add(new AuthMiddleware());
    $app->put('/auth/encuesta', [AuthController::class, 'updateEncuesta'])->add(new AuthMiddleware());
    $app->delete('/auth/account', [AuthController::class, 'deleteAccount'])->add(new AuthMiddleware());
        
    // Ruta de bienvenida (CORREGIDA)
    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write(json_encode([
            'message' => 'Bienvenido a la API del Club de Pádel',
            'version' => '1.0.0',
            'endpoints' => [
                // ... endpoints existentes ...
                'survey' => [
                    'POST /api/leveling-survey' => 'Enviar encuesta de nivelación (protegido)',
                    'GET /api/leveling-survey' => 'Obtener encuesta del usuario (protegido)',
                    'POST /api/update-user-level' => 'Actualizar nivel del usuario (protegido)',
                    'GET /api/leveling-stats' => 'Obtener estadísticas de nivelación (protegido)'
                ]
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Resultados de partidos
    $app->post('/partidosLiga/{id}/resultados', [LigaController::class, 'guardarResultados'])->add(new AuthMiddleware());
    $app->get('/partidosLiga/pendientes-resultados', [LigaController::class, 'obtenerPartidosPendientesResultados'])->add(new AuthMiddleware());
    
    // Ranking y estadísticas
    $app->get('/ligas/{codLiga}/ranking', [LigaController::class, 'obtenerRankingLiga'])->add(new AuthMiddleware());
    $app->get('/ligas/{codLiga}/estadisticas[/{usuarioId}]', [LigaController::class, 'obtenerEstadisticasJugador'])->add(new AuthMiddleware());
    $app->get('/ligas/{codLiga}/ultimos-partidos', [LigaController::class, 'obtenerUltimosPartidosLiga'])->add(new AuthMiddleware());

    // Rutas de encuesta de nivelación
    $app->post('/api/leveling-survey', [SurveyController::class, 'submitSurvey'])->add(new AuthMiddleware());
    $app->get('/api/leveling-survey', [SurveyController::class, 'getUserSurvey'])->add(new AuthMiddleware());
    $app->post('/api/update-user-level', [SurveyController::class, 'updateUserLevel'])->add(new AuthMiddleware());
    $app->get('/api/leveling-stats', [SurveyController::class, 'getLevelingStats'])->add(new AuthMiddleware());

    $app->put('/auth/update', [AuthController::class, 'updateUserField'])->add(new AuthMiddleware());

    // Rutas de perfil y configuración
    $app->put('/auth/profile', [AuthController::class, 'updateProfile'])->add(new AuthMiddleware());
    $app->put('/auth/info', [AuthController::class, 'updateUserInfo'])->add(new AuthMiddleware());
    $app->put('/auth/password', [AuthController::class, 'changePassword'])->add(new AuthMiddleware());
    $app->post('/usauther/upload-image', [AuthController::class, 'uploadProfileImage'])->add(new AuthMiddleware());

    // Ruta para actualizar campo específico (que ya tienes)
    $app->put('/auth/field', [AuthController::class, 'updateUserField'])->add(new AuthMiddleware());
};