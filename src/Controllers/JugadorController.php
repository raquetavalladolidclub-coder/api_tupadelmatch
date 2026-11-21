<?php

namespace PadelClub\Controllers;

use PadelClub\Models\LigaHeadPartido;
use PadelClub\Models\LigaHeadPareja;
use PadelClub\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Ficha pública de un jugador: datos básicos y estadísticas de Liga Head.
 */
class JugadorController
{
    public function perfil(Request $request, Response $response, $args)
    {
        $id = (int) ($args['id'] ?? 0);
        $user = User::find($id);

        if (!$user) {
            return $this->errorResponse($response, 'Jugador no encontrado', 404);
        }

        return $this->successResponse($response, [
            'jugador' => $this->formatearJugador($user),
            'estadisticas' => $this->estadisticas($id),
        ]);
    }

    private function formatearJugador(User $user): array
    {
        return [
            'id'         => (int) $user->id,
            'nombre'     => $user->fullName ?: $user->username,
            'username'   => $user->username,
            'categoria'  => $user->categoria,
            'genero'     => $user->genero,
            'fiabilidad' => (int) ($user->fiabilidad ?? 0),
            'foto'       => $this->urlFoto($user->imagePath ?? ''),
            'alta'       => $user->created_at ? date('Y-m-d', strtotime((string) $user->created_at)) : null,
        ];
    }

    /** Estadísticas de Liga Head del jugador (solo partidos confirmados). */
    private function estadisticas(int $userId): array
    {
        $parejas = LigaHeadPareja::where('jugador1_id', $userId)
            ->orWhere('jugador2_id', $userId)
            ->pluck('id')
            ->all();

        $stats = [
            'parejas' => count($parejas),
            'pj' => 0, 'pg' => 0, 'pp' => 0, 'sf' => 0, 'sc' => 0, 'puntos' => 0,
        ];

        if (!$parejas) {
            return $stats;
        }

        $partidos = LigaHeadPartido::where('confirmado_club', 1)
            ->where(function ($q) use ($parejas) {
                $q->whereIn('pareja_a_id', $parejas)->orWhereIn('pareja_b_id', $parejas);
            })
            ->with('sets')
            ->get();

        foreach ($partidos as $partido) {
            $esA = in_array((int) $partido->pareja_a_id, $parejas, true);
            $setsA = 0;
            $setsB = 0;

            foreach ($partido->sets as $set) {
                if ($set->games_a > $set->games_b) {
                    $setsA++;
                } elseif ($set->games_b > $set->games_a) {
                    $setsB++;
                }
            }

            $favor = $esA ? $setsA : $setsB;
            $contra = $esA ? $setsB : $setsA;

            $stats['pj']++;
            $stats['sf'] += $favor;
            $stats['sc'] += $contra;

            if ($favor > $contra) {
                $stats['pg']++;
                $stats['puntos'] += 3;
            } elseif ($contra > $favor) {
                $stats['pp']++;
            }
        }

        $stats['winrate'] = $stats['pj'] > 0 ? round(100 * $stats['pg'] / $stats['pj']) : 0;

        return $stats;
    }

    private function urlFoto($ruta): ?string
    {
        $ruta = trim((string) $ruta);
        if ($ruta === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $ruta)) {
            return $ruta;
        }
        $base = str_starts_with($ruta, '/')
            ? 'https://api.tupadelmatch.es'
            : 'https://admin.tupadelmatch.es/';
        return rtrim($base, '/') . '/' . ltrim($ruta, '/');
    }

    private function successResponse(Response $response, $data, $statusCode = 200): Response
    {
        $response->getBody()->write(json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }

    private function errorResponse(Response $response, string $message, int $statusCode = 400): Response
    {
        $response->getBody()->write(json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
}
