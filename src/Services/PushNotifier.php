<?php

namespace PadelClub\Services;

use PadelClub\Models\User;

/**
 * Avisos push a los jugadores (FCM con el token del dispositivo).
 *
 * Si falta la cuenta de servicio de Firebase, el envío se registra en el log
 * y no rompe el flujo que lo llama.
 */
class PushNotifier
{
    /** Envía una push a un usuario a partir de su id. */
    public function aUsuario($userId, string $titulo, string $cuerpo, array $datos = []): bool
    {
        $user = User::find($userId);
        if (!$user) {
            return false;
        }
        return $this->aToken($user->tokenDevice ?? '', $titulo, $cuerpo, $datos);
    }

    /**
     * Envía a varios usuarios (sin repetir y saltando los que no tienen token).
     *
     * @param array<int> $userIds
     */
    public function aUsuarios(array $userIds, string $titulo, string $cuerpo, array $datos = []): int
    {
        $ids = array_values(array_unique(array_map('intval', $userIds)));
        if (!$ids) {
            return 0;
        }

        $enviados = 0;
        foreach (User::whereIn('id', $ids)->get() as $user) {
            if ($this->aToken($user->tokenDevice ?? '', $titulo, $cuerpo, $datos)) {
                $enviados++;
            }
        }
        return $enviados;
    }

    /** Envía a los dos jugadores de una pareja. */
    public function aPareja($pareja, string $titulo, string $cuerpo, array $datos = []): int
    {
        if (!$pareja) {
            return 0;
        }
        return $this->aUsuarios(
            [(int) $pareja->jugador1_id, (int) $pareja->jugador2_id],
            $titulo,
            $cuerpo,
            $datos
        );
    }

    private function aToken($token, string $titulo, string $cuerpo, array $datos): bool
    {
        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }

        $datos['tipo'] = $datos['tipo'] ?? 'liga_head';

        $ok = FcmPushService::sendToToken($token, $titulo, $cuerpo, $datos);
        if (!$ok) {
            error_log('Push: no se pudo enviar "' . $titulo . '" (revisa config/firebase-service-account.json)');
        }
        return $ok;
    }
}
