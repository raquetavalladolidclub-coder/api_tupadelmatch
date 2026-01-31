<?php
namespace PadelClub\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class NotificationService
{
    private $mailer;
    private $templatesPath;
    private $appName      = 'PadelMatch';
    private $supportEmail = 'soporte@tupadelmatch.es';
    private $logoUrl      = 'https://admin.tupadelmatch.es/assets/images/logo.png';

    public function __construct()
    {
        $this->templatesPath = __DIR__ . '../Templates/Emails/';
        // $templatePath = __DIR__ . '/../Templates/Emails/' . $template . '.html';
        
        // Configurar PHPMailer (ajusta con tus credenciales)
        $this->mailer = new PHPMailer(true);
        $this->configureMailer();
    }

    private function configureMailer()
    {
        // Configuración SMTP - AJUSTA ESTOS VALORES
        $this->mailer->isSMTP();
        $this->mailer->SMTPDebug  = 0;
        $this->mailer->Host       = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
        $this->mailer->SMTPAuth   = true;
        $this->mailer->Username   = $_ENV['MAIL_USERNAME'];
        $this->mailer->Password   = $_ENV['MAIL_PASSWORD'];
        $this->mailer->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? 'tls';
        $this->mailer->Port       = $_ENV['MAIL_PORT'] ?? 587;
        $this->mailer->CharSet    = 'UTF-8';
        
        // Configuración general
        $this->mailer->setFrom('notificaciones@tupadelmatch.es', $this->appName);
        $this->mailer->isHTML(true);
        $this->mailer->CharSet = 'UTF-8';
    }

