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
        // Debug completo de TODOS los headers
        $allHeaders = $request->getHeaders();
        error_log("=== DEBUG AUTH MIDDLEWARE ===");
        error_log("Método: " . $request->getMethod());
        error_log("URL: " . $request->getUri()->getPath());
        error_log("Todos los headers recibidos:");
        foreach ($allHeaders as $name => $values) {
            error_log("  $name: " . implode(', ', $values));
        }
        
        $token = $this->extractToken($request);
        error_log("Token extraído: " . ($token ? 'SI (' . substr($token, 0, 20) . '...)' : 'NO'));
        
        if (!$token) {
            error_log("ERROR: No se pudo extraer token");
            return $this->unauthorizedResponse('Token no proporcionado');
        }
        
        $userData = JWTUtils::validateToken($token);
        
        if (!$userData) {
            error_log("ERROR: Token inválido o expirado");
            return $this->unauthorizedResponse('Token inválido o expirado');
        }
        
        error_log("Token válido para usuario ID: " . $userData['sub']);
        
        $request = $request->withAttribute('user_id', $userData['sub']);
        $request = $request->withAttribute('user_email', $userData['email']);
        
        return $handler->handle($request);
    }
    
    private function extractToken($request)
    {
        // 1. Intentar desde header Authorization
        $header = $request->getHeaderLine('Authorization');
        error_log("Header Authorization: '" . $header . "'");
        
        if (!empty($header) && preg_match('/Bearer\s+(.+)$/i', $header, $matches)) {
            return trim($matches[1]);
        }
        
        // 2. Intentar desde query parameter (temporal para debug)
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['token'])) {
            error_log("Token obtenido desde query parameter");
            return $queryParams['token'];
        }
        
        // 3. Intentar desde header personalizado
        $customHeader = $request->getHeaderLine('X-Auth-Token');
        if (!empty($customHeader)) {
            error_log("Token obtenido desde X-Auth-Token: " . $customHeader);
            return $customHeader;
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