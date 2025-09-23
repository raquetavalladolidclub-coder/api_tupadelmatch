<?php
namespace PadelClub\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;
use PadelClub\Utils\JWTUtils;

class AuthMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler)
    {
        $token = $this->extractToken($request);
        
        if (!$token) {
            return $this->unauthorizedResponse('Token no proporcionado');
        }
        
        $userData = JWTUtils::validateToken($token);
        
        if (!$userData) {
            return $this->unauthorizedResponse('Token inválido o expirado');
        }
        
        // Agregar datos del usuario a la request
        $request = $request->withAttribute('user_id', $userData['sub']);
        $request = $request->withAttribute('user_email', $userData['email']);
        
        return $handler->handle($request);
    }
    
    private function extractToken($request)
    {
        $header = $request->getHeaderLine('Authorization');
        
        if (empty($header)) {
            return null;
        }
        
        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    private function unauthorizedResponse($message)
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message
        ]));
        
        return $response->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }
}