    /**
     * Envía una notificación general usando un template
     */
    public function sendGeneralNotificationWithTemplate($to, $template, $data = [])
    {
        try {
            // Agregar datos comunes a todos los templates
            $data = array_merge($data, [
                'app_name'      => $this->appName,
                'support_email' => $this->supportEmail,
                'logo_url'      => $this->logoUrl,
                'current_year'  => date('Y')
            ]);

            // Cargar el template
            $templateContent = $this->loadTemplate($template, $data);
            
            if (!$templateContent) {
                throw new \Exception("Template '$template' no encontrado");
            }

            // Configurar el email
            $this->mailer->clearAddresses();
            
            if (is_array($to)) {
                foreach ($to as $email => $name) {
                    $this->mailer->addAddress($email, $name);
                }
            } else {
                $this->mailer->addAddress($to);
            }

            // Determinar asunto según el template
            $subject = $this->getSubjectForTemplate($template, $data);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $templateContent;
            $this->mailer->AltBody = $this->generatePlainText($templateContent);

            // Enviar el email
            $this->mailer->send();
            return true;

        } catch (Exception $e) {
            error_log("Error enviando email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Métodos específicos para cada tipo de notificación
     */
    
    public function sendWelcomeEmail($userEmail, $userName, $password = null)
    {
        $data = [
            'user_name' => $userName,
            'email'     => $userEmail,
            'password'  => $password,
            'login_url' => 'https://tupadelmatch.es/login',
            'app_name'  => $this->appName
        ];

        return $this->sendGeneralNotificationWithTemplate(
            $userEmail, 
            'welcome.html', 
            $data
        );
    }

    public function sendPlayerJoinedNotification($partido, $jugador, $organizadorEmail)
    {
        $data = [
            'player_name'       => $jugador->nombre ?? $jugador->username,
            'organizer_name'    => $organizadorEmail, // O nombre del organizador
            'match_date'        => $partido->fecha->format('d/m/Y'),
            'match_time'        => $partido->hora,
            'court_name'        => $partido->pista,
            'court_address'     => $partido->club ? $partido->club->direccion : 'Dirección no disponible',
            'skill_level'       => $partido->categoria,
            'price'             => $partido->precio_individual,
            'match_details_url' => 'https://tupadelmatch.es/partido/' . $partido->id
        ];

        return $this->sendGeneralNotificationWithTemplate(
            $organizadorEmail,
            'jugadorApuntado.html',
            $data
        );
    }

    public function sendPlayerConfirmationEmail($partido, $usuario, $inscripcion)
    {
        // Obtener lista de jugadores confirmados
        $jugadores = $partido->jugadoresConfirmados()
            ->with('usuario')
            ->get()
            ->map(function($insc) {
                return [
                    'name'        => $insc->usuario->nombre ?? $insc->usuario->username,
                    'skill_level' => $insc->usuario->categoria ?? 'N/A',
                    'phone'       => $insc->usuario->telefono ?? 'No disponible',
                    'initials'    => $this->getInitials($insc->usuario->nombre ?? $insc->usuario->username)
                ];
            })
            ->toArray();
        
        $data = [
            'player_name'       => $usuario->nombre ?? $usuario->username,
            'inscription_id'    => $inscripcion->id,
            'reservation_code'  => 'RES-' . str_pad($inscripcion->id, 6, '0', STR_PAD_LEFT),
            'confirmation_date' => date('d/m/Y H:i'),
            
            'match_date'      => $partido->fecha->format('d/m/Y'),
            'match_time'      => $partido->hora,
            'court_name'      => $partido->pista,
            'court_address'   => $partido->club ? $partido->club->direccion : 'Dirección no disponible',
            'club_phone'      => $partido->club ? $partido->club->telefono : 'No disponible',
            'skill_level'     => $partido->categoria,
            'price'           => number_format($partido->precio_individual, 2) . '€',
            'current_players' => $partido->jugadoresConfirmados()->count(),
            'max_players'     => $partido->tipo === 'individual' ? 2 : 4,
            
            'organizer_name'  => $partido->creador->name ?? 'Organizador',
            'organizer_phone' => $partido->creador->telefono ?? 'No disponible',
            
            'players' => $jugadores,
            
            'match_details_url' => 'https://tupadelmatch.es/partido/' . $partido->id,
            'calendar_url'      => 'https://tupadelmatch.es/partido/' . $partido->id . '/calendar',
            'cancel_url'        => 'https://tupadelmatch.es/partido/' . $partido->id . '/cancel?token=' . $this->generateCancelToken($inscripcion->id),
            'share_url'         => 'https://tupadelmatch.es/partido/' . $partido->id . '/share',
        ];
        echo $usuario->email;
        return $this->sendGeneralNotificationWithTemplate(
            $usuario->email,
            'confirmacionInscripcionPartido.html',
            $data
        );
    }

    private function generateCancelToken($inscriptionId)
    {
        return md5($inscriptionId . 'cancel' . time());
    }

    public function sendMatchFullNotification($partido, $jugadores)
    {
        $playerData = [];
        foreach ($jugadores as $jugador) {
            $playerData[] = [
                'name'        => $jugador->nombre ?? $jugador->username,
                'skill_level' => $jugador->categoria ?? 'N/A',
                'phone'       => $jugador->telefono ?? 'No disponible',
                'initials'    => $this->getInitials($jugador->nombre ?? $jugador->username)
            ];
        }

        $data = [
            'match_date'        => $partido->fecha->format('d/m/Y'),
            'match_time'        => $partido->hora,
            'court_name'        => $partido->pista,
            'court_address'     => $partido->club ? $partido->club->direccion : 'Dirección no disponible',
            'club_phone'        => $partido->club ? $partido->club->telefono : 'No disponible',
            'price'             => $partido->precio_individual,
            'total_players'     => count($jugadores),
            'max_players'       => $partido->tipo === 'individual' ? 2 : 4,
            'players'           => $playerData,
            'match_details_url' => 'https://tupadelmatch.es/partido/' . $partido->id
        ];

        // Enviar a todos los jugadores
        $sent = true;
        foreach ($jugadores as $jugador) {
            $data['player_name'] = $jugador->nombre ?? $jugador->username;
            
            $sent = $sent && $this->sendGeneralNotificationWithTemplate(
                $jugador->email,
                'partido_completo.html',
                $data
            );
        }

        return $sent;
    }

    public function sendPlayerLeftNotification($partido, $jugador, $organizadorEmail)
    {
        $data = [
            'player_name'           => $jugador->nombre ?? $jugador->username,
            'match_date'            => $partido->fecha->format('d/m/Y'),
            'match_time'            => $partido->hora,
            'court_name'            => $partido->pista,
            'court_address'         => $partido->club ? $partido->club->direccion : 'Dirección no disponible',
            'available_matches_url' => 'https://tupadelmatch.es/partidos'
        ];

        return $this->sendGeneralNotificationWithTemplate(
            $organizadorEmail,
            'jugador_eliminado.html',
            $data
        );
    }

    public function sendSpotAvailableNotification($partido, $jugador, $waitingListPlayers)
    {
        $data = [
            'player_name'      => $jugador->nombre ?? $jugador->username,
            'match_date'       => $partido->fecha->format('d/m/Y'),
            'days_until_match' => $this->daysUntil($partido->fecha),
            'match_time'       => $partido->hora,
            'court_name'       => $partido->pista,
            'court_address'    => $partido->club ? $partido->club->direccion : 'Dirección no disponible',
            'skill_level'      => $partido->categoria,
            'price'            => $partido->precio_individual,
            'available_spots'  => 1,
            'max_players'      => $partido->tipo === 'individual' ? 2 : 4,
            'current_players'  => ($partido->jugadoresConfirmados()->count() - 1) . '/' . ($partido->tipo === 'individual' ? 2 : 4),
            'quick_join_url'   => 'https://tupadelmatch.es/partido/' . $partido->id . '/join-quick',
            'expiration_time'  => date('H:i', strtotime('+2 hours'))
        ];

        // Enviar a todos los jugadores en lista de espera
        $sent = true;
        foreach ($waitingListPlayers as $waitingPlayer) {
            $data['player_name'] = $waitingPlayer->nombre ?? $waitingPlayer->username;
            
            $sent = $sent && $this->sendGeneralNotificationWithTemplate(
                $waitingPlayer->email,
                'plaza_disponible.html',
                $data
            );
        }

        return $sent;
    }

    public function sendMatchReminder24h($partido, $jugador)
    {
        $weatherData = $this->getWeatherForecast($partido);
        
        $data = [
            'player_name'       => $jugador->nombre ?? $jugador->username,
            'match_date'        => $partido->fecha->format('d/m/Y'),
            'match_time'        => $partido->hora,
            'court_name'        => $partido->pista,
            'court_address'     => $partido->club ? $partido->club->direccion : 'Dirección no disponible',
            'club_phone'        => $partido->club ? $partido->club->telefono : 'No disponible',
            'organizer_name'    => $partido->creador->name ?? 'Organizador',
            'organizer_phone'   => $partido->creador->telefono ?? 'No disponible',
            'price'             => $partido->precio_individual,
            'match_details_url' => 'https://tupadelmatch.es/partido/' . $partido->id,
            'cancel_url'        => 'https://tupadelmatch.es/partido/' . $partido->id . '/cancel'
        ];

        // Agregar datos meteorológicos si están disponibles
        if ($weatherData) {
            $data['weather_available'] = true;
            $data = array_merge($data, $weatherData);
        } else {
            $data['weather_available'] = false;
        }

        return $this->sendGeneralNotificationWithTemplate(
            $jugador->email,
            'recordatorio_24h.html',
            $data
        );
    }

    public function sendPrivateInvitation($partido, $inviter, $invitedPlayer, $personalMessage = '')
    {
        $data = [
            'player_name'      => $invitedPlayer->nombre ?? $invitedPlayer->username,
            'inviter_name'     => $inviter->nombre ?? $inviter->username,
            'personal_message' => $personalMessage,
            'match_date'       => $partido->fecha->format('d/m/Y'),
            'match_time'       => $partido->hora,
            'court_name'       => $partido->pista,
            'court_address'    => $partido->club ? $partido->club->direccion : 'Dirección no disponible',
            'accept_url'       => 'https://tupadelmatch.es/invitacion/' . $this->generateInvitationToken($partido->id, $invitedPlayer->id) . '/accept',
            'decline_url'      => 'https://tupadelmatch.es/invitacion/' . $this->generateInvitationToken($partido->id, $invitedPlayer->id) . '/decline'
        ];

        return $this->sendGeneralNotificationWithTemplate(
            $invitedPlayer->email,
            'invitacion_privada.html',
            $data
        );
    }

    public function sendPostMatchReview($partido, $jugador)
    {
        $data = [
            'player_name'      => $jugador->nombre ?? $jugador->username,
            'match_date'       => $partido->fecha->format('d/m/Y'),
            'court_name'       => $partido->pista,
            'player_count'     => $partido->jugadoresConfirmados()->count(),
            'review_url'       => 'https://tupadelmatch.es/partido/' . $partido->id . '/review',
            'new_match_url'    => 'https://tupadelmatch.es/partidos',
            'create_match_url' => 'https://tupadelmatch.es/crear-partido'
        ];

        return $this->sendGeneralNotificationWithTemplate(
            $jugador->email,
            'review_post_partido.html',
            $data
        );
    }

    /**
     * Métodos auxiliares
     */

    private function loadTemplate($templateName, $data)
    {
        // Asegurar que tenga la extensión .html
        if (!str_ends_with($templateName, '.html')) {
            $templateName .= '.html';
        }

        $templatePath = $this->templatesPath . $templateName;
        
        if (file_exists($templatePath)) {
            $content = file_get_contents($templatePath);
            
            // Reemplazar variables
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    // Procesar arrays específicos
                    if ($key === 'players' && strpos($content, '{{#players}}') !== false) {
                        $playerContent = '';
                        foreach ($value as $player) {
                            $playerContent .= $this->renderPlayerItem($player);
                        }
                        // Reemplazar bloque completo
                        $pattern = '/\{\{#players\}\}.*?\{\{\/players\}\}/s';
                        $replacement = $playerContent;
                        $content = preg_replace($pattern, $replacement, $content);
                    }
                } else {
                    // Reemplazar variables simples
                    $content = str_replace('{{' . $key . '}}', htmlspecialchars($value ?? '', ENT_QUOTES), $content);
                    // También soportar formato sin llaves por si acaso
                    $content = str_replace('$' . $key . '$', htmlspecialchars($value ?? '', ENT_QUOTES), $content);
                }
            }
            
            // Limpiar cualquier variable no reemplazada
            $content = preg_replace('/\{\{[^}]+\}\}/', '', $content);
            
            return $content;
        }
        
        // Si no encuentra el template, crear uno básico
        error_log("Template no encontrado: $templateName. Paths probados: " . $templatePath);
        return $this->createBasicTemplate($templateName, $data);
    }

    private function createBasicTemplate($templateName, $data)
    {
        // Template HTML básico de respaldo
        $subject = $this->getSubjectForTemplate($templateName, $data);
        
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($subject) . '</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; background-color: #f9f9f9; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>' . htmlspecialchars($subject) . '</h1>
                </div>
                
                <div class="content">';
        
        // Agregar datos dinámicos
        foreach ($data as $key => $value) {
            if (!is_array($value) && !in_array($key, ['app_name', 'support_email', 'current_year'])) {
                $html .= '<p><strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars($value) . '</p>';
            }
        }
        
        $html .= '
                </div>
                
                <div class="footer">
                    <p>Este es un email automático de ' . htmlspecialchars($data['app_name'] ?? 'TuPadelMatch') . '.</p>
                    <p>&copy; ' . ($data['current_year'] ?? date('Y')) . ' ' . htmlspecialchars($data['app_name'] ?? 'TuPadelMatch') . '. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    private function loadTemplateOLD($templateName, $data)
    {
        $templatePath = $this->templatesPath . $templateName;
        
        if (!file_exists($templatePath)) {
            // Intentar cargar template por defecto si no existe
            $templatePath = $this->templatesPath . 'default.html';
            
            if (!file_exists($templatePath)) {
                return false;
            }
        }

        $content = file_get_contents($templatePath);
        
        // Reemplazar variables en el template
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Procesar arrays (como lista de jugadores)
                if ($key === 'players') {
                    $playerList = '';
                    foreach ($value as $player) {
                        $playerHtml = $this->renderPlayerItem($player);
                        $playerList .= $playerHtml;
                    }
                    $content = str_replace('{{#players}}', '', $content);
                    $content = str_replace('{{/players}}', '', $content);
                    $content = str_replace('{{player_list}}', $playerList, $content);
                }
            } else {
                $content = str_replace('{{' . $key . '}}', htmlspecialchars($value, ENT_QUOTES), $content);
            }
        }

        return $content;
    }

