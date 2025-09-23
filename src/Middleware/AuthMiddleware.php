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
        // Debug: mostrar headers recibidos
        $headers = $request->getHeaders();
        error_log("Headers recibidos: " . print_r($headers, true));
        
        $token = $this->extractToken($request);
        error_log("Token extraído: " . ($token ? 'SI' : 'NO'));
        
        if (!$token) {
            error_log("ERROR: Token no proporcionado");
            return $this->unauthorizedResponse('Token no proporcionado');
        }
        
        error_log("Token: " . substr($token, 0, 20) . "...");
        
        $userData = JWTUtils::validateToken($token);
        
        if (!$userData) {
            error_log("ERROR: Token inválido");
            return $this->unauthorizedResponse('Token inválido o expirado');
        }
        
        error_log("Token válido para usuario: " . $userData['email']);
        
        // Agregar datos del usuario a la request
        $request = $request->withAttribute('user_id', $userData['sub']);
        $request = $request->withAttribute('user_email', $userData['email']);
        
        return $handler->handle($request);
    }
    
    private function extractToken($request)
    {
        $header = $request->getHeaderLine('Authorization');
        error_log("Header Authorization: " . $header);
        
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