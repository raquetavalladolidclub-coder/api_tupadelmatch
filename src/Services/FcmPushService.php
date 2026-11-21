<?php

namespace PadelClub\Services;

/**
 * Envío de push con Firebase Cloud Messaging (HTTP v1).
 * Si no hay cuenta de servicio, intenta el modo legado con server_key.
 */
class FcmPushService
{
    public static function sendToToken($token, $title, $body, array $data = []): bool
    {
        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }

        $config = [];
        $configPath = __DIR__ . '/../../config/firebase.php';
        if (is_file($configPath)) {
            $config = require $configPath;
        }

        $serviceAccount = $config['service_account'] ?? '';
        if ($serviceAccount !== '' && is_file($serviceAccount)) {
            return self::sendV1($serviceAccount, $token, $title, $body, $data);
        }

        $serverKey = $config['server_key'] ?? '';
        if ($serverKey !== '') {
            return self::sendLegacy($serverKey, $token, $title, $body, $data);
        }

        error_log('FCM: falta config/firebase-service-account.json o server_key');
        return false;
    }

    private static function sendV1($serviceAccountPath, $token, $title, $body, array $data): bool
    {
        $serviceAccount = json_decode((string) file_get_contents($serviceAccountPath), true);
        if (!$serviceAccount || empty($serviceAccount['project_id']) ||
            empty($serviceAccount['client_email']) || empty($serviceAccount['private_key'])) {
            error_log('FCM: cuenta de servicio inválida');
            return false;
        }

        $accessToken = self::getAccessToken($serviceAccount);
        if ($accessToken === null) {
            return false;
        }

        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                'data' => array_map('strval', $data),
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => 'tu_padel_match_channel',
                        'sound'      => 'default',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => ['sound' => 'default'],
                    ],
                ],
            ],
        ];

        $url = 'https://fcm.googleapis.com/v1/projects/' .
            rawurlencode($serviceAccount['project_id']) . '/messages:send';

        [$httpCode, $response] = self::postJson($url, $payload, [
            'Authorization: Bearer ' . $accessToken,
        ]);

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('FCM v1 error ' . $httpCode . ': ' . $response);
            return false;
        }

        return true;
    }

    private static function getAccessToken(array $serviceAccount): ?string
    {
        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss'   => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $input = self::base64Url(json_encode($header)) . '.' . self::base64Url(json_encode($claims));
        $signature = '';
        if (!openssl_sign($input, $signature, $serviceAccount['private_key'], OPENSSL_ALGO_SHA256)) {
            error_log('FCM: no se pudo firmar el JWT');
            return null;
        }

        $assertion = $input . '.' . self::base64Url($signature);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $assertion,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            error_log('FCM: error obteniendo access_token (' . $httpCode . '): ' . $response);
            return null;
        }

        $json = json_decode($response, true);
        return $json['access_token'] ?? null;
    }

    private static function sendLegacy($serverKey, $token, $title, $body, array $data): bool
    {
        $payload = [
            'to'           => $token,
            'notification' => ['title' => $title, 'body' => $body],
            'data'         => $data,
            'priority'     => 'high',
        ];

        [$httpCode, $response] = self::postJson(
            'https://fcm.googleapis.com/fcm/send',
            $payload,
            ['Authorization: key=' . $serverKey]
        );

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('FCM legacy error ' . $httpCode . ': ' . $response);
            return false;
        }

        return true;
    }

    private static function postJson($url, array $payload, array $extraHeaders = []): array
    {
        $headers = array_merge(['Content-Type: application/json'], $extraHeaders);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$httpCode, $response === false ? '' : $response];
    }

    private static function base64Url($data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
