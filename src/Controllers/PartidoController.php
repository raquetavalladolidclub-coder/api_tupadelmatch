<?php
namespace PadelClub\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PadelClub\Models\Partido;
use PadelClub\Models\InscripcionPartido;
use PadelClub\Models\User;
use PadelClub\Services\NotificationService;

class PartidoController
{
    private $notificationService;
    
    // Hacer el parámetro opcional con valor null
    public function __construct(?NotificationService $notificationService = null)
    {
        $this->notificationService = $notificationService ?? new NotificationService();
    }

    public function listarMisPartidos(Request $request, Response $response)
    {
        try {
            $userId = $request->getAttribute('user_id'); // o desde token / sesión

            $query = Partido::with(['creador', 'jugadoresConfirmados.usuario', 'club:id,nombre,url_logo,url_imagen', 'pistaDetalle'])
                ->whereHas('inscripciones', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                });
            
            // Filtros
            $filters = $request->getQueryParams();
            
            // Filtrar por fecha
            if (isset($filters['fecha'])) {
                // $query->where('fecha', $filters['fecha']);
            }
            
            // Filtrar por fecha desde/hasta
            if (isset($filters['fecha_desde'])) {
                // $query->where('fecha', '>=', $filters['fecha_desde']);
            }
            
            if (isset($filters['fecha_hasta'])) {
                // $query->where('fecha', '<=', $filters['fecha_hasta']);
            }
            
            // Filtrar por tipo
            if (isset($filters['tipo'])) {
                // $query->where('tipo', $filters['tipo']);
            }
            
            // Filtrar por género
            if (isset($filters['genero'])) {
                // $query->where('genero', $filters['genero']);
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

    public function listarPartidos(Request $request, Response $response)
    {
        try {
            // $query = Partido::with(['creador', 'jugadoresConfirmados.usuario']);
            $query = Partido::with([
                'creador',
                'jugadoresConfirmados.usuario',
                'club:id,nombre,url_logo,url_imagen',
                'pistaDetalle'
            ]);
            
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

    public function listarPartidosProximos(Request $request, Response $response)
    {
        try {
            $userId = $request->getAttribute('user_id'); // o desde token / sesión

            $query = Partido::with(['creador', 'jugadoresConfirmados.usuario', 'pistaDetalle'])
                ->whereHas('inscripciones', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                });

            // Filtros
            $filters = $request->getQueryParams();

            // Filtrar por fecha
            if (isset($filters['fecha'])) {
                $query->where('fecha', '>=', $filters['fecha']);
            }

            // Ordenar por fecha y hora
            $query->orderBy('fecha', 'asc')
                ->orderBy('hora', 'asc');

            // Límite
            if (isset($filters['limite'])) {
                $query->limit((int)$filters['limite']);
            }

            $partidos = $query->get()->map(function ($partido) {
                return $this->formatearPartido($partido);
            });

            return $this->successResponse($response, [
                'partidos' => $partidos,
                'total'    => $partidos->count()
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse(
                $response,
                'Error al listar partidos: ' . $e->getMessage()
            );
        }
    }

    public function obtenerPartido(Request $request, Response $response, $args)
    {
        try {
            $partidoId = $args['id'];
            $userId    = $request->getAttribute('user_id');
            
            $partido = Partido::with(['creador', 'inscripciones.usuario', 'pistaDetalle'])
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
            
            $creador = User::find($userId);

            $partido = Partido::create([
                'categoria' => $data['categoria'] ?? ($creador->categoria ?? ''),
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
            if ($this->estaCompleto($partido)) {
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
            
            // Un partido sin nivelar (0) lo puede abrir cualquiera; en cuanto
            // tiene nivel, solo entran jugadores de ese nivel o superior.
            if ($nivelPartido > 0 && $nivelNumerico < $nivelPartido) {
                return $this->errorResponse($response, sprintf(
                    'Este partido es de nivel %s y tu nivel es %s. Solo pueden apuntarse jugadores de nivel %s o superior.',
                    $this->nombreNivel($nivelPartido),
                    $this->nombreNivel($nivelNumerico),
                    $this->nombreNivel($nivelPartido)
                ));
            }
            
            // Crear inscripción
            $inscripcion = InscripcionPartido::create([
                'partido_id'  => $partidoId,
                'user_id'     => $userId,
                'tipoReserva' => strtoupper($data['tipoReserva'] ?? 'individual'),
                'estado'      => 'confirmado', // 'pendiente',
                'comentario'  => $data['notas'] ?? null
            ]);
            
            // Si el partido es del creador, auto-confirmar
            if ($partido->creador_id == $userId) {
                $inscripcion->update(['estado' => 'confirmado']);
            }

            // El primero en inscribirse pasa a ser el creador del partido.
            if ($partido->jugadoresConfirmados()->count() == 1) {
                $partido->update(['creador_id' => $userId]);
            }

            // $partido = Partido::find($partidoId);

            /////////////////////////////////////////////////////////////////////////////////
            // Notificar al organizador que un jugador se ha apuntado
            $organizador = User::find($partido->creador_id);
         
            if ($organizador && $organizador->id != $userId) {
                $this->notificationService->sendPlayerJoinedNotification(
                    $partido, 
                    $usuario, 
                    $organizador->email
                );
            }

            // Confirmación al usuario: si reservó la pista entera, correo propio.
            $tipoReservaEmail = strtolower(trim((string) ($data['tipoReserva'] ?? 'individual')));
            if ($tipoReservaEmail === 'completo') {
                $this->notificationService->sendFullCourtConfirmation(
                    $partido,
                    $usuario,
                    $inscripcion
                );
            } else {
                $this->notificationService->sendPlayerConfirmationEmail(
                    $partido,
                    $usuario,
                    $inscripcion
                );
            }
            
            // Si el partido se completó, enviar notificación a todos
            if ($this->estaCompleto($partido)) {
                $jugadores = $partido->jugadoresConfirmados()->with('usuario')->get()->pluck('usuario');
                $this->notificationService->sendMatchFullNotification($partido, $jugadores);
            }
            /////////////////////////////////////////////////////////////////////////////////
            

            // Verificar si el partido se completó
            $this->actualizarEstadoPartido($partido, $userId, $nivelUsuario, $data['tipoReserva'] ?? 'individual'); //  $data['nivelPartido']
            
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
            $userId    = $request->getAttribute('user_id');
            $usuario   = User::find($userId);
            
            $inscripcion = InscripcionPartido::where('partido_id', $partidoId)->where('user_id', $userId)->first();
            
            if (!$inscripcion) {
                return $this->errorResponse($response, 'No estás inscrito en este partido', 404);
            }
            
            // No permitir cancelar si el usuario es el creador
            $partido = $inscripcion->partido;
            if ($partido->creador_id == $userId) {
                // return $this->errorResponse($response, 'El creador del partido no puede cancelar su inscripción');
            }
            
            $inscripcion->delete();

            /////////////////////////////////////////////////////////////////////////////////
            // ¿Estaba el partido completo antes de la baja?
            $estabaCompleto = ($partido->estado === 'completo');

            // Jugadores que siguen apuntados
            $restantes = $partido->jugadoresConfirmados()->with('usuario')->get()->pluck('usuario');
            $organizador = User::find($partido->creador_id);

            // 1) Aviso al organizador
            if ($organizador) {
                $this->notificationService->sendPlayerLeftNotification(
                    $partido,
                    $usuario,
                    $organizador->email
                );
            }

            // 2) Aviso al resto de jugadores (sin repetir al organizador ni al que se baja)
            foreach ($restantes as $jugador) {
                if ((int) $jugador->id === (int) $usuario->id) {
                    continue;
                }
                if ($organizador && (int) $jugador->id === (int) $organizador->id) {
                    continue;
                }
                if (!$this->aceptaEmail($jugador)) {
                    continue;
                }
                $this->notificationService->sendPlayerLeftNotification(
                    $partido,
                    $usuario,
                    $jugador->email
                );
            }

            // 3) Confirmación al usuario que se da de baja
            $this->notificationService->sendCancellationConfirmationToUser(
                $partido,
                $usuario,
                $inscripcion
            );

            // 4) Si el partido estaba completo, avisar de que ya no lo está
            if ($estabaCompleto && $restantes->count() > 0) {
                $this->notificationService->sendSpotAvailableNotification(
                    $partido,
                    $usuario,
                    $restantes
                );
            }
            /////////////////////////////////////////////////////////////////////////////////

            // Actualizar estado del partido
            $this->actualizarEstadoPartido($partido, $userId);

            // Si el creador se da de baja, el siguiente inscrito pasa a serlo.
            if ((int) $partido->creador_id === (int) $userId) {
                $siguiente = InscripcionPartido::where('partido_id', $partido->id)
                    ->where('estado', 'confirmado')
                    ->orderBy('id', 'asc')
                    ->first();

                if ($siguiente) {
                    $partido->update(['creador_id' => $siguiente->user_id]);
                }
            }

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
            
            $query = InscripcionPartido::with(['partido.creador', 'partido.jugadoresConfirmados', 'partido.pistaDetalle'])
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
            'pista'                 => (int) ($partido->pistaDetalle?->numero ?? $partido->pista),
            'tipo'                  => $partido->tipo,
            'tipoReserva'           => $partido->tipoReserva,
            'categoria'             => $partido->categoria,
            'genero'                => $partido->genero,
            'estado'                => $partido->estado,
            'plazas_disponibles'    => $this->plazasDisponibles($partido),
            'esta_completo'         => $this->estaCompleto($partido),
            'tiene_resultados'      => $partido->resultados()->exists(),
            'codLiga'               => $partido->codLiga,
            'precio_individual'     => number_format((float) ($partido->precio_individual ?? 0), 2),
            'precio_pista_completa' => number_format((float) ($partido->precio_pista_completa ?? 0), 2),
            'club' => [
                'logoClub'              => $partido->club?->url_logo ?? 'https://admin.tupadelmatch.es/assets/images/logo.png',
                'imagenClub'            => $partido->club?->url_imagen ?? 'https://admin.tupadelmatch.es/assets/images/logo.png',
                'nombreClub'            => $partido->club?->nombre ?? 'Club padel',
            ],
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
                    'imageUrl'    => $inscripcion->usuario->imagePath ?? 'assets/images/logo.png',
                    'fiabilidad'  => $inscripcion->usuario->fiabilidad ?? 0,
                    'tipoReserva' => $inscripcion->tipoReserva
                ];
            }),
            'total_jugadores' => $partido->jugadoresConfirmados->count(),
            'max_jugadores'   => $partido->tipo === 'individual' ? 2 : 4,
            'created_at'      => $partido->created_at
        ];
    }
    
    /**
     * Orden real de los niveles del club (de menor a mayor).
     * 'sin_nivel' / vacío = 0: el partido aún no está nivelado.
     */
    /** Jugadores por partido (pádel dobles). */
    private const MAX_JUGADORES = 4;

    /** ¿Está lleno? Se calcula con las inscripciones reales. */
    /** ¿El usuario quiere recibir emails? (0 = desactivado) */
    private function aceptaEmail($usuario): bool
    {
        return (int) ($usuario->notificacionesEmail ?? 1) === 1;
    }

    private function estaCompleto($partido): bool
    {
        return $partido->jugadoresConfirmados()->count() >= self::MAX_JUGADORES;
    }

    private function plazasDisponibles($partido): int
    {
        return max(0, self::MAX_JUGADORES - $partido->jugadoresConfirmados()->count());
    }

    private function convertirNivelANumero($nivel)
    {
        $niveles = [
            'sin_nivel' => 0,
            'sin nivel' => 0,
            'promesas'  => 1,
            'cobre'     => 2,
            'bronce'    => 3,
            'plata'     => 4,
            'diamante'  => 5,
            'oro'       => 6,
            'pro'       => 7,
        ];

        return $niveles[strtolower(trim((string) $nivel))] ?? 0;
    }

    private function nombreNivel($numero)
    {
        $nombres = [
            0 => 'Sin nivel',
            1 => 'Promesas',
            2 => 'Cobre',
            3 => 'Bronce',
            4 => 'Plata',
            5 => 'Diamante',
            6 => 'Oro',
            7 => 'Pro',
        ];

        return $nombres[$numero] ?? 'Sin nivel';
    }

    private function actualizarEstadoPartido($partido, $userId, $categoria="promesas", $tipoReserva="individual")
    {
        if ($partido->esta_completo) {
            $partido->update(['estado' => 'completo']);
        } else {
            if($partido->jugadoresConfirmados->count() == 1
                && $this->convertirNivelANumero($partido->categoria) === 0){
                // El primero en apuntarse fija el nivel del partido (solo si
                // el partido se creó sin nivel).
                $partido->update(['categoria' => "$categoria", 'estado' => 'disponible']);
            }elseif($partido->jugadoresConfirmados->count() >= 4){
                $partido->update(['estado' => 'completo']);
            }else{
                $partido->update(['estado' => 'disponible']);
            }
        }

        if($tipoReserva == "completo"){
            $partido->update(['tipoReserva' => strtoupper('completo'), 'estado' => 'completo']);
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