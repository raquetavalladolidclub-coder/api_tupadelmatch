<?php
    require __DIR__ . '/vendor/autoload.php';

    use Slim\Factory\AppFactory;
    use Dotenv\Dotenv;

    // Cargar variables de entorno
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    $app = AppFactory::create();

    // Middleware CORS
    $app->add(new CorsMiddleware());

    // Parse JSON body
    $app->addBodyParsingMiddleware();

    // Routes
    $authRoutes = require __DIR__ . '/routes/auth.php';
    $authRoutes($app);

    // Ruta de bienvenida
    $app->get('/', function ($request, $response) {
        $response->getBody()->write(json_encode([
            'message' => 'API Padel Club - Bienvenido',
            'version' => '1.0'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->run();
?>