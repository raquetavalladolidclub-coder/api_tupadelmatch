<?php
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
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

// Middleware CORS
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

// Cargar rutas
$routes = require __DIR__ . '/../src/routes.php';
$routes($app);

// Ejecutar aplicación
$app->run();