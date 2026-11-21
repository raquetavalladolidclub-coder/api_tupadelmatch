<?php
/**
 * Recordatorio 24 h: envía el correo del partido que se juega mañana.
 *
 * Pensado para ejecutarse cada 15 minutos desde Plesk o cron:
 *   php /var/www/vhosts/tupadelmatch.es/api.tupadelmatch.es/cron/recordatorios.php
 *
 * No repite envíos: marca el partido con recordatorio_enviado.
 */
require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();
require __DIR__ . '/../config/database.php';

use PadelClub\Models\Partido;
use PadelClub\Services\NotificationService;

$servicio = new NotificationService();
$manana = date('Y-m-d', strtotime('+1 day'));

$partidos = Partido::where('fecha', $manana)
    ->where('recordatorio_enviado', 0)
    ->whereNotIn('estado', ['cancelado', 'finalizado'])
    ->get();

echo 'Recordatorios para ' . $manana . ': ' . $partidos->count() . " partido(s)\n";

foreach ($partidos as $partido) {
    $jugadores = $partido->jugadoresConfirmados()->with('usuario')->get()->pluck('usuario');
    if ($jugadores->isEmpty()) {
        $partido->update([
            'recordatorio_enviado'    => 1,
            'recordatorio_enviado_at' => date('Y-m-d H:i:s'),
        ]);
        echo '  · partido ' . $partido->id . ": sin jugadores, se marca para no repetir\n";
        continue;
    }

    $enviados = $servicio->sendMatchReminder($partido, $jugadores);

    $partido->update([
        'recordatorio_enviado'    => 1,
        'recordatorio_enviado_at' => date('Y-m-d H:i:s'),
    ]);

    echo '  · partido ' . $partido->id . ': ' . $enviados . ' correo(s) de ' . $jugadores->count() . " jugador(es)\n";
}

echo "Fin.\n";
