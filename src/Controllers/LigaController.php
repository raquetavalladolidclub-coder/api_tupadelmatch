<?php
namespace PadelClub\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PadelClub\Models\Partido;
use PadelClub\Models\ResultadoPartido;
use PadelClub\Models\ResultadoSet;
use PadelClub\Models\EstadisticaLiga;
use PadelClub\Models\User;
use PadelClub\Models\InscripcionPartido;

class LigaController
{
    // POST /api/partidos/{id}/resultados
    public function guardarResultados(Request $request, Response $response, $args)
    {
        try {
            $partidoId = $args['id'];
            $userId    = $request->getAttribute('user_id');
            $data      = $request->getParsedBody();
            
            // 1. Validar partido
            $partido = Partido::with(['creador', 'inscripciones.usuario'])->find($partidoId);
            
            if (!$partido) {
                return $this->errorResponse($response, 'Partido no encontrado', 404);
            }
            
            // 2. Validar que es partido de liga
            if (empty($partido->codLiga)) {
                return $this->errorResponse($response, 'Este partido no es de liga');
            }
            
            // 3. Validar que el usuario es el creador
            if ($partido->creador_id != $userId) {
                return $this->errorResponse($response, 'Solo el creador del partido puede registrar resultados');
            }
            
            // 4. Validar que el partido está finalizado
            if ($partido->estado != 'finalizado') {
                return $this->errorResponse($response, 'El partido debe estar en estado "finalizado"');
            }
            
            // 5. Validar que no tiene resultados previos
            if ($partido->resultados()->exists()) {
                return $this->errorResponse($response, 'Este partido ya tiene resultados registrados');
            }
            
            // 6. Validar sets
            if (!isset($data['sets']) || !is_array($data['sets']) || count($data['sets']) != 3) {
                return $this->errorResponse($response, 'Debe proporcionar resultados para 3 sets');
            }
            
            $setsValidados = [];
            foreach ($data['sets'] as $index => $set) {
                if (!isset($set['puntosEquipoA'], $set['puntosEquipoB'])) {
                    return $this->errorResponse($response, "Formato inválido en set " . ($index + 1));
                }
                
                $puntosA = (int)$set['puntosEquipoA'];
                $puntosB = (int)$set['puntosEquipoB'];
                
                if (!$this->validarPuntuacionSet($puntosA, $puntosB)) {
                    return $this->errorResponse($response, "Puntuación inválida en set " . ($index + 1) . ": $puntosA-$puntosB");
                }
                
                $setsValidados[] = [
                    'numero_set' => $index + 1,
                    'puntos_equipo_a' => $puntosA,
                    'puntos_equipo_b' => $puntosB
                ];
            }
            
            // 7. Calcular resultados
            $resultados = $this->calcularResultados($setsValidados);
            
            // 8. Obtener jugadores organizados por equipos
            $jugadores = $this->obtenerJugadoresPorEquipo($partido);
            
            if (count($jugadores['equipoA']) < 1 || count($jugadores['equipoB']) < 1) {
                return $this->errorResponse($response, 'El partido no tiene suficientes jugadores para equipos');
            }
            
            // 9. Guardar en transacción MANUALMENTE
            $pdo = \PadelClub\Models\Partido::getConnectionResolver()->connection()->getPdo();
            $pdo->beginTransaction();
            
            try {
                // Guardar resultado principal
                $resultado = ResultadoPartido::create([
                    'partido_id' => $partidoId,
                    'sets_ganados_equipo_a' => $resultados['setsGanadosA'],
                    'sets_ganados_equipo_b' => $resultados['setsGanadosB'],
                    'puntos_totales_equipo_a' => $resultados['puntosTotalesA'],
                    'puntos_totales_equipo_b' => $resultados['puntosTotalesB'],
                    'equipo_ganador' => $resultados['equipoGanador']
                ]);
                
                // Guardar sets individuales
                foreach ($setsValidados as $set) {
                    ResultadoSet::create([
                        'resultado_id' => $resultado->id,
                        'numero_set' => $set['numero_set'],
                        'puntos_equipo_a' => $set['puntos_equipo_a'],
                        'puntos_equipo_b' => $set['puntos_equipo_b']
                    ]);
                }
                
                // Actualizar estadísticas de jugadores
                $this->actualizarEstadisticasJugadores(
                    $partido->codLiga,
                    $jugadores,
                    $resultados
                );
                
                $pdo->commit();
                
                return $this->successResponse($response, [
                    'message' => 'Resultados guardados correctamente',
                    'resultado' => $this->formatearResultado($resultado)
                ], 201);
                
            } catch (\Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al guardar resultados: ' . $e->getMessage());
        }
    }
    
    private function actualizarEstadisticasJugadores($codLiga, $jugadores, $resultados): void
    {
        // Actualizar equipo A
        $ganoEquipoA = $resultados['equipoGanador'] == 'A';
        foreach ($jugadores['equipoA'] as $jugador) {
            $this->actualizarEstadisticasIndividuales(
                $jugador->id,
                $codLiga,
                $ganoEquipoA,
                $resultados['setsGanadosA'],
                $resultados['setsGanadosB'],
                $resultados['puntosTotalesA'],
                $resultados['puntosTotalesB']
            );
        }
        
        // Actualizar equipo B
        $ganoEquipoB = $resultados['equipoGanador'] == 'B';
        foreach ($jugadores['equipoB'] as $jugador) {
            $this->actualizarEstadisticasIndividuales(
                $jugador->id,
                $codLiga,
                $ganoEquipoB,
                $resultados['setsGanadosB'],
                $resultados['setsGanadosA'],
                $resultados['puntosTotalesB'],
                $resultados['puntosTotalesA']
            );
        }
    }
    
    private function actualizarEstadisticasIndividuales(
        $usuarioId, 
        $codLiga, 
        $gano, 
        $setsGanados, 
        $setsPerdidos,
        $puntosAFavor, 
        $puntosEnContra
    ): void {
        $estadistica = EstadisticaLiga::firstOrNew([
            'user_id' => $usuarioId,
            'codLiga' => $codLiga
        ]);
        
        // Si es nueva, inicializar con valores por defecto
        if (!$estadistica->exists) {
            $estadistica->fill([
                'partidos_jugados' => 0,
                'partidos_ganados' => 0,
                'partidos_perdidos' => 0,
                'sets_ganados' => 0,
                'sets_perdidos' => 0,
                'puntos_a_favor' => 0,
                'puntos_en_contra' => 0,
                'puntos_ranking' => 1000
            ]);
        }
        
        // Guardar puntos anteriores antes de actualizar
        $puntosAnterior = $estadistica->puntos_ranking;
        
        // Actualizar estadísticas
        $estadistica->partidos_jugados += 1;
        $estadistica->partidos_ganados += $gano ? 1 : 0;
        $estadistica->partidos_perdidos += $gano ? 0 : 1;
        $estadistica->sets_ganados += $setsGanados;
        $estadistica->sets_perdidos += $setsPerdidos;
        $estadistica->puntos_a_favor += $puntosAFavor;
        $estadistica->puntos_en_contra += $puntosEnContra;
        
        // Calcular nuevos puntos de ranking
        $nuevosPuntos = $this->calcularPuntosRanking(
            $estadistica->puntos_ranking,
            $gano,
            $setsGanados,
            $setsPerdidos
        );
        
        $estadistica->puntos_ranking = $nuevosPuntos;
        $estadistica->save();
        
        // Registrar en historial usando query builder directo
        $pdo = \PadelClub\Models\Partido::getConnectionResolver()->connection()->getPdo();
        
        $sql = "INSERT INTO historial_ranking (user_id, codLiga, puntos_anterior, puntos_nuevo, diferencia, motivo, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $usuarioId,
            $codLiga,
            $puntosAnterior,
            $nuevosPuntos,
            $nuevosPuntos - $puntosAnterior,
            'partido_jugado',
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ]);
    }
    
    private function obtenerHistorialJugador($codLiga, $usuarioId): array
    {
        $pdo = \PadelClub\Models\Partido::getConnectionResolver()->connection()->getPdo();
        
        $sql = "SELECT hr.puntos_anterior, hr.puntos_nuevo, hr.diferencia, hr.motivo, p.fecha, p.id as partido_id
                FROM historial_ranking hr
                LEFT JOIN partidos p ON hr.partido_id = p.id
                WHERE hr.codLiga = :codLiga AND hr.user_id = :usuarioId
                ORDER BY hr.created_at DESC
                LIMIT 10";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':codLiga' => $codLiga,
            ':usuarioId' => $usuarioId
        ]);
        
        $resultados = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return array_map(function($item) {
            return [
                'puntos_anterior' => $item['puntos_anterior'],
                'puntos_nuevo' => $item['puntos_nuevo'],
                'diferencia' => $item['diferencia'],
                'tendencia' => $item['diferencia'] >= 0 ? 'positiva' : 'negativa',
                'motivo' => $item['motivo'],
                'fecha' => $item['fecha'],
                'partido_id' => $item['partido_id']
            ];
        }, $resultados);
    }
    
    private function obtenerRivalesFrecuentes($codLiga, $usuarioId): array
    {
        $pdo = \PadelClub\Models\Partido::getConnectionResolver()->connection()->getPdo();
        
        $sql = "SELECT 
                    u.id,
                    u.nombre,
                    u.apellidos,
                    COUNT(*) as partidos_jugados,
                    SUM(CASE WHEN rp.equipo_ganador = 
                        CASE WHEN jp1.posicion % 2 = 0 THEN 'A' ELSE 'B' END 
                        THEN 1 ELSE 0 END) as partidos_ganados
                FROM partidos p
                JOIN inscripciones_partidos jp1 ON p.id = jp1.partido_id
                JOIN inscripciones_partidos jp2 ON p.id = jp2.partido_id
                JOIN users u ON jp2.user_id = u.id
                JOIN resultados_partidos rp ON p.id = rp.partido_id
                WHERE p.codLiga = :codLiga
                AND jp1.user_id = :usuarioId1
                AND jp2.user_id != :usuarioId2
                AND p.estado = 'finalizado'
                AND jp1.estado = 'confirmado'
                AND jp2.estado = 'confirmado'
                GROUP BY u.id, u.nombre, u.apellidos
                ORDER BY partidos_jugados DESC
                LIMIT 5";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':codLiga' => $codLiga,
            ':usuarioId1' => $usuarioId,
            ':usuarioId2' => $usuarioId
        ]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // GET /api/ligas/{codLiga}/ranking
    public function obtenerRankingLiga(Request $request, Response $response, $args)
    {
        try {
            $codLiga = $args['codLiga'];
            $userId = $request->getAttribute('user_id');
            
            // Verificar que el usuario pertenece a la liga
            $usuario = User::find($userId);
            if ($usuario->codLiga != $codLiga) {
                return $this->errorResponse($response, 'No perteneces a esta liga', 403);
            }
            
            // Obtener ranking
            $ranking = EstadisticaLiga::with('usuario')
                ->where('codLiga', $codLiga)
                ->orderBy('puntos_ranking', 'desc')
                ->get()
                ->map(function($estadistica, $index) {
                    return $this->formatearEstadisticaRanking($estadistica, $index + 1);
                });
            
            // Obtener información de la liga
            $infoLiga = $this->obtenerInfoLiga($codLiga);
            
            // Obtener posición del usuario
            $miPosicion = $this->obtenerPosicionUsuario($codLiga, $userId);
            
            return $this->successResponse($response, [
                'ranking' => $ranking,
                'info_liga' => $infoLiga,
                'mi_posicion' => $miPosicion
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al obtener ranking: ' . $e->getMessage());
        }
    }
    
    // GET /api/ligas/{codLiga}/estadisticas[/{usuarioId}]
    public function obtenerEstadisticasJugador(Request $request, Response $response, $args)
    {
        try {
            $codLiga = $args['codLiga'];
            $usuarioId = $args['usuarioId'] ?? $request->getAttribute('user_id');
            
            // Obtener estadísticas del jugador
            $estadistica = EstadisticaLiga::with('usuario')
                ->where('codLiga', $codLiga)
                ->where('user_id', $usuarioId)
                ->first();
            
            if (!$estadistica) {
                return $this->errorResponse($response, 'Jugador no encontrado en la liga', 404);
            }
            
            // Obtener historial reciente
            $historial = $this->obtenerHistorialJugador($codLiga, $usuarioId);
            
            // Obtener rivales frecuentes
            $rivales = $this->obtenerRivalesFrecuentes($codLiga, $usuarioId);
            
            // Obtener últimos partidos
            $ultimosPartidos = $this->obtenerUltimosPartidosJugador($codLiga, $usuarioId);
            
            return $this->successResponse($response, [
                'estadisticas' => $this->formatearEstadisticaCompleta($estadistica),
                'historial' => $historial,
                'rivales' => $rivales,
                'ultimos_partidos' => $ultimosPartidos
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al obtener estadísticas: ' . $e->getMessage());
        }
    }
    
    // GET /api/ligas/{codLiga}/ultimos-partidos
    public function obtenerUltimosPartidosLiga(Request $request, Response $response, $args)
    {
        try {
            $codLiga     = $args['codLiga'];
            $queryParams = $request->getQueryParams();
            $limit       = $queryParams['limit'] ?? 10;
            
            $partidos = Partido::where('codLiga', $codLiga)
                ->where('estado', 'finalizado')
                ->whereHas('resultados')
                ->with(['resultados', 'creador', 'inscripciones' => function($query) {
                    $query->where('estado', 'confirmado')
                          ->with('usuario');
                }])
                ->orderBy('fecha', 'desc')
                ->orderBy('hora', 'desc')
                ->limit($limit)
                ->get()
                ->map(function($partido) {
                    return $this->formatearPartidoConResultado($partido);
                });
            
            return $this->successResponse($response, [
                'partidos' => $partidos,
                'total'    => $partidos->count()
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error al obtener partidos: ' . $e->getMessage());
        }
    }
    
    // ==================== MÉTODOS PRIVADOS ====================
    
    private function validarPuntuacionSet($puntosA, $puntosB): bool
    {
        // Ambos 0 = set no jugado (válido)
        if ($puntosA == 0 && $puntosB == 0) {
            return true;
        }
        
        // No pueden ser ambos positivos y menores a 6
        if ($puntosA > 0 && $puntosB > 0 && $puntosA < 6 && $puntosB < 6) {
            return false;
        }
        
        $diferencia = abs($puntosA - $puntosB);
        
        // Diferencia mínima de 2 puntos
        if ($diferencia < 2) {
            return false;
        }
        
        // Puntuaciones válidas en padel
        $puntuacionesValidas = [
            '6-0', '6-1', '6-2', '6-3', '6-4',
            '7-5', '7-6', '7-4'
        ];
        
        $puntuacion = "$puntosA-$puntosB";
        $puntuacionInversa = "$puntosB-$puntosA";
        
        return in_array($puntuacion, $puntuacionesValidas) || 
               in_array($puntuacionInversa, $puntuacionesValidas);
    }
    
    private function calcularResultados($sets): array
    {
        $setsGanadosA = 0;
        $setsGanadosB = 0;
        $puntosTotalesA = 0;
        $puntosTotalesB = 0;
        
        foreach ($sets as $set) {
            $puntosA = $set['puntos_equipo_a'];
            $puntosB = $set['puntos_equipo_b'];
            
            $puntosTotalesA += $puntosA;
            $puntosTotalesB += $puntosB;
            
            if ($puntosA > $puntosB) {
                $setsGanadosA++;
            } elseif ($puntosB > $puntosA) {
                $setsGanadosB++;
            }
        }
        
        if ($setsGanadosA == $setsGanadosB) {
            throw new \Exception('El partido debe tener un ganador (no puede ser empate en sets)');
        }
        
        return [
            'setsGanadosA' => $setsGanadosA,
            'setsGanadosB' => $setsGanadosB,
            'puntosTotalesA' => $puntosTotalesA,
            'puntosTotalesB' => $puntosTotalesB,
            'equipoGanador' => $setsGanadosA > $setsGanadosB ? 'A' : 'B'
        ];
    }
    
    private function calcularPuntosRanking($puntosActuales, $gano, $setsGanados, $setsPerdidos): int
    {
        $kFactor = 32; // Factor K para sistema ELO
        
        // Resultado esperado (0.5 para todos inicialmente)
        $resultadoEsperado = 0.5;
        
        // Resultado real (1 si gana, 0 si pierde)
        $resultadoReal = $gano ? 1.0 : 0.0;
        
        // Calcular cambio de puntos ELO
        $cambioELO = $kFactor * ($resultadoReal - $resultadoEsperado);
        
        // Bonificación por sets ganados
        $bonusSets = $setsGanados * 5;
        
        // Penalización por sets perdidos
        $penalizacionSets = $setsPerdidos * 2;
        
        $nuevosPuntos = $puntosActuales + $cambioELO + $bonusSets - $penalizacionSets;
        
        // Mínimo 100 puntos
        return max(100, (int)round($nuevosPuntos));
    }
    
    private function obtenerInfoLiga($codLiga): array
    {
        $totalPartidos = Partido::where('codLiga', $codLiga)
            ->where('estado', 'finalizado')
            ->count();
        
        $totalJugadores = EstadisticaLiga::where('codLiga', $codLiga)
            ->distinct('user_id')
            ->count('user_id');
        
        $ultimoPartido = Partido::where('codLiga', $codLiga)
            ->where('estado', 'finalizado')
            ->orderBy('fecha', 'desc')
            ->first();
        
        return [
            'total_partidos' => $totalPartidos,
            'total_jugadores' => $totalJugadores,
            'ultimo_partido' => $ultimoPartido ? $ultimoPartido->fecha : null
        ];
    }
    
    private function obtenerPosicionUsuario($codLiga, $usuarioId): ?array
    {
        $rankingCompleto = EstadisticaLiga::where('codLiga', $codLiga)
            ->orderBy('puntos_ranking', 'desc')
            ->pluck('user_id')
            ->toArray();
        
        $posicion = array_search($usuarioId, $rankingCompleto);
        
        if ($posicion === false) {
            return null;
        }
        
        $estadistica = EstadisticaLiga::where('codLiga', $codLiga)
            ->where('user_id', $usuarioId)
            ->first();
        
        return [
            'posicion' => $posicion + 1,
            'puntos_ranking' => $estadistica->puntos_ranking,
            'partidos_jugados' => $estadistica->partidos_jugados
        ];
    }
    
    private function obtenerUltimosPartidosJugador($codLiga, $usuarioId): array
    {
        return Partido::where('codLiga', $codLiga)
            ->where('estado', 'finalizado')
            ->whereHas('inscripciones', function($query) use ($usuarioId) {
                $query->where('user_id', $usuarioId)
                      ->where('estado', 'confirmado');
            })
            ->with(['resultados', 'creador'])
            ->orderBy('fecha', 'desc')
            ->limit(5)
            ->get()
            ->map(function($partido) use ($usuarioId) {
                return $this->formatearPartidoJugador($partido, $usuarioId);
            })
            ->toArray();
    }
    
    // ==================== MÉTODOS DE FORMATEO ====================

    private function formatearPartidoParaResultadosOLD($partido): array
    {
        $jugadores = $this->obtenerJugadoresPorEquipo($partido);
        
        // Convertir las colecciones de jugadores a arrays
        $equipoAArray = $jugadores['equipoA']->map(function($jugador) {
            return [
                'id' => $jugador->id,
                'nombre' => $jugador->nombre,
                'apellidos' => $jugador->apellidos
            ];
        })->toArray(); // <-- Añadir toArray() aquí
        
        $equipoBArray = $jugadores['equipoB']->map(function($jugador) {
            return [
                'id' => $jugador->id,
                'nombre' => $jugador->nombre,
                'apellidos' => $jugador->apellidos
            ];
        })->toArray(); // <-- Añadir toArray() aquí
        
        return [
            'id' => $partido->id,
            'fecha' => $partido->fecha->format('Y-m-d'),
            'hora' => $partido->hora,
            'pista' => $partido->pista,
            'codLiga' => $partido->codLiga,
            'equipo_a' => $equipoAArray, // <-- Usar el array
            'equipo_b' => $equipoBArray, // <-- Usar el array
        ];
    }
    
    private function formatearResultado($resultado): array
    {
        return [
            'id' => $resultado->id,
            'partido_id' => $resultado->partido_id,
            'sets_ganados_equipo_a' => $resultado->sets_ganados_equipo_a,
            'sets_ganados_equipo_b' => $resultado->sets_ganados_equipo_b,
            'puntos_totales_equipo_a' => $resultado->puntos_totales_equipo_a,
            'puntos_totales_equipo_b' => $resultado->puntos_totales_equipo_b,
            'equipo_ganador' => $resultado->equipo_ganador,
            'resultado_sets' => $resultado->resultado_sets,
            'sets' => $resultado->sets->map(function($set) {
                return [
                    'numero_set' => $set->numero_set,
                    'puntos_equipo_a' => $set->puntos_equipo_a,
                    'puntos_equipo_b' => $set->puntos_equipo_b,
                    'resultado' => $set->resultado
                ];
            })
        ];
    }
    
    private function formatearEstadisticaRanking($estadistica, $posicion): array
    {
        return [
            'posicion' => $posicion,
            'usuario' => [
                'id' => $estadistica->usuario->id,
                'nombre' => $estadistica->usuario->nombre,
                'apellidos' => $estadistica->usuario->apellidos,
                'categoria' => $estadistica->usuario->categoria,
                'foto_perfil' => $estadistica->usuario->image_path
            ],
            'partidos_jugados' => $estadistica->partidos_jugados,
            'partidos_ganados' => $estadistica->partidos_ganados,
            'partidos_perdidos' => $estadistica->partidos_perdidos,
            'porcentaje_victorias' => $estadistica->porcentaje_victorias,
            'sets_ganados' => $estadistica->sets_ganados,
            'sets_perdidos' => $estadistica->sets_perdidos,
            'diferencia_sets' => $estadistica->diferencia_sets,
            'puntos_ranking' => $estadistica->puntos_ranking,
            'puntos_a_favor' => $estadistica->puntos_a_favor,
            'puntos_en_contra' => $estadistica->puntos_en_contra
        ];
    }
    
    private function formatearEstadisticaCompleta($estadistica): array
    {
        return [
            'usuario' => [
                'id' => $estadistica->usuario->id,
                'nombre' => $estadistica->usuario->nombre,
                'apellidos' => $estadistica->usuario->apellidos,
                'categoria' => $estadistica->usuario->categoria,
                'email' => $estadistica->usuario->email,
                'foto_perfil' => $estadistica->usuario->image_path
            ],
            'estadisticas' => [
                'partidos_jugados' => $estadistica->partidos_jugados,
                'partidos_ganados' => $estadistica->partidos_ganados,
                'partidos_perdidos' => $estadistica->partidos_perdidos,
                'porcentaje_victorias' => $estadistica->porcentaje_victorias,
                'sets_ganados' => $estadistica->sets_ganados,
                'sets_perdidos' => $estadistica->sets_perdidos,
                'diferencia_sets' => $estadistica->diferencia_sets,
                'puntos_a_favor' => $estadistica->puntos_a_favor,
                'puntos_en_contra' => $estadistica->puntos_en_contra,
                'diferencia_puntos' => $estadistica->diferencia_puntos,
                'promedio_puntos_por_partido' => $estadistica->promedio_puntos_por_partido,
                'puntos_ranking' => $estadistica->puntos_ranking
            ]
        ];
    }
    
    private function formatearPartidoConResultado($partido): array
    {
        $jugadores = $this->obtenerJugadoresPorEquipo($partido);
        
        return [
            'id'    => $partido->id,
            'fecha' => $partido->fecha->format('Y-m-d'),
            'hora'  => $partido->hora,
            'pista' => $partido->pista,
            'resultado' => $this->formatearResultado($partido->resultados),
            'equipo_a' => $jugadores['equipoA']->map(function($jugador) {
                return [
                    'id' => $jugador->id,
                    'nombre' => $jugador->full_name,
                    'apellidos' => $jugador->apellidos
                ];
            }),
            'equipo_b' => $jugadores['equipoB']->map(function($jugador) {
                return [
                    'id' => $jugador->id,
                    'nombre' => $jugador->full_name,
                    'apellidos' => $jugador->apellidos
                ];
            })
        ];
    }
    
    private function formatearPartidoJugador($partido, $usuarioId): array
    {
        $resultado = $partido->resultados;
        $jugadores = $this->obtenerJugadoresPorEquipo($partido);
        
        // Determinar si el usuario estaba en equipo A o B
        $usuarioEnEquipoA = $jugadores['equipoA']->contains('id', $usuarioId);
        $usuarioEnEquipoB = $jugadores['equipoB']->contains('id', $usuarioId);
        
        $gano = false;
        if ($usuarioEnEquipoA && $resultado->equipo_ganador == 'A') {
            $gano = true;
        } elseif ($usuarioEnEquipoB && $resultado->equipo_ganador == 'B') {
            $gano = true;
        }
        
        return [
            'id' => $partido->id,
            'fecha' => $partido->fecha->format('Y-m-d'),
            'hora' => $partido->hora,
            'pista' => $partido->pista,
            'resultado' => "{$resultado->sets_ganados_equipo_a}-{$resultado->sets_ganados_equipo_b}",
            'gano' => $gano,
            'equipo_propio' => $usuarioEnEquipoA ? 'A' : 'B',
            'equipo_rival' => $usuarioEnEquipoA ? 'B' : 'A',
            'sets' => $resultado->sets->map(function($set) {
                return [
                    'numero' => $set->numero_set,
                    'resultado' => $set->resultado
                ];
            })
        ];
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

    // GET /api/partidos/pendientes-resultados
    public function obtenerPartidosPendientesResultados(Request $request, Response $response)
    {
        try {
            $userId = $request->getAttribute('user_id');

            $partidos = Partido::query()
                // El usuario está inscrito en el partido
                ->whereHas('inscripciones', function ($q) use ($userId) {
                    $q->where('user_id', $userId)->where('estado', 'confirmado');
                })

                // Partido finalizado y de liga
                // ->where('estado', 'finalizado')
                ->whereNotNull('codLiga')

                // Sin resultados aún
                // ->whereDoesntHave('resultados')

                /* // Mínimo 2 jugadores confirmados
                // ->whereHas('inscripciones', function ($q) {
                //     $q->where('estado', 'confirmado');
                // }, '>=', 2) */

                // Cargar relaciones necesarias
                ->with([
                    'creador',
                    'inscripciones' => function ($q) {
                        $q->where('estado', 'confirmado')
                        ->with('usuario');
                    }
                ])

                // Ordenar por fecha (más recientes primero)
                ->orderBy('fecha', 'desc')

                ->get()
                ->map(function ($partido) {
                    return $this->formatearPartidoParaResultados($partido);
                });

            return $this->successResponse($response, [
                'partidos' => $partidos,
                'total'    => $partidos->count()
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse(
                $response,
                'Error al obtener partidos pendientes: ' . $e->getMessage()
            );
        }
    }

    private function formatearPartidoParaResultados($partido): array
    {
        $jugadores = $this->obtenerJugadoresPorEquipo($partido);
        
        // Asegurar que convertimos las colecciones a arrays
        $equipoA = $jugadores['equipoA']
            ? $jugadores['equipoA']->map(function($jugador) {
                return [
                    'id'        => $jugador->id ?? $jugador['id'] ?? '',
                    'nombre'    => $jugador->full_name ?? $jugador['full_name'] ?? 'Jugador',
                    'apellidos' => $jugador->apellidos ?? $jugador['apellidos'] ?? ''
                ];
            })->toArray()
            : (array) $jugadores['equipoA'];
            
        $equipoB = $jugadores['equipoB']
            ? $jugadores['equipoB']->map(function($jugador) {
                return [
                    'id'        => $jugador->id ?? $jugador['id'] ?? '',
                    'nombre'    => $jugador->full_name ?? $jugador['full_name'] ?? 'Jugador',
                    'apellidos' => $jugador->apellidos ?? $jugador['apellidos'] ?? ''
                ];
            })->toArray()
            : (array) $jugadores['equipoB'];
        
        return [
            'id'       => $partido->id,
            'fecha'    => $partido->fecha->format('Y-m-d'),
            'hora'     => $partido->hora,
            'pista'    => $partido->pista,
            'codLiga'  => $partido->codLiga,
            'equipo_a' => $equipoA,
            'equipo_b' => $equipoB,
        ];
    }

    private function obtenerJugadoresPorEquipo($partido): array
    {
        $jugadores = $partido->inscripciones
            ->where('estado', 'confirmado')
            ->sortBy('id')
            ->values();
        
        $equipoA = collect();
        $equipoB = collect();
        
        foreach ($jugadores as $index => $inscripcion) {
            // Posiciones pares (0, 2) -> Equipo A
            // Posiciones impares (1, 3) -> Equipo B
            if ($index % 2 == 0) {
                $equipoA->push($inscripcion->usuario);
            } else {
                $equipoB->push($inscripcion->usuario);
            }
        }
        
        return [
            'equipoA' => $equipoA,
            'equipoB' => $equipoB
        ];
    }
}