    private function getSubjectForTemplate($template, $data)
    {
        $subjects = [
            'welcome.html'             => '¡Bienvenido a ' . $this->appName . '!',
            'jugador_apuntado.html'    => 'Nuevo jugador en tu partido',
            'partido_completo.html'    => '🎉 ¡Tu partido está completo!',
            'jugador_eliminado.html'   => 'Un jugador ha cancelado su participación',
            'plaza_disponible.html'    => '🚨 ¡Plaza disponible en partido completo!',
            'recordatorio_24h.html'    => '⏰ Recordatorio: Tu partido de pádel mañana',
            'invitacion_privada.html'  => '🎯 Invitación a partido privado',
            'review_post_partido.html' => '🏆 ¿Cómo te fue el partido?'
        ];

        return $subjects[$template] ?? 'Notificación de ' . $this->appName;
    }

    private function generatePlainText($htmlContent)
    {
        // Eliminar etiquetas HTML
        $plain = strip_tags($htmlContent);
        
        // Reemplazar múltiples espacios y saltos de línea
        $plain = preg_replace('/\s+/', ' ', $plain);
        
        // Limpiar caracteres especiales
        $plain = html_entity_decode($plain, ENT_QUOTES, 'UTF-8');
        
        return trim($plain);
    }

    private function getInitials($name)
    {
        $parts = explode(' ', $name);
        $initials = '';
        
        if (count($parts) >= 2) {
            $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
        } else {
            $initials = strtoupper(substr($name, 0, 2));
        }
        
        return $initials;
    }

