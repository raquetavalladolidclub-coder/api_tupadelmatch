<?php
namespace PadelClub\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SystemController
{
    private $appConfig;

    public function __construct()
    {
        $this->appConfig = require __DIR__ . '/../../config/app.php';
    }

    public function getVersion(Request $request, Response $response)
    {
        $versionInfo = [
            'api' => [
                'name' => $this->appConfig['app']['name'],
                'version' => $this->appConfig['app']['version'],
                'environment' => $this->appConfig['app']['environment'],
                'url' => $this->appConfig['app']['url']
            ],
            'timestamp' => date('Y-m-d H:i:s'),
            'features' => $this->appConfig['features'],
            'limits' => $this->appConfig['limits'],
            'endpoints' => [
                'auth' => [
                    'POST /auth/google',
                    'POST /auth/register',
                    'GET /auth/profile',
                    'PUT /auth/profile'
                ],
                'matches' => [
                    'GET /matches',
                    'POST /matches',
                    'GET /matches/{id}',
                    'PUT /matches/{id}',
                    'DELETE /matches/{id}'
                ],
                'system' => [
                    'GET /version',
                    'GET /health',
                    'GET /'
                ]
            ],
            'database' => [
                'status' => $this->checkDatabaseConnection() ? 'connected' : 'disconnected'
            ]
        ];

        return $this->successResponse($response, $versionInfo);
    }

    public function healthCheck(Request $request, Response $response)
    {
        $dbStatus = $this->checkDatabaseConnection();
        
        $healthStatus = [
            'status' => $dbStatus ? 'healthy' : 'degraded',
            'timestamp' => date('Y-m-d H:i:s'),
            'services' => [
                'database' => $dbStatus ? 'healthy' : 'unhealthy',
                'api' => 'healthy',
                'authentication' => 'healthy',
                'storage' => 'healthy'
            ],
            'version' => $this->appConfig['app']['version'],
            'environment' => $this->appConfig['app']['environment'],
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            'php_version' => PHP_VERSION,
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
        ];

        return $this->successResponse($response, $healthStatus);
    }

    private function checkDatabaseConnection()
    {
        try {
            \Illuminate\Database\Capsule\Manager::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function successResponse(Response $response, $data, $statusCode = 200)
    {
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $data
        ]));
        
        return $response->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json');
    }
}