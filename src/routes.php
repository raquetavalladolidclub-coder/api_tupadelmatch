<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use PadelClub\Controllers\AuthController;
use PadelClub\Middleware\AuthMiddleware;

return function (App $app) {
    // Rutas públicas
    $app->post('/auth/google', [AuthController::class, 'loginWithGoogle']);
    $app->post('/auth/register', [AuthController::class, 'register']);
    
    // Rutas protegidas
    $app->get('/auth/profile', [AuthController::class, 'getProfile'])
        ->add(new AuthMiddleware());
        
    $app->put('/auth/profile', [AuthController::class, 'updateProfile'])
        ->add(new AuthMiddleware());
};