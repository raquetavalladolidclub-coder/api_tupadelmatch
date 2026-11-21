<?php

namespace PadelClub\Services;

use PadelClub\Models\LigaHead;
use PadelClub\Models\LigaHeadJornada;
use PadelClub\Models\LigaHeadPartido;
use PadelClub\Models\LigaHeadSet;

/**
 * Reglas de la LIGA HEAD: generación del calendario y clasificación.
 */
class LigaHeadService
{
    /**
     * Genera jornadas y partidos (todos contra todos) para una liga.
     *
     * Sistema de rotación: se fija la primera pareja y se rotan las demás,
     * así ningún par se repite y cada pareja descansa una jornada si son impares.
     *
     * @return array{jornadas:int, partidos:int}
     */
    public function generarCalendario(LigaHead $liga, ?string $categoria = null): array
    {
        $categoria = $categoria ?: ($liga->categoriasLista()[0] ?? $liga->categoria);

        $ids = $liga->parejasConfirmadas()
            ->when($categoria, fn ($q) => $q->where('categoria', $categoria))
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if (count($ids) < 2) {
            throw new \RuntimeException('Hacen falta al menos 2 parejas confirmadas.');
        }

        // Si son impares, añadimos un hueco para que una pareja descanse.
        if (count($ids) % 2 !== 0) {
            $ids[] = null;
        }

        $rondas = $this->rondasRoundRobin($ids);

        if ($liga->ida_vuelta) {
            $vuelta = [];
            foreach ($rondas as $ronda) {
                $invertida = [];
                foreach ($ronda as [$a, $b]) {
                    $invertida[] = [$b, $a];
                }
                $vuelta[] = $invertida;
            }
            $rondas = array_merge($rondas, $vuelta);
        }

        // Se borra solo lo de esta categoría (solo si no hay resultados confirmados).
        $jornadasCategoria = $liga->jornadas()
            ->when($categoria, fn ($q) => $q->where('categoria', $categoria))
            ->pluck('id')
            ->all();

        $hayConfirmados = $liga->partidos()
            ->whereIn('jornada_id', $jornadasCategoria ?: [0])
            ->where('confirmado_club', 1)
            ->exists();
        if ($hayConfirmados) {
            throw new \RuntimeException('Ya hay partidos con resultado confirmado: no se puede regenerar.');
        }

        \PadelClub\Models\LigaHeadPartido::whereIn('jornada_id', $jornadasCategoria ?: [0])->delete();
        \PadelClub\Models\LigaHeadJornada::whereIn('id', $jornadasCategoria ?: [0])->delete();

        $inicio = new \DateTime($liga->fecha_inicio);
        $partidosCreados = 0;

        foreach ($rondas as $indice => $ronda) {
            $desde = (clone $inicio)->modify('+' . (7 * $indice) . ' days');
            $hasta = (clone $desde)->modify('+6 days');

            $jornada = LigaHeadJornada::create([
                'liga_id'     => $liga->id,
                'categoria'   => $categoria,
                'numero'      => $indice + 1,
                'fecha_desde' => $desde->format('Y-m-d'),
                'fecha_hasta' => $hasta->format('Y-m-d'),
            ]);

            foreach ($ronda as [$a, $b]) {
                LigaHeadPartido::create([
                    'liga_id'     => $liga->id,
                    'jornada_id'  => $jornada->id,
                    'pareja_a_id' => $a,
                    'pareja_b_id' => $b,
                    'estado'      => 'pendiente',
                ]);
                $partidosCreados++;
            }
        }

        // Se guarda el máximo de jornadas entre las categorías ya generadas.
        $maxJornadas = (int) LigaHeadJornada::where('liga_id', $liga->id)->max('numero');
        $liga->update([
            'jornadas' => $maxJornadas,
            'estado'   => 'en_curso',
        ]);

        return ['jornadas' => count($rondas), 'partidos' => $partidosCreados, 'categoria' => $categoria];
    }

    /**
     * Emparejamientos de una vuelta con el método de rotación.
     *
     * @param array<int|null> $ids
     * @return array<int, array<int, array{0:int,1:int}>>
     */
    private function rondasRoundRobin(array $ids): array
    {
        $total = count($ids);
        $rondas = [];
        $rotacion = $ids;

        for ($r = 0; $r < $total - 1; $r++) {
            $ronda = [];
            for ($i = 0; $i < $total / 2; $i++) {
                $a = $rotacion[$i];
                $b = $rotacion[$total - 1 - $i];
                if ($a !== null && $b !== null) {
                    $ronda[] = [$a, $b];
                }
            }
            $rondas[] = $ronda;

            // Rotar dejando fija la primera posición: la última pasa al segundo
            // puesto. (Si solo se mueve al final, la rotación no avanza y se
            // repiten los mismos emparejamientos.)
            $primera = array_shift($rotacion);
            $ultima = array_pop($rotacion);
            array_unshift($rotacion, $primera);
            array_splice($rotacion, 1, 0, [$ultima]);
        }

        return $rondas;
    }

    /**
     * Clasificación: solo cuentan los partidos confirmados por el club.
     *
     * @return array<int, array<string, mixed>>
     */
    public function clasificacion(LigaHead $liga, ?string $categoria = null): array
    {
        $parejas = $liga->parejasConfirmadas()
            ->when($categoria, fn ($q) => $q->where('categoria', $categoria))
            ->with(['jugador1', 'jugador2'])
            ->get();

        $tabla = [];
        foreach ($parejas as $pareja) {
            $tabla[$pareja->id] = [
                'pareja_id' => $pareja->id,
                'pareja'    => $this->nombrePareja($pareja),
                'jugadores' => $this->nombresJugadores($pareja),
                'pj' => 0, 'pg' => 0, 'pp' => 0,
                'sf' => 0, 'sc' => 0,
                'puntos' => 0,
            ];
        }

        $jornadas = $liga->jornadas()
            ->when($categoria, fn ($q) => $q->where('categoria', $categoria))
            ->pluck('id')
            ->all();

        $partidos = $liga->partidos()
            ->whereIn('jornada_id', $jornadas ?: [0])
            ->where('confirmado_club', 1)
            ->with('sets')
            ->get();

        foreach ($partidos as $partido) {
            $a = $partido->pareja_a_id;
            $b = $partido->pareja_b_id;
            if (!isset($tabla[$a]) || !isset($tabla[$b])) {
                continue;
            }

            $setsA = 0;
            $setsB = 0;
            foreach ($partido->sets as $set) {
                if ($set->games_a > $set->games_b) {
                    $setsA++;
                } elseif ($set->games_b > $set->games_a) {
                    $setsB++;
                }
            }

            $tabla[$a]['pj']++;
            $tabla[$b]['pj']++;
            $tabla[$a]['sf'] += $setsA;
            $tabla[$a]['sc'] += $setsB;
            $tabla[$b]['sf'] += $setsB;
            $tabla[$b]['sc'] += $setsA;

            if ($setsA > $setsB) {
                $tabla[$a]['pg']++;
                $tabla[$a]['puntos'] += 3;
                $tabla[$b]['pp']++;
            } elseif ($setsB > $setsA) {
                $tabla[$b]['pg']++;
                $tabla[$b]['puntos'] += 3;
                $tabla[$a]['pp']++;
            }
        }

        $filas = array_values($tabla);
        usort($filas, function ($x, $y) {
            if ($x['puntos'] !== $y['puntos']) {
                return $y['puntos'] <=> $x['puntos'];
            }
            $diffX = $x['sf'] - $x['sc'];
            $diffY = $y['sf'] - $y['sc'];
            if ($diffX !== $diffY) {
                return $diffY <=> $diffX;
            }
            return $x['pareja'] <=> $y['pareja'];
        });

        foreach ($filas as $i => &$fila) {
            $fila['posicion'] = $i + 1;
        }

        return $filas;
    }

    /** Nombre corto de cada jugador de la pareja. */
    public function nombresJugadores($pareja): array
    {
        if (!$pareja) {
            return ['Jugador', 'Jugador'];
        }

        $corto = function ($user) {
            $nombre = trim((string) ($user->fullName ?? '') ?: (string) ($user->username ?? ''));
            if ($nombre === '') {
                return 'Jugador';
            }
            $partes = preg_split('/\s+/', $nombre);
            if (count($partes) >= 3) {
                return $partes[0] . ' ' . $partes[2];
            }
            return $nombre;
        };

        return [$corto($pareja->jugador1), $corto($pareja->jugador2)];
    }

    public function nombrePareja($pareja): string
    {
        // Una pareja puede haberse dado de baja y dejar partidos huérfanos.
        if (!$pareja) {
            return 'Pareja pendiente';
        }

        $j1 = $pareja->jugador1;
        $j2 = $pareja->jugador2;
        $n1 = $j1 ? ($j1->fullName ?: $j1->username) : 'Jugador';
        $n2 = $j2 ? ($j2->fullName ?: $j2->username) : 'Jugador';

        // Solo el primer apellido de cada uno, como en el diseño.
        $corto = function ($nombre) {
            $partes = preg_split('/\s+/', trim((string) $nombre));
            if (count($partes) >= 3) {
                return $partes[0] . ' ' . $partes[2];
            }
            return $nombre;
        };

        return $corto($n1) . ' / ' . $corto($n2);
    }
}