    private function daysUntil($date)
    {
        $now = new \DateTime();
        $matchDate = is_string($date) ? new \DateTime($date) : $date;
        $interval  = $now->diff($matchDate);
        return $interval->days;
    }

    private function getWeatherForecast($partido)
    {
        // Esta función debería integrarse con una API de pronóstico del tiempo
        // Por ahora retorna datos de ejemplo o null
        
        // Ejemplo de integración con OpenWeatherMap:
        /*
        $apiKey = 'tu_api_key';
        $city = $partido->club ? $partido->club->ciudad : 'Madrid';
        $date = $partido->fecha->format('Y-m-d');
        
        // Hacer llamada API...
        */
        
        // Retornar null por ahora
        return null;
    }

    private function generateInvitationToken($partidoId, $userId)
    {
        return md5($partidoId . $userId . time());
    }

    private function renderPlayerItem($player)
    {
        return '<div class="player-item">
                    <div class="player-avatar">' . ($player['initials'] ?? '??') . '</div>
                    <div>
                        <strong>' . htmlspecialchars($player['name'] ?? 'Jugador') . '</strong><br>
                        <small>Nivel: ' . ($player['skill_level'] ?? 'N/A') . ' | Tel: ' . ($player['phone'] ?? 'No disponible') . '</small>
                    </div>
                </div>';
    }

    /**
     * Método para enviar notificaciones personalizadas desde el controller
     */
    public function sendMatchNotification($type, $partido, $user, $additionalData = [])
    {
        $methodMap = [
            'player_joined'      => 'sendPlayerJoinedNotification',
            'match_full'         => 'sendMatchFullNotification',
            'player_left'        => 'sendPlayerLeftNotification',
            'spot_available'     => 'sendSpotAvailableNotification',
            'reminder_24h'       => 'sendMatchReminder24h',
            'private_invitation' => 'sendPrivateInvitation',
            'post_match_review'  => 'sendPostMatchReview'
        ];

        if (!isset($methodMap[$type])) {
            throw new \Exception("Tipo de notificación no válido: $type");
        }

        $method = $methodMap[$type];
        return $this->$method($partido, $user, $additionalData);
    }
}