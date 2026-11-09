<?php

namespace PadelClub\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PadelClub\Models\FanaticEvento;
use PadelClub\Models\FanaticEvaluacion;
use PadelClub\Models\FanaticNivel;
use PadelClub\Models\FanaticRegistro;
use PadelClub\Models\User;

/**
 * FANATIC: eventos por niveles, inscripción pendiente de pago y jugadores.
 *
 * GET  /fanatic            -> eventos + mi inscripción
 * GET  /fanatic/{id}       -> detalle, reglamento y jugadores confirmados
 * POST /fanatic/registro   -> solicitud de inscripción (pendiente)
 */
class FanaticController
{
    private array $niveles = [
        'promesas' => 1,
        'cobre'    => 2,
        'bronce'   => 3,
        'plata'    => 4,
        'diamante' => 5,
        'oro'      => 6,
        'pro'      => 7,
    ];

    public function index(Request $request, Response $response)
    {
        $userId = $request->getAttribute('user_id');
        $user   = User::find($userId);

        if (!$user) {
            return $this->errorResponse($response, 'Usuario no encontrado', 404);
        }

        $categoriaUsuario = $this->normalizarCategoria($user->categoria ?? 'promesas');

        $eventos = FanaticEvento::where('activo', 1)
            ->where(function ($query) use ($userId) {
                $query->whereDate('fecha', '>=', date('Y-m-d'))
                    ->orWhereHas('registros', function ($q) use ($userId) {
                        $q->where('user_id', $userId);
                    });
            })
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get();

        $data = $eventos->map(function (FanaticEvento $evento) use ($userId, $categoriaUsuario) {
            return $this->formatearEvento($evento, $userId, $categoriaUsuario, false);
        })->values();

        return $this->successResponse($response, [
            'categoria_usuario' => $categoriaUsuario,
            'eventos'           => $data,
        ]);
    }

    public function misEvaluaciones(Request $request, Response $response)
    {
        $userId = $request->getAttribute('user_id');
        $user   = User::find($userId);

        if (!$user) {
            return $this->errorResponse($response, 'Usuario no encontrado', 404);
        }

        $evaluaciones = FanaticEvaluacion::with('evento')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $data = $evaluaciones->map(function (FanaticEvaluacion $evaluacion) {
            $evento = $evaluacion->evento;

            return [
                'id'             => $evaluacion->id,
                'evento_id'      => $evaluacion->evento_id,
                'evento_nombre'  => $evento->nombre ?? 'FANATIC',
                'evento_fecha'   => $evento && $evento->fecha
                    ? $evento->fecha->format('Y-m-d')
                    : null,
                'tecnica'        => (int) $evaluacion->tecnica,
                'tactica'        => (int) $evaluacion->tactica,
                'fisico'         => (int) $evaluacion->fisico,
                'actitud'        => (int) $evaluacion->actitud,
                'puntuacion'     => (float) $evaluacion->puntuacion,
                'nivel_anterior' => $evaluacion->nivel_anterior,
                'nivel_nuevo'    => $evaluacion->nivel_nuevo,
                'evaluador'      => $evaluacion->evaluador,
                'comentario'     => $evaluacion->comentario,
                'created_at'     => $evaluacion->created_at
                    ? $evaluacion->created_at->format('Y-m-d H:i')
                    : null,
            ];
        })->values();

        return $this->successResponse($response, [
            'categoria_usuario' => $this->normalizarCategoria($user->categoria ?? 'promesas'),
            'evaluaciones'      => $data,
        ]);
    }

    public function detalle(Request $request, Response $response, $args)
    {
        $userId = $request->getAttribute('user_id');
        $user   = User::find($userId);
        $evento = FanaticEvento::find($args['id'] ?? 0);

        if (!$user) {
            return $this->errorResponse($response, 'Usuario no encontrado', 404);
        }
        if (!$evento) {
            return $this->errorResponse($response, 'Evento no encontrado', 404);
        }

        $categoriaUsuario = $this->normalizarCategoria($user->categoria ?? 'promesas');
        $miRegistro = FanaticRegistro::where('evento_id', $evento->id)
            ->where('user_id', $userId)
            ->first();

        $jugadores = [];
        if ($miRegistro) {
            $registros = FanaticRegistro::with('usuario')
                ->where('evento_id', $evento->id)
                ->where('estado', 'confirmado')
                ->get();

            foreach ($registros as $registro) {
                $usuario = $registro->usuario;
                if (!$usuario) {
                    continue;
                }

                $nombre = trim((string) ($usuario->fullName ?? ''));
                if ($nombre === '') {
                    $nombre = trim(($usuario->nombre ?? '') . ' ' . ($usuario->apellidos ?? ''));
                }
                if ($nombre === '') {
                    $nombre = $usuario->username ?? 'Jugador';
                }

                $jugadores[] = [
                    'id'        => $usuario->id,
                    'nombre'    => $nombre,
                    'imagePath' => $usuario->imagePath ?? '',
                    'categoria' => $registro->categoria,
                ];
            }
        }

        return $this->successResponse($response, [
            'evento'             => $this->formatearEvento($evento, $userId, $categoriaUsuario, true),
            'mi_registro'        => $this->formatearRegistro($miRegistro),
            'jugadores'          => $jugadores,
            'puede_ver_jugadores' => $miRegistro !== null,
        ]);
    }

