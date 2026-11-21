<?php
/**
 * Configuración de Firebase Cloud Messaging para el panel.
 *
 * 1. Firebase Console -> Configuración del proyecto -> Cuentas de servicio
 *    -> Generar nueva clave privada.
 * 2. Sube el JSON a config/firebase-service-account.json (no se commitea).
 */
return [
    'service_account' => __DIR__ . '/firebase-service-account.json',
    // Alternativa legada (si tu proyecto aún tiene Server Key):
    // 'server_key' => 'AAAA...',
];
