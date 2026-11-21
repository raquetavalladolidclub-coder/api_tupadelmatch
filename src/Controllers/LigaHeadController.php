<?php

namespace PadelClub\Controllers;

use PadelClub\Models\LigaHead;
use PadelClub\Models\LigaHeadCategoria;
use PadelClub\Models\LigaHeadJornada;
use PadelClub\Models\LigaHeadPartido;
use PadelClub\Models\LigaHeadPareja;
use PadelClub\Models\LigaHeadSet;
use PadelClub\Models\User;
use PadelClub\Services\LigaHeadService;
use PadelClub\Services\PushNotifier;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * LIGA HEAD: liga por parejas, todos contra todos, una jornada por semana.
 */
class LigaHeadController
{
    private $service;
    private $push;

    public function __construct()
    {
        $this->service = new LigaHeadService();
        $this->push = new PushNotifier();
    }

    // ------------------------------------------------------------------
    // App
    // ------------------------------------------------------------------

    /** Ligas visibles para el jugador (con su estado de inscripción). */
    public function index(Request $request, Response $response)
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $query = LigaHead::query()
            ->whereIn('estado', ['inscripcion', 'cerrada', 'en_curso', 'finalizada'])
            ->orderByDesc('inscripcion_desde');

        if (!empty($params['categoria'])) {
            $cat = strtolower(trim($params['categoria']));
            $query->where(function ($q) use ($cat) {
                $q->where('categoria', $cat)
                  ->orWhereHas('categorias', fn ($sub) => $sub->where('categoria', $cat));
            });
        }
        if (!empty($params['genero'])) {
            $query->where('genero', strtolower(trim($params['genero'])));
        }

        $ligas = $query->get()->map(function (LigaHead $liga) use ($userId) {
            return $this->formatearLiga($liga, $userId);
        })->values();

        return $this->successResponse($response, ['ligas' => $ligas]);
    }

    /** Detalle de la liga: jornadas, parejas y clasificación. */
    public function detalle(Request $request, Response $response, $args)
    {
        $userId = $request->getAttribute('user_id');
        $liga = LigaHead::find((int) $args['id']);

        if (!$liga) {
            return $this->errorResponse($response, 'Liga no encontrada', 404);
        }

        $pareja = $liga->parejaDe($userId);

        $categoria = $this->categoriaSolicitada($request, $liga);

        $parejas = $liga->parejas()->with(['jugador1', 'jugador2'])
            ->where('categoria', $categoria)
            ->whereIn('estado', ['confirmada', 'pendiente'])->orderBy('id')->get()
            ->map(fn ($p) => $this->formatearPareja($p))->values();

        return $this->successResponse($response, [
            'liga'          => $this->formatearLiga($liga, $userId),
            'mi_pareja'     => $pareja ? $this->formatearPareja($pareja) : null,
            'categoria_actual' => $categoria,
            'parejas'       => $parejas,
            'jornadas'      => $this->jornadas($liga, $categoria),
            'clasificacion' => $this->clasificacionConFotos($liga, $categoria),
        ]);
    }

    /** Inscribirse con un compañero. */
    public function inscribir(Request $request, Response $response, $args)
    {
        $userId = $request->getAttribute('user_id');
        $data   = $request->getParsedBody() ?? [];
        $companeroId = (int) ($data['companero_id'] ?? 0);

        $liga = LigaHead::find((int) $args['id']);
        if (!$liga) {
            return $this->errorResponse($response, 'Liga no encontrada', 404);
        }

        if (!$liga->inscripcionesAbiertas()) {
            return $this->errorResponse($response, 'El plazo de inscripción está cerrado');
        }

        if ($companeroId === 0 || $companeroId === (int) $userId) {
            return $this->errorResponse($response, 'Elige a tu compañero de pareja');
        }

        $jugador    = User::find($userId);
        $companero  = User::find($companeroId);
        if (!$jugador || !$companero) {
            return $this->errorResponse($response, 'Jugador no encontrado', 404);
        }

        // Nadie puede estar en dos parejas de la misma liga.
        foreach ([$userId, $companeroId] as $id) {
            if ($liga->parejaDe($id)) {
                return $this->errorResponse($response, 'Ese jugador ya está apuntado en esta liga');
            }
        }

        // Categoría: la de los jugadores, y tiene que jugarse en esta liga.
        $categoriaLiga = strtolower((string) ($jugador->categoria ?? ''));
        $categorias = $liga->categoriasLista();

        if ($categoriaLiga === '' || !in_array($categoriaLiga, $categorias, true)) {
            return $this->errorResponse($response, 'Tu categoría no se juega en esta liga');
        }
        if (strtolower((string) ($companero->categoria ?? '')) !== $categoriaLiga) {
            return $this->errorResponse($response, 'Los dos jugadores deben ser de la categoría ' . ucfirst($categoriaLiga));
        }
        if ($liga->genero !== 'mixto') {
            foreach ([$jugador, $companero] as $jug) {
                if (strtolower((string) ($jug->genero ?? 'mixto')) !== $liga->genero) {
                    return $this->errorResponse($response, 'Esta liga es ' . $liga->genero);
                }
            }
        }

        // Plazas de esa categoría.
        $ocupadas = $liga->parejas()
            ->where('categoria', $categoriaLiga)
            ->whereIn('estado', ['pendiente', 'confirmada'])
            ->count();
        if ($ocupadas >= (int) $liga->plazas_max) {
            return $this->errorResponse($response, 'La categoría ' . ucfirst($categoriaLiga) . ' está completa');
        }

        $pareja = LigaHeadPareja::create([
            'liga_id'     => $liga->id,
            'categoria'   => $categoriaLiga,
            'jugador1_id' => (int) $userId,
            'jugador2_id' => $companeroId,
            'estado'      => 'pendiente',
        ]);

        return $this->successResponse($response, [
            'message' => 'Inscripción registrada. Se confirmará cuando el club reciba el pago.',
            'pareja'  => $this->formatearPareja($pareja),
        ], 201);
    }

    /** Calendario: jornadas con sus partidos. */
    public function calendario(Request $request, Response $response, $args)
    {
        $liga = LigaHead::find((int) $args['id']);
        if (!$liga) {
            return $this->errorResponse($response, 'Liga no encontrada', 404);
        }

        $categoria = $this->categoriaSolicitada($request, $liga);

        return $this->successResponse($response, [
            'liga'      => $this->formatearLiga($liga, $request->getAttribute('user_id')),
            'categoria' => $categoria,
            'jornadas'  => $this->jornadas($liga, $categoria),
        ]);
    }

    /** Añade las fotos de cada pareja a las filas de clasificación. */
    private function clasificacionConFotos(LigaHead $liga, ?string $categoria = null): array
    {
        $filas = $this->service->clasificacion($liga, $categoria);
        $fotos = [];
        $ids = [];
        $parejas = $liga->parejasConfirmadas()
            ->when($categoria, fn ($q) => $q->where('categoria', $categoria))
            ->with(['jugador1', 'jugador2'])
            ->get();
        foreach ($parejas as $pareja) {
            $fotos[$pareja->id] = $this->fotosPareja($pareja);
            $ids[$pareja->id] = [(int) $pareja->jugador1_id, (int) $pareja->jugador2_id];
        }
        foreach ($filas as &$fila) {
            $fila['fotos'] = $fotos[$fila['pareja_id']] ?? [];
            $fila['jugadores_ids'] = $ids[$fila['pareja_id']] ?? [];
        }
        return $filas;
    }

    /** Clasificación. */
    public function clasificacion(Request $request, Response $response, $args)
    {
        $liga = LigaHead::find((int) $args['id']);
        if (!$liga) {
            return $this->errorResponse($response, 'Liga no encontrada', 404);
        }

        $categoria = $this->categoriaSolicitada($request, $liga);

        return $this->successResponse($response, [
            'liga'          => $this->formatearLiga($liga, $request->getAttribute('user_id')),
            'categoria'     => $categoria,
            'clasificacion' => $this->clasificacionConFotos($liga, $categoria),
        ]);
    }

    /** Un jugador de la pareja propone el resultado. */
    public function proponerResultado(Request $request, Response $response, $args)
    {
        $userId  = $request->getAttribute('user_id');
        $data    = $request->getParsedBody() ?? [];
        $sets    = $data['sets'] ?? null;

        $partido = LigaHeadPartido::find((int) $args['id']);
        if (!$partido) {
            return $this->errorResponse($response, 'Partido no encontrado', 404);
        }

        $pareja = LigaHeadPareja::find($partido->pareja_a_id);
        $parejaB = LigaHeadPareja::find($partido->pareja_b_id);
        $esJugador = in_array((int) $userId, [
            (int) $pareja->jugador1_id, (int) $pareja->jugador2_id,
            (int) $parejaB->jugador1_id, (int) $parejaB->jugador2_id,
        ], true);

        if (!$esJugador) {
            return $this->errorResponse($response, 'No juegas este partido', 403);
        }

        if ($partido->confirmado_club) {
            return $this->errorResponse($response, 'El resultado ya está confirmado');
        }

        if (!is_array($sets) || count($sets) === 0 || count($sets) > 3) {
            return $this->errorResponse($response, 'Manda entre 1 y 3 sets');
        }

        $partido->sets()->delete();
        $numero = 1;
        foreach ($sets as $set) {
            $a = (int) ($set['a'] ?? -1);
            $b = (int) ($set['b'] ?? -1);
            if ($a < 0 || $b < 0) {
                return $this->errorResponse($response, 'Games inválidos');
            }
            LigaHeadSet::create([
                'partido_id' => $partido->id,
                'numero'     => $numero++,
                'games_a'    => $a,
                'games_b'    => $b,
            ]);
        }

        $partido->update([
            'estado'            => 'jugado',
            'propuesto_por'     => (int) $userId,
            'propuesto_at'      => date('Y-m-d H:i:s'),
            'validado_rival'    => 0,
            'validado_rival_at' => null,
            'confirmado_club'   => 0,
            'confirmado_por'    => null,
            'confirmado_at'     => null,
        ]);

        $rival = ((int) $partido->pareja_a_id === (int) ($pareja->id ?? 0)) ? $parejaB : $pareja;
        $this->push->aPareja(
            $rival,
            'Resultado pendiente de validar',
            'Hay un resultado nuevo en tu partido de Liga Head. Revísalo en la app.',
            ['liga_id' => (int) $partido->liga_id]
        );

        return $this->successResponse($response, [
            'message' => 'Resultado enviado. La pareja rival tiene 2 horas para validarlo.',
            'partido' => $this->formatearPartido($partido->fresh()),
        ]);
    }

    /** La pareja rival valida el resultado. */
    public function validarResultado(Request $request, Response $response, $args)
    {
        $userId  = $request->getAttribute('user_id');
        $partido = LigaHeadPartido::find((int) $args['id']);

        if (!$partido) {
            return $this->errorResponse($response, 'Partido no encontrado', 404);
        }

        $parejaA = LigaHeadPareja::find($partido->pareja_a_id);
        $parejaB = LigaHeadPareja::find($partido->pareja_b_id);
        $ids = [
            (int) $parejaA->jugador1_id, (int) $parejaA->jugador2_id,
            (int) $parejaB->jugador1_id, (int) $parejaB->jugador2_id,
        ];

        if (!in_array((int) $userId, $ids, true)) {
            return $this->errorResponse($response, 'No juegas este partido', 403);
        }
        if ((int) $partido->propuesto_por === (int) $userId) {
            return $this->errorResponse($response, 'El resultado lo tiene que validar la pareja rival');
        }
        if ($partido->confirmado_club) {
            return $this->errorResponse($response, 'El resultado ya está confirmado');
        }

        $partido->update([
            'validado_rival'    => 1,
            'validado_rival_at' => date('Y-m-d H:i:s'),
        ]);

        $this->push->aUsuario(
            (int) $partido->propuesto_por,
            'Resultado validado',
            'La pareja rival ha validado el resultado. Falta que el club lo confirme.',
            ['liga_id' => (int) $partido->liga_id]
        );

        return $this->successResponse($response, [
            'message' => 'Resultado validado. Falta la confirmación del club.',
            'partido' => $this->formatearPartido($partido->fresh()),
        ]);
    }


    /** Jugadores de la categoría de la liga que aún no tienen pareja. */
    public function companeros(Request $request, Response $response, $args)
    {
        $userId = (int) $request->getAttribute('user_id');
        $liga   = LigaHead::find((int) $args['id']);

        if (!$liga) {
            return $this->errorResponse($response, 'Liga no encontrada', 404);
        }

        $ocupados = $liga->parejas()
            ->whereIn('estado', ['pendiente', 'confirmada'])
            ->get()
            ->flatMap(fn ($p) => [(int) $p->jugador1_id, (int) $p->jugador2_id])
            ->all();

        $categoria = $this->categoriaSolicitada($request, $liga);

        $sql = User::query()
            ->where('id', '!=', $userId)
            ->where('categoria', $categoria)
            ->whereNotIn('id', $ocupados ?: [0]);

        if ($liga->genero !== 'mixto') {
            $sql->where('genero', $liga->genero);
        }

        $jugadores = $sql->orderBy('fullName')->get()->map(function (User $u) {
            return [
                'id'     => (int) $u->id,
                'nombre' => $u->fullName ?: $u->username,
                'categoria' => $u->categoria,
                'genero' => $u->genero,
                'foto'   => $u->imagePath,
            ];
        })->values();

        return $this->successResponse($response, ['jugadores' => $jugadores]);
    }

    // ------------------------------------------------------------------
    // Club (admin)
    // ------------------------------------------------------------------

    /** Crear una liga. */
    public function crear(Request $request, Response $response)
    {
        if (!$this->esAdmin($request)) {
            return $this->errorResponse($response, 'Solo el club puede crear ligas', 403);
        }

        $data = $request->getParsedBody() ?? [];
        foreach (['nombre', 'inscripcion_desde', 'inscripcion_hasta', 'fecha_inicio'] as $campo) {
            if (empty($data[$campo])) {
                return $this->errorResponse($response, 'Falta el campo ' . $campo);
            }
        }

        $categorias = $data['categorias'] ?? ($data['categoria'] ?? []);
        if (is_string($categorias)) {
            $categorias = [$categorias];
        }
        $categorias = array_values(array_filter(array_map(
            fn ($c) => strtolower(trim((string) $c)),
            is_array($categorias) ? $categorias : []
        )));
        if (empty($categorias)) {
            return $this->errorResponse($response, 'Indica al menos una categoría');
        }

        $liga = LigaHead::create([
            'nombre'            => (string) $data['nombre'],
            'genero'            => in_array(($data['genero'] ?? 'mixto'), ['masculino', 'femenino', 'mixto'], true) ? $data['genero'] : 'mixto',
            'categoria'         => $categorias[0],
            'precio'            => (float) ($data['precio'] ?? 20),
            'plazas_max'        => (int) ($data['plazas_max'] ?? 16),
            'plazas_min'        => (int) ($data['plazas_min'] ?? 4),
            'inscripcion_desde' => $data['inscripcion_desde'],
            'inscripcion_hasta' => $data['inscripcion_hasta'],
            'fecha_inicio'      => $data['fecha_inicio'],
            'ida_vuelta'        => !empty($data['ida_vuelta']),
            'reglamento'        => $data['reglamento'] ?? null,
            'estado'            => 'inscripcion',
            'creado_por'        => (int) $request->getAttribute('user_id'),
        ]);

        foreach ($categorias as $cat) {
            LigaHeadCategoria::firstOrCreate(['liga_id' => $liga->id, 'categoria' => $cat]);
        }

        return $this->successResponse($response, [
            'message' => 'Liga creada',
            'liga'    => $this->formatearLiga($liga->fresh(), null),
        ], 201);
    }

    /** Cerrar inscripciones y generar el calendario. */
    public function generar(Request $request, Response $response, $args)
    {
        if (!$this->esAdmin($request)) {
            return $this->errorResponse($response, 'Solo el club puede generar el calendario', 403);
        }

        $liga = LigaHead::find((int) $args['id']);
        if (!$liga) {
            return $this->errorResponse($response, 'Liga no encontrada', 404);
        }

        $data = $request->getParsedBody() ?? [];
        $pedida = strtolower(trim((string) ($data['categoria'] ?? '')));
        $categorias = $liga->categoriasLista();
        $aGenerar = ($pedida !== '' && in_array($pedida, $categorias, true)) ? [$pedida] : $categorias;

        $resumen = ['jornadas' => 0, 'partidos' => 0, 'categorias' => []];
        foreach ($aGenerar as $categoria) {
            try {
                $resultado = $this->service->generarCalendario($liga, $categoria);
                $resumen['jornadas'] = max($resumen['jornadas'], $resultado['jornadas']);
                $resumen['partidos'] += $resultado['partidos'];
                $resumen['categorias'][] = $categoria;
            } catch (\RuntimeException $e) {
                if (count($aGenerar) === 1) {
                    return $this->errorResponse($response, $e->getMessage());
                }
            }
        }

        if (empty($resumen['categorias'])) {
            return $this->errorResponse($response, 'No hay parejas confirmadas suficientes para generar el calendario');
        }

        foreach ($resumen['categorias'] as $categoriaGenerada) {
            $jugadores = [];
            foreach ($liga->parejasConfirmadas()->where('categoria', $categoriaGenerada)->get() as $pareja) {
                $jugadores[] = (int) $pareja->jugador1_id;
                $jugadores[] = (int) $pareja->jugador2_id;
            }
            if ($jugadores) {
                $this->push->aUsuarios(
                    $jugadores,
                    'Calendario publicado',
                    'Ya está el calendario de ' . ucfirst($categoriaGenerada) . ' en la Liga Head. Mira tu jornada en la app.',
                    ['liga_id' => (int) $liga->id]
                );
            }
        }

        return $this->successResponse($response, [
            'message'  => 'Calendario generado',
            'jornadas' => $resumen['jornadas'],
            'partidos' => $resumen['partidos'],
            'liga'     => $this->formatearLiga($liga->fresh(), null),
        ]);
    }

    /** Confirmar o rechazar una pareja (tras el pago en el club). */
    public function cambiarEstadoPareja(Request $request, Response $response, $args)
    {
        if (!$this->esAdmin($request)) {
            return $this->errorResponse($response, 'Solo el club puede confirmar inscripciones', 403);
        }

        $data   = $request->getParsedBody() ?? [];
        $estado = strtolower((string) ($data['estado'] ?? ''));
        if (!in_array($estado, ['confirmada', 'rechazada'], true)) {
            return $this->errorResponse($response, 'Estado no válido');
        }

        $pareja = LigaHeadPareja::find((int) $args['id']);
        if (!$pareja) {
            return $this->errorResponse($response, 'Pareja no encontrada', 404);
        }

        if ($estado === 'confirmada') {
            $liga = $pareja->liga;
            $confirmadas = $liga->parejas()->where('estado', 'confirmada')->count();
            if ($confirmadas >= (int) $liga->plazas_max) {
                return $this->errorResponse($response, 'La liga está completa');
            }
        }

        $pareja->update([
            'estado'         => $estado,
            'confirmada_por' => (int) $request->getAttribute('user_id'),
            'confirmada_at'  => date('Y-m-d H:i:s'),
        ]);

        return $this->successResponse($response, [
            'message' => $estado === 'confirmada' ? 'Pareja confirmada' : 'Pareja rechazada',
            'pareja'  => $this->formatearPareja($pareja->fresh()),
        ]);
    }

    /** El club da por bueno el resultado propuesto. */
    public function confirmarResultado(Request $request, Response $response, $args)
    {
        if (!$this->esAdmin($request)) {
            return $this->errorResponse($response, 'Solo el club puede confirmar resultados', 403);
        }

        $partido = LigaHeadPartido::find((int) $args['id']);
        if (!$partido) {
            return $this->errorResponse($response, 'Partido no encontrado', 404);
        }
        if ($partido->sets()->count() === 0) {
            return $this->errorResponse($response, 'Ese partido no tiene resultado');
        }

        $partido->update([
            'estado'          => 'jugado',
            'confirmado_club' => 1,
            'confirmado_por'  => (int) $request->getAttribute('user_id'),
            'confirmado_at'   => date('Y-m-d H:i:s'),
        ]);

        return $this->successResponse($response, [
            'message' => 'Resultado confirmado',
            'partido' => $this->formatearPartido($partido->fresh()),
        ]);
    }

    /** Listado de parejas para el club. */
    public function parejas(Request $request, Response $response, $args)
    {
        if (!$this->esAdmin($request)) {
            return $this->errorResponse($response, 'Solo el club puede ver las inscripciones', 403);
        }

        $liga = LigaHead::find((int) $args['id']);
        if (!$liga) {
            return $this->errorResponse($response, 'Liga no encontrada', 404);
        }

        $cat = strtolower(trim((string) ($request->getQueryParams()['categoria'] ?? '')));

        $parejas = $liga->parejas()
            ->when($cat !== '', fn ($q) => $q->where('categoria', $cat))
            ->with(['jugador1', 'jugador2'])->orderBy('id')->get()
            ->map(fn ($p) => $this->formatearPareja($p))->values();

        return $this->successResponse($response, [
            'liga'    => $this->formatearLiga($liga, null),
            'parejas' => $parejas,
        ]);
    }

    // ------------------------------------------------------------------
    // Formato
    // ------------------------------------------------------------------

    private function formatearLiga(LigaHead $liga, $userId): array
    {
        $ocupadas   = $liga->parejas()->whereIn('estado', ['pendiente', 'confirmada'])->count();
        $confirmadas = $liga->parejas()->where('estado', 'confirmada')->count();
        $miPareja = $userId ? $liga->parejaDe($userId) : null;

        return [
            'id'                => $liga->id,
            'nombre'            => $liga->nombre,
            'genero'            => $liga->genero,
            'categoria'         => $liga->categoria,
            'categorias'        => $liga->categoriasLista(),
            'precio'            => number_format((float) $liga->precio, 2, ',', '.') . ' €',
            'plazas_max'        => (int) $liga->plazas_max,
            'plazas_min'        => (int) $liga->plazas_min,
            'inscripcion_desde' => $liga->inscripcion_desde,
            'inscripcion_hasta' => $liga->inscripcion_hasta,
            'fecha_inicio'      => $liga->fecha_inicio,
            'ida_vuelta'        => (bool) $liga->ida_vuelta,
            'jornadas'          => (int) ($liga->jornadas ?? 0),
            'reglamento'        => $liga->reglamento,
            'imagen'            => $this->urlImagen($liga->imagen ?? ''),
            'estado'            => $liga->estado,
            'parejas_inscritas' => $ocupadas,
            'parejas_confirmadas' => $confirmadas,
            'plazas_libres'     => max(0, (int) $liga->plazas_max - $ocupadas),
            'inscripcion_abierta' => $liga->inscripcionesAbiertas(),
            'inscrito'          => $miPareja !== null,
            'mi_pareja_estado'  => $miPareja->estado ?? null,
            'mi_categoria'      => $miPareja->categoria ?? null,
        ];
    }

    private function formatearPareja(LigaHeadPareja $pareja): array
    {
        return [
            'id'         => $pareja->id,
            'liga_id'    => $pareja->liga_id,
            'categoria'  => $pareja->categoria,
            'nombre'     => $this->service->nombrePareja($pareja->load(['jugador1', 'jugador2'])),
            'jugador1'   => [
                'id'     => $pareja->jugador1_id,
                'nombre' => $pareja->jugador1->fullName ?? $pareja->jugador1->username ?? '',
                'foto'   => $this->urlFoto($pareja->jugador1->imagePath ?? ''),
            ],
            'jugador2'   => [
                'id'     => $pareja->jugador2_id,
                'nombre' => $pareja->jugador2->fullName ?? $pareja->jugador2->username ?? '',
                'foto'   => $this->urlFoto($pareja->jugador2->imagePath ?? ''),
            ],
            'fotos'      => [
                $this->urlFoto($pareja->jugador1->imagePath ?? ''),
                $this->urlFoto($pareja->jugador2->imagePath ?? ''),
            ],
            'estado'     => $pareja->estado,
        ];
    }

    private function jornadas(LigaHead $liga, ?string $categoria = null): array
    {
        $jornadas = $liga->jornadas()
            ->when($categoria, fn ($q) => $q->where('categoria', $categoria))
            ->with(['partidos.sets', 'partidos.parejaA.jugador1', 'partidos.parejaA.jugador2',
            'partidos.parejaB.jugador1', 'partidos.parejaB.jugador2'])->get();

        return $jornadas->map(function (LigaHeadJornada $jornada) {
            return [
                'id'          => $jornada->id,
                'numero'      => $jornada->numero,
                'fecha_desde' => $jornada->fecha_desde,
                'fecha_hasta' => $jornada->fecha_hasta,
                'partidos'    => $jornada->partidos->map(fn ($p) => $this->formatearPartido($p))->values(),
            ];
        })->values()->all();
    }

    private function formatearPartido(LigaHeadPartido $partido): array
    {
        $partido->load(['parejaA.jugador1', 'parejaA.jugador2', 'parejaB.jugador1', 'parejaB.jugador2', 'sets']);

        return [
            'id'             => $partido->id,
            'jornada_id'     => $partido->jornada_id,
            'pareja_a'       => [
                'id'       => $partido->pareja_a_id,
                'nombre'   => $this->service->nombrePareja($partido->parejaA),
                'fotos'    => $this->fotosPareja($partido->parejaA),
                'jugadores' => $this->jugadoresPareja($partido->parejaA),
            ],
            'pareja_b'       => [
                'id'       => $partido->pareja_b_id,
                'nombre'   => $this->service->nombrePareja($partido->parejaB),
                'fotos'    => $this->fotosPareja($partido->parejaB),
                'jugadores' => $this->jugadoresPareja($partido->parejaB),
            ],
            'fecha'          => $partido->fecha,
            'hora'           => $partido->hora,
            'estado'         => $partido->estado,
            'sets'           => $partido->sets->map(fn ($s) => [
                'numero'  => $s->numero,
                'games_a' => $s->games_a,
                'games_b' => $s->games_b,
            ])->values(),
            'validado_rival'  => (bool) $partido->validado_rival,
            'confirmado_club' => (bool) $partido->confirmado_club,
        ];
    }

    /** Categoría pedida en la URL (o la primera de la liga). */
    private function categoriaSolicitada(Request $request, LigaHead $liga): ?string
    {
        $pedida = strtolower(trim((string) ($request->getQueryParams()['categoria'] ?? '')));
        $lista = $liga->categoriasLista();

        if ($pedida !== '' && in_array($pedida, $lista, true)) {
            return $pedida;
        }
        return $lista[0] ?? $liga->categoria;
    }

    /** URL absoluta de la imagen de la liga. */
    private function urlImagen($ruta): ?string
    {
        $ruta = trim((string) $ruta);
        if ($ruta === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $ruta)) {
            return $ruta;
        }
        $base = rtrim($_ENV['ADMIN_URL'] ?? 'https://admin.tupadelmatch.es', '/');
        return $base . '/' . ltrim($ruta, '/');
    }

    /** URL absoluta de la foto de un jugador (API o panel). */
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

    /** Id, nombre y foto de cada jugador de la pareja. */
    private function jugadoresPareja($pareja): array
    {
        if (!$pareja) {
            return [];
        }

        $corto = function ($user) {
            $nombre = trim((string) ($user->fullName ?? '') ?: (string) ($user->username ?? ''));
            if ($nombre === '') {
                return 'Jugador';
            }
            $partes = preg_split('/\s+/', $nombre);
            return count($partes) >= 3 ? $partes[0] . ' ' . $partes[2] : $nombre;
        };

        $lista = [];
        foreach (['jugador1', 'jugador2'] as $relacion) {
            $user = $pareja->{$relacion};
            if ($user) {
                $lista[] = [
                    'id'     => (int) $user->id,
                    'nombre' => $corto($user),
                    'foto'   => $this->urlFoto($user->imagePath ?? ''),
                ];
            }
        }
        return $lista;
    }

    /** Fotos de los dos jugadores de una pareja. */
    private function fotosPareja($pareja): array
    {
        if (!$pareja) {
            return [null, null];
        }
        return [
            $this->urlFoto($pareja->jugador1->imagePath ?? ''),
            $this->urlFoto($pareja->jugador2->imagePath ?? ''),
        ];
    }

    private function esAdmin(Request $request): bool
    {
        $user = User::find($request->getAttribute('user_id'));
        return $user && $user->role === 'admin';
    }

    private function successResponse(Response $response, $data, $statusCode = 200): Response
    {
        $response->getBody()->write(json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }

    private function errorResponse(Response $response, string $message, $statusCode = 400): Response
    {
        $response->getBody()->write(json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
}
