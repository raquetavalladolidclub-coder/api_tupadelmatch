<?php
    use Slim\Routing\RouteCollectorProxy;

    require_once __DIR__ . '/../controllers/AuthController.php';

    return function ($app) {
        $app->group('/auth', function (RouteCollectorProxy $group) {
            $authController = new AuthController();
            
            $group->post('/google', [$authController, 'loginWithGoogle']);
            $group->get('/verify', [$authController, 'verifyToken']);
        });
    };
?>