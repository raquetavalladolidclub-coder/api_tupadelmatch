<?php
return [
    'app' => [
        'name' => $_ENV['APP_NAME'] ?? 'Padel Club API',
        'version' => $_ENV['APP_VERSION'] ?? '1.0.0',
        'environment' => $_ENV['APP_ENV'] ?? 'production',
        'url' => $_ENV['APP_URL'] ?? 'http://localhost:8000'
    ],
    'features' => [
        'google_auth' => true,
        'user_registration' => true,
        'match_management' => true,
        'real_time_notifications' => false
    ],
    'limits' => [
        'max_players_per_match' => 4,
        'max_matches_per_day' => 10,
        'max_reservation_days' => 7
    ]
];