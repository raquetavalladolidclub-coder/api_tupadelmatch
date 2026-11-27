<?php
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Psr7\Request; // ← Agregar esta línea
use Slim\Psr7\Response; // ← Agregar esta línea
use Dotenv\Dotenv;

// Cargar variables de entorno
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Inicializar JWT
PadelClub\Utils\JWTUtils::init();

// Configurar base de datos
require __DIR__ . '/../config/database.php';

// Crear aplicación Slim
$app = AppFactory::create();

// Middleware para parsing JSON
$app->addBodyParsingMiddleware();

// Manejo de errores: 404/500 en JSON y sin trazas al cliente.
// Las trazas solo se muestran si APP_DEBUG está activo (nunca en producción).
$displayErrorDetails = (bool) filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, true);
$errorMiddleware->setDefaultErrorHandler(function ($request, Throwable $exception) use ($app) {
    $status = (int) $exception->getCode();
    if ($status < 400 || $status > 599) {
        $status = 500;
    }

    $payload = [
        'success' => false,
        'message' => $status === 404 ? 'Recurso no encontrado' : 'Error interno del servidor',
    ];

    $response = $app->getResponseFactory()->createResponse($status);
    $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
});

// Middleware CORS
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

// Middleware de logging (opcional)
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    error_log("[" . date('Y-m-d H:i:s') . "] " . $request->getMethod() . " " . $request->getUri()->getPath());
    return $response;
});

// Cargar rutas
$routes = require __DIR__ . '/../src/routes.php';
$routes($app);

// Ejecutar aplicación
$app->run();