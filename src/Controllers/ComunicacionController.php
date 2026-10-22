<?php

namespace PadelClub\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PadelClub\Models\Comunicacion;

/**
 * Comunicaciones del club para la app.
 *
 * GET /comunicaciones            -> lista de comunicaciones visibles
 * GET /comunicaciones?todas=1    -> también las inactivas (uso interno)
 */
class ComunicacionController
{
    public function index(Request $request, Response $response)
    {
        $params = $request->getQueryParams();
        $todas  = filter_var($params['todas'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $query = Comunicacion::query();

        if (!$todas) {
            $ahora = date('Y-m-d H:i:s');
            $query->where('activa', 1)
                ->where(function ($q) use ($ahora) {
                    $q->whereNull('fecha_inicio')
                      ->orWhere('fecha_inicio', '<=', $ahora);
                })
                ->where(function ($q) use ($ahora) {
                    $q->whereNull('fecha_fin')
                      ->orWhere('fecha_fin', '>=', $ahora);
                });
        }

        $items = $query->orderByDesc('orden')
            ->orderByDesc('created_at')
            ->get();

        return $this->successResponse($response, [
            'comunicaciones' => $items->map(function (Comunicacion $c) {
                return $this->serialize($c);
            })->all(),
        ]);
    }

    /**
     * Da formato a una comunicación. La imagen se devuelve como URL absoluta
     * para que la app pueda cargarla directamente.
     */
    private function serialize(Comunicacion $c): array
    {
        $imagen = trim((string) $c->imagen);

        if ($imagen !== '' && !preg_match('#^https?://#i', $imagen)) {
            $base = rtrim($_ENV['ADMIN_URL'] ?? 'https://admin.tupadelmatch.es', '/');
            $imagen = $base . '/' . ltrim($imagen, '/');
        }

        return [
            'id'           => (int) $c->id,
            'titulo'       => (string) $c->titulo,
            'texto'        => (string) ($c->texto ?? ''),
            'imagen'       => $imagen !== '' ? $imagen : null,
            'orden'        => (int) $c->orden,
            'fecha_inicio' => $c->fecha_inicio,
            'fecha_fin'    => $c->fecha_fin,
        ];
    }

    private function successResponse(Response $response, $data, int $statusCode = 200)
    {
        $payload = json_encode([
            'success' => true,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE);

        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($statusCode);
    }
}