    public function registrar(Request $request, Response $response)
    {
        $userId    = $request->getAttribute('user_id');
        $data      = $request->getParsedBody() ?? [];
        $eventoId  = (int) ($data['evento_id'] ?? 0);
        $categoria = strtolower(trim((string) ($data['categoria'] ?? '')));
        $categoria = $this->normalizarCategoria($categoria);

        $user = User::find($userId);
        if (!$user) {
            return $this->errorResponse($response, 'Usuario no encontrado', 404);
        }

        $evento = FanaticEvento::where('activo', 1)->where('id', $eventoId)->first();
        if (!$evento) {
            return $this->errorResponse($response, 'Evento no disponible', 404);
        }

        $nivel = FanaticNivel::where('evento_id', $eventoId)
            ->where('categoria', $categoria)
            ->first();

        if (!$nivel) {
            return $this->errorResponse($response, 'Ese nivel no se juega en este FANATIC');
        }

        $categoriaUsuario = $this->normalizarCategoria($user->categoria ?? 'promesas');

        // De momento: nivel del jugador o superior.
        if ($this->nivelNumero($categoria) < $this->nivelNumero($categoriaUsuario)) {
            return $this->errorResponse(
                $response,
                'Solo puedes inscribirte en tu nivel o en uno superior.'
            );
        }

        $existente = FanaticRegistro::where('evento_id', $eventoId)
            ->where('user_id', $userId)
            ->first();

        if ($existente && $existente->estado !== 'rechazado') {
            return $this->errorResponse($response, 'Ya tienes una solicitud para este FANATIC');
        }

        $ocupadas = FanaticRegistro::where('evento_id', $eventoId)
            ->where('categoria', $categoria)
            ->where('estado', '!=', 'rechazado')
            ->count();

        if ((int) $nivel->plazas > 0 && $ocupadas >= (int) $nivel->plazas) {
            return $this->errorResponse($response, 'No quedan plazas en ese nivel');
        }

        $datos = [
            'evento_id' => $eventoId,
            'user_id'   => $userId,
            'categoria' => $categoria,
            'estado'    => 'pendiente',
            'pagado'    => 0,
            'importe'   => $nivel->precio,
        ];

        if ($existente) {
            $existente->update($datos);
            $registro = $existente->fresh();
        } else {
            $registro = FanaticRegistro::create($datos);
        }

        return $this->successResponse($response, [
            'message'  => 'Solicitud enviada. El administrador confirmará el pago.',
            'registro' => $this->formatearRegistro($registro),
        ], 201);
    }

    private function formatearEvento(
        FanaticEvento $evento,
        $userId,
        $categoriaUsuario,
        bool $incluirReglamento
    ): array {
        $niveles = $evento->niveles()->get()
            ->sortBy(fn ($nivel) => $this->nivelNumero($nivel->categoria))
            ->values();

        $miRegistro = FanaticRegistro::where('evento_id', $evento->id)
            ->where('user_id', $userId)
            ->first();

        $nivelesData = $niveles->map(function (FanaticNivel $nivel) use ($categoriaUsuario) {
            $ocupadas = FanaticRegistro::where('evento_id', $nivel->evento_id)
                ->where('categoria', $nivel->categoria)
                ->where('estado', '!=', 'rechazado')
                ->count();

            return [
                'categoria'        => $nivel->categoria,
                'precio'           => (float) $nivel->precio,
                'plazas'           => (int) $nivel->plazas,
                'ocupadas'         => $ocupadas,
                'completo'         => (int) $nivel->plazas > 0 && $ocupadas >= (int) $nivel->plazas,
                'puede_inscribirse' => $this->nivelNumero($categoriaUsuario) <= $this->nivelNumero($nivel->categoria),
            ];
        })->values();

        $data = [
            'id'         => $evento->id,
            'nombre'     => $evento->nombre,
            'fecha'      => $evento->fecha ? $evento->fecha->format('Y-m-d') : null,
            'hora'       => $evento->hora,
            'lugar'      => $evento->lugar,
            'activo'     => (bool) $evento->activo,
            'niveles'    => $nivelesData,
            'mi_registro' => $this->formatearRegistro($miRegistro),
        ];

        if ($incluirReglamento) {
            $data['reglamento'] = $evento->reglamento;
        }

        return $data;
    }

    private function formatearRegistro(?FanaticRegistro $registro): ?array
    {
        if (!$registro) {
            return null;
        }

        return [
            'id'        => $registro->id,
            'categoria' => $registro->categoria,
            'estado'    => $registro->estado,
            'pagado'    => (bool) $registro->pagado,
            'importe'   => $registro->importe !== null ? (float) $registro->importe : null,
        ];
    }

    private function normalizarCategoria($categoria): string
    {
        $categoria = strtolower(trim((string) $categoria));
        return isset($this->niveles[$categoria]) ? $categoria : 'promesas';
    }

    private function nivelNumero($categoria): int
    {
        $categoria = $this->normalizarCategoria($categoria);
        return $this->niveles[$categoria] ?? 1;
    }

    private function successResponse(Response $response, $data, $statusCode = 200)
    {
        $response->getBody()->write(json_encode([
            'success' => true,
            'data'    => $data,
        ]));

        return $response->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json');
    }

    private function errorResponse(Response $response, $message, $statusCode = 400)
    {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message,
        ]));

        return $response->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json');
    }
}
