<?php
namespace PadelClub\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PadelClub\Models\Partido;
use PadelClub\Models\InscripcionPartido;
use PadelClub\Models\User;

class PartidoController
{
    public function listarPartidos(Request $request, Response $response)
    {
        try {
            $query = Partido::with(['creador', 'jugadoresConfirmados.usuario']);
            
            // Filtros
            $filters = $request->getQueryParams();
            
            // Filtrar por fecha
            if (isset($filters['fecha'])) {
                $query->where('fecha', $filters['fecha']);
            }
            
            // Filtrar por fecha desde/hasta
            if (isset($filters['fecha_desde'])) {
                $query->where('fecha', '>=', $filters['fecha_desde']);
            }
            
            if (isset($filters['fecha_hasta'])) {
                $query->where('fecha', '<=', $filters['fecha_hasta']);
            }
            
            // Filtrar por tipo
            if (isset($filters['tipo'])) {
                $query->where('tipo', $filters['tipo']);
            }
            
            // Filtrar por género
            if (isset($filters['genero'])) {
                $query->where('genero', $filters['genero']);
            }
            
            
            // Filtrar por estado
            /*$estado = $filters['estado'] ?? 'disponible';
            $query->where('estado', $estado);*/
            
            // Ordenar por fecha y hora
            $query->orderBy('fecha', 'asc')->orderBy('hora', 'asc');
            
            $partidos = $query->get()->map(function($partido) {
                return $this->formatearPartido($partido);
            });
            
            return $this->successResponse($response, [
                'partidos' => $partidos,
                'total'    => $partidos->count()
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al listar partidos: ' . $e->getMessage());
        }
    }
    
    public function obtenerPartido(Request $request, Response $response, $args)
    {
        try {
            $partidoId = $args['id'];
            $userId    = $request->getAttribute('user_id');
            
            $partido = Partido::with(['creador', 'inscripciones.usuario'])
                            ->find($partidoId);
            
            if (!$partido) {
                return $this->errorResponse($response, 'Partido no encontrado', 404);
            }
            
            $partidoData = $this->formatearPartido($partido);
            $partidoData['usuario_inscrito'] = $partido->usuarioInscrito($userId);
            
            // Obtener inscripción del usuario si existe
            $inscripcionUsuario = $partido->inscripciones()
                                        ->where('user_id', $userId)
                                        ->first();
            
            if ($inscripcionUsuario) {
                $partidoData['mi_inscripcion'] = [
                    'id' => $inscripcionUsuario->id,
                    'estado' => $inscripcionUsuario->estado,
                    'comentario' => $inscripcionUsuario->comentario,
                    'fecha_inscripcion' => $inscripcionUsuario->fecha_inscripcion
                ];
            }
            
            return $this->successResponse($response, ['partido' => $partidoData]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al obtener partido: ' . $e->getMessage());
        }
    }
    
    public function crearPartido(Request $request, Response $response)
    {
        try {
            $userId = $request->getAttribute('user_id');
            $data = $request->getParsedBody();
            
            // Validar datos requeridos
            $required = ['fecha', 'hora', 'duracion', 'pista', 'tipo'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return $this->errorResponse($response, "El campo $field es requerido");
                }
            }
            
            // Validar que la fecha no sea en el pasado
            $fechaPartido = new \DateTime($data['fecha']);
            $hoy = new \DateTime();
            if ($fechaPartido < $hoy) {
                return $this->errorResponse($response, 'No se pueden crear partidos en fechas pasadas');
            }
            
            // Validar límites de nivel
            if (isset($data['nivel_min']) && isset($data['nivel_max'])) {
                if ($data['nivel_min'] > $data['nivel_max']) {
                    return $this->errorResponse($response, 'El nivel mínimo no puede ser mayor al nivel máximo');
                }
            }
            
            $partido = Partido::create([
                'fecha' => $data['fecha'],
                'hora' => $data['hora'],
                'duracion' => $data['duracion'] ?? 60,
                'pista' => $data['pista'],
                'tipo' => $data['tipo'],
                'nivel_min' => $data['nivel_min'] ?? 1,
                'nivel_max' => $data['nivel_max'] ?? 5,
                'genero' => $data['genero'] ?? 'mixto',
                'estado' => 'disponible',
                'creador_id' => $userId
            ]);
            
            // Inscribir automáticamente al creador
            InscripcionPartido::create([
                'partido_id' => $partido->id,
                'user_id' => $userId,
                'estado' => 'confirmado',
                'comentario' => 'Creador del partido'
            ]);
            
            return $this->successResponse($response, [
                'message' => 'Partido creado correctamente',
                'partido' => $this->formatearPartido($partido)
            ], 201);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al crear partido: ' . $e->getMessage());
        }
    }
    
    public function inscribirsePartido(Request $request, Response $response, $args)
    {
        try {
            $partidoId = $args['id'];
            $userId    = $request->getAttribute('user_id');
            $data      = $request->getParsedBody();
            
            $partido = Partido::find($partidoId);
            
            if (!$partido) {
                return $this->errorResponse($response, 'Partido no encontrado', 404);
            }
            
            // Validar que el partido esté disponible
            if ($partido->estado !== 'disponible') {
                return $this->errorResponse($response, 'No es posible inscribirse a este partido');
            }
            
            // Validar que no esté completo
            if ($partido->esta_completo) {
                return $this->errorResponse($response, 'El partido ya está completo');
            }
            
            // Validar que el usuario no esté ya inscrito
            if ($partido->usuarioInscrito($userId)) {
                return $this->errorResponse($response, 'Ya estás inscrito en este partido');
            }
            
            // Validar nivel del usuario
            $usuario       = User::find($userId);
            $nivelUsuario  = $usuario->categoria ?? 'promesas';
            $nivelNumerico = $this->convertirNivelANumero($nivelUsuario);
            $nivelPartido  = $this->convertirNivelANumero($partido->categoria);
            
            if ($nivelNumerico < $nivelPartido) {
                return $this->errorResponse($response, 'Tu nivel no coincide con los requisitos del partido');
            }
            
            // Crear inscripción
            $inscripcion = InscripcionPartido::create([
                'partido_id'  => $partidoId,
                'user_id'     => $userId,
                'tipoReserva' => strtoupper($data['tipoReserva']),
                'estado'      => 'confirmado', // 'pendiente',
                'comentario'  => $data['notas'] ?? null
            ]);
            
            // Si el partido es del creador, auto-confirmar
            if ($partido->creador_id == $userId) {
                $inscripcion->update(['estado' => 'confirmado']);
            }
            
            // Verificar si el partido se completó
            $this->actualizarEstadoPartido($partido, $nivelUsuario, $data['tipoReserva']); //  $data['nivelPartido']
            
            return $this->successResponse($response, [
                'message' => 'Inscripción realizada correctamente',
                'inscripcion' => [
                    'id'         => $inscripcion->id,
                    'estado'     => $inscripcion->estado,
                    'partido_id' => $partido->id,
                    'data'       => $data
                ]
            ], 201);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al inscribirse: ' . $e->getMessage());
        }
    }
    
    public function cancelarInscripcion(Request $request, Response $response, $args)
    {
        try {
            $partidoId = $args['id'];
            $userId = $request->getAttribute('user_id');
            
            $inscripcion = InscripcionPartido::where('partido_id', $partidoId)
                                           ->where('user_id', $userId)
                                           ->first();
            
            if (!$inscripcion) {
                return $this->errorResponse($response, 'No estás inscrito en este partido', 404);
            }
            
            // No permitir cancelar si el usuario es el creador
            $partido = $inscripcion->partido;
            if ($partido->creador_id == $userId) {
                return $this->errorResponse($response, 'El creador del partido no puede cancelar su inscripción');
            }
            
            $inscripcion->delete();
            
            // Actualizar estado del partido
            $this->actualizarEstadoPartido($partido);
            
            return $this->successResponse($response, [
                'message' => 'Inscripción cancelada correctamente'
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al cancelar inscripción: ' . $e->getMessage());
        }
    }
    
    public function misInscripciones(Request $request, Response $response)
    {
        try {
            $userId = $request->getAttribute('user_id');
            $filters = $request->getQueryParams();
            
            $query = InscripcionPartido::with(['partido.creador', 'partido.jugadoresConfirmados'])
                                     ->where('user_id', $userId);
            
            // Filtrar por estado de inscripción
            if (isset($filters['estado'])) {
                $query->where('estado', $filters['estado']);
            }
            
            // Filtrar por fecha del partido
            if (isset($filters['fecha_desde'])) {
                $query->whereHas('partido', function($q) use ($filters) {
                    $q->where('fecha', '>=', $filters['fecha_desde']);
                });
            }
            
            if (isset($filters['fecha_hasta'])) {
                $query->whereHas('partido', function($q) use ($filters) {
                    $q->where('fecha', '<=', $filters['fecha_hasta']);
                });
            }
            
            $inscripciones = $query->orderBy('fecha_inscripcion', 'desc')->get();
            
            $resultado = $inscripciones->map(function($inscripcion) {
                return [
                    'id' => $inscripcion->id,
                    'estado' => $inscripcion->estado,
                    'fecha_inscripcion' => $inscripcion->fecha_inscripcion,
                    'comentario' => $inscripcion->comentario,
                    'partido' => $this->formatearPartido($inscripcion->partido)
                ];
            });
            
            return $this->successResponse($response, [
                'inscripciones' => $resultado,
                'total' => $resultado->count()
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al obtener inscripciones: ' . $e->getMessage());
        }
    }
    
    private function formatearPartido($partido)
    {
        return [
            'id'                    => $partido->id,
            'idClub'                => $partido->idClub,
            'nombre'                => $partido->nombre ?? "",
            'fecha'                 => $partido->fecha->format('Y-m-d'),
            'hora'                  => $partido->hora,
            'duracion'              => $partido->duracion,
            'pista'                 => $partido->pista,
            'tipo'                  => $partido->tipo,
            'tipoReserva'           => $partido->tipoReserva,
            'categoria'             => $partido->categoria,
            'genero'                => $partido->genero,
            'estado'                => $partido->estado,
            'plazas_disponibles'    => $partido->plazas_disponibles,
            'esta_completo'         => $partido->esta_completo,
            'codLiga'               => $partido->codLiga,
            'precio_individual'     => number_format($partido->precio_individual, 2),
            'precio_pista_completa' => number_format($partido->precio_pista_completa, 2),
            'creador' => [
                'id'    => $partido->creador->id,
                'name'  => $partido->creador->name ?? "",
                'email' => $partido->creador->email
            ],
            'jugadores_confirmados' => $partido->jugadoresConfirmados->map(function($inscripcion) {
                return [
                    'id'          => $inscripcion->usuario->id,
                    'username'    => $inscripcion->usuario->username,
                    'nombre'      => $inscripcion->usuario->nombre,
                    'apellidos'   => $inscripcion->usuario->apellidos,
                    'categoria'   => $inscripcion->usuario->categoria,
                    'imageUrl'    => $inscripcion->usuario->image_path,
                    'fiabilidad'  => $inscripcion->usuario->fiabilidad ?? 0,
                    'tipoReserva' => $inscripcion->tipoReserva
                ];
            }),
            'total_jugadores' => $partido->jugadoresConfirmados->count(),
            'max_jugadores'   => $partido->tipo === 'individual' ? 2 : 4,
            'created_at'      => $partido->created_at
        ];
    }
    
    private function convertirNivelANumero($nivel)
    {
        $niveles = [
            'promesas' => 1,
            'cobre'    => 1,
            'bronce'   => 1,
            'plata'    => 1,
            'oro'      => 1,
            'diamante' => 1
        ];
        
        return $niveles[$nivel] ?? 1;
    }

    private function actualizarEstadoPartido($partido, $categoria="promesas", $tipoReserva="individual")
    {
        if ($partido->esta_completo) {
            $partido->update(['estado' => 'completo']);
        } else {
            if($partido->jugadoresConfirmados->count() == 1){
                $partido->update(['categoria' => "$categoria", 'estado' => 'disponible']);
            }elseif($partido->jugadoresConfirmados->count() >= 4){
                $partido->update(['estado' => 'completo']);
            }else{
                $partido->update(['estado' => 'disponible']);
            }
        }

        if($tipoReserva == "completa"){
            $partido->update(['estado' => 'completo']);
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
    
    private function errorResponse(Response $response, $message, $statusCode = 400)
    {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message
        ]));
        
        return $response->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json');
    }
}