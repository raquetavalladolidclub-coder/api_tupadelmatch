<?php
namespace PadelClub\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PadelClub\Models\User;

class NotificationService
{
    private $mailer;
    private $templatesPath;
    private $appName      = 'PadelMatch';
    private $supportEmail = 'soporte@tupadelmatch.es';
    private $logoUrl      = 'https://admin.tupadelmatch.es/assets/images/logo.png';

    public function __construct()
    {
        $this->templatesPath = __DIR__ . '/../Templates/Emails/';
        
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
            $this->mailer->Body    = $templateContent;
            $this->mailer->AltBody = $this->generatePlainText($templateContent);

            // Enviar el email
            $this->mailer->send();
            error_log("Email enviado exitosamente a: " . (is_array($to) ? implode(', ', array_keys($to)) : $to));
            return true;

        } catch (Exception $e) {
            error_log("Error enviando email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ==============================================
     * NUEVAS FUNCIONES PARA RECUPERACIÓN DE CONTRASEÑA
     * ==============================================
     */

    /**
     * Envía email con enlace para restablecer contraseña
     */
    public function sendPasswordResetEmail(User $user, string $resetLink): bool
    {
        $data = [
            'user_name'      => $user->nombre ?? $user->username ?? 'Usuario',
            'user_email'     => $user->email,
            'reset_link'     => $resetLink,
            'expires_in'     => '24 horas',
            'support_contact' => $this->supportEmail,
            'current_date'   => date('d/m/Y H:i'),
            'ip_address'     => $_SERVER['REMOTE_ADDR'] ?? 'No disponible',
            
            // URLs adicionales
            'login_url'      => 'https://tupadelmatch.es/login',
            'faq_url'        => 'https://tupadelmatch.es/ayuda/recuperar-contrasena',
            'security_tips_url' => 'https://tupadelmatch.es/seguridad'
        ];

        // Obtener el nombre completo del usuario
        if (!empty($user->nombre) && !empty($user->apellidos)) {
            $data['user_full_name'] = trim($user->nombre . ' ' . $user->apellidos);
        } else if (!empty($user->fullName)) {
            $data['user_full_name'] = $user->fullName;
        } else {
            $data['user_full_name'] = $data['user_name'];
        }

        // Agregar información de seguridad
        $data['security_tips'] = [
            '1. Nunca compartas tu contraseña',
            '2. Cambia tu contraseña regularmente',
            '3. Usa una combinación de letras, números y símbolos',
            '4. No uses la misma contraseña en múltiples sitios'
        ];

        return $this->sendGeneralNotificationWithTemplate(
            $user->email,
            'password_reset.html',
            $data
        );
    }

    /**
     * Envía email con nueva contraseña generada automáticamente
     */
    public function sendNewPasswordEmail(User $user, string $newPassword): bool
    {
        $data = [
            'user_name'      => $user->nombre ?? $user->username ?? 'Usuario',
            'user_email'     => $user->email,
            'new_password'   => $newPassword,
            'generated_date' => date('d/m/Y H:i'),
            'expires_in'     => 'Nunca (puedes cambiarla cuando quieras)',
            
            // Instrucciones importantes
            'change_password_url' => 'https://tupadelmatch.es/perfil/cambiar-contrasena',
            'login_url'           => 'https://tupadelmatch.es/login',
            'security_center_url' => 'https://tupadelmatch.es/perfil/seguridad',
            
            // Advertencias de seguridad
            'warning_message' => 'Esta contraseña ha sido generada automáticamente. Por seguridad, te recomendamos cambiarla inmediatamente después de iniciar sesión.'
        ];

        // Obtener nombre completo
        if (!empty($user->nombre) && !empty($user->apellidos)) {
            $data['user_full_name'] = trim($user->nombre . ' ' . $user->apellidos);
        } else if (!empty($user->fullName)) {
            $data['user_full_name'] = $user->fullName;
        } else {
            $data['user_full_name'] = $data['user_name'];
        }

        // Pasos recomendados
        $data['recommended_steps'] = [
            '1. Inicia sesión con la contraseña proporcionada',
            '2. Ve a tu perfil > Configuración de seguridad',
            '3. Cambia la contraseña por una personal',
            '4. Activa la autenticación de dos factores (recomendado)'
        ];

        return $this->sendGeneralNotificationWithTemplate(
            $user->email,
            'new_password.html',
            $data
        );
    }

    /**
     * Envía confirmación de cambio de contraseña exitoso
     */
    public function sendPasswordChangedConfirmation(User $user): bool
    {
        $data = [
            'user_name'          => $user->nombre ?? $user->username ?? 'Usuario',
            'user_email'         => $user->email,
            'change_date'        => date('d/m/Y H:i'),
            'ip_address'         => $_SERVER['REMOTE_ADDR'] ?? 'No disponible',
            'device_info'        => $_SERVER['HTTP_USER_AGENT'] ?? 'No disponible',
            
            // Información de seguridad
            'support_contact'    => $this->supportEmail,
            'login_url'          => 'https://tupadelmatch.es/login',
            'security_check_url' => 'https://tupadelmatch.es/perfil/actividad',
            
            // Mensaje de seguridad
            'security_message' => 'Si no reconoces este cambio, por favor contacta inmediatamente con nuestro equipo de soporte.'
        ];

        // Obtener nombre completo
        if (!empty($user->nombre) && !empty($user->apellidos)) {
            $data['user_full_name'] = trim($user->nombre . ' ' . $user->apellidos);
        } else if (!empty($user->fullName)) {
            $data['user_full_name'] = $user->fullName;
        } else {
            $data['user_full_name'] = $data['user_name'];
        }

        // Opciones de seguridad adicionales
        $data['security_options'] = [
            [
                'title' => 'Autenticación de Dos Factores',
                'description' => 'Protege tu cuenta con un código adicional',
                'url' => 'https://tupadelmatch.es/perfil/2fa'
            ],
            [
                'title' => 'Revisar Actividad',
                'description' => 'Verifica los inicios de sesión recientes',
                'url' => 'https://tupadelmatch.es/perfil/actividad'
            ],
            [
                'title' => 'Dispositivos Conectados',
                'description' => 'Gestiona los dispositivos autorizados',
                'url' => 'https://tupadelmatch.es/perfil/dispositivos'
            ]
        ];

        return $this->sendGeneralNotificationWithTemplate(
            $user->email,
            'password_changed.html',
            $data
        );
    }

    /**
     * Envía email de bienvenida al nuevo usuario
     * (Ya existe pero la mantengo por si acaso)
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

    /**
     * Método simplificado para enviar email de recuperación desde el controlador
     */
    public function sendPasswordReset(User $user, string $resetToken): bool
    {
        $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'https://tupadelmatch.es';
        $resetLink = $frontendUrl . '/reset-password?token=' . $resetToken;
        
        return $this->sendPasswordResetEmail($user, $resetLink);
    }

    /**
     * ==============================================
     * MÉTODOS AUXILIARES ACTUALIZADOS
     * ==============================================
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
                    // Procesar arrays para security_tips, recommended_steps, etc.
                    elseif (in_array($key, ['security_tips', 'recommended_steps', 'security_options'])) {
                        $listContent = '<ul>';
                        foreach ($value as $item) {
                            if (is_array($item)) {
                                // Para security_options que tiene título, descripción y URL
                                if (isset($item['title'])) {
                                    $listContent .= '<li>';
                                    $listContent .= '<strong>' . htmlspecialchars($item['title']) . '</strong>: ';
                                    $listContent .= htmlspecialchars($item['description'] ?? '');
                                    if (isset($item['url'])) {
                                        $listContent .= ' <a href="' . htmlspecialchars($item['url']) . '">Ver más</a>';
                                    }
                                    $listContent .= '</li>';
                                } else {
                                    $listContent .= '<li>' . htmlspecialchars($item) . '</li>';
                                }
                            } else {
                                $listContent .= '<li>' . htmlspecialchars($item) . '</li>';
                            }
                        }
                        $listContent .= '</ul>';
                        $content = str_replace('{{' . $key . '}}', $listContent, $content);
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

    private function getSubjectForTemplate($template, $data)
    {
        $subjects = [
            'welcome.html'                => '¡Bienvenido a ' . $this->appName . '!',
            'jugador_apuntado.html'       => 'Nuevo jugador en tu partido',
            'partido_completo.html'       => '🎉 ¡Tu partido está completo!',
            'jugador_eliminado.html'      => 'Un jugador ha cancelado su participación',
            'plaza_disponible.html'       => '🚨 ¡Plaza disponible en partido completo!',
            'recordatorio_24h.html'       => '⏰ Recordatorio: Tu partido de pádel mañana',
            'invitacion_privada.html'     => '🎯 Invitación a partido privado',
            'review_post_partido.html'    => '🏆 ¿Cómo te fue el partido?',
            
            // Nuevos subjects para recuperación de contraseña
            'password_reset.html'         => 'Restablece tu contraseña de ' . $this->appName,
            'new_password.html'           => 'Tu nueva contraseña de ' . $this->appName,
            'password_changed.html'       => 'Confirmación de cambio de contraseña - ' . $this->appName
        ];

        // Si no está en la lista, usar un subject genérico
        if (isset($subjects[$template])) {
            return $subjects[$template];
        }
        
        // Personalizar subject con nombre de usuario si está disponible
        if (isset($data['user_name'])) {
            return 'Hola ' . $data['user_name'] . ' - Notificación de ' . $this->appName;
        }
        
        return 'Notificación de ' . $this->appName;
    }

    /**
     * ==============================================
     * MÉTODOS EXISTENTES (se mantienen igual)
     * ==============================================
     */

    public function sendPlayerJoinedNotification($partido, $jugador, $organizadorEmail)
    {
        $data = [
            'player_name'       => $jugador->fullName ?? $jugador->username,
            'organizer_name'    => $organizadorEmail,
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
                    'name'        => $insc->usuario->fullName ?? $insc->usuario->username,
                    'skill_level' => $insc->usuario->categoria ?? 'N/A',
                    'phone'       => $insc->usuario->phone ?? 'No disponible',
                    'initials'    => $this->getInitials($insc->usuario->fullName ?? $insc->usuario->username)
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
            
            'organizer_name'  => $partido->creador->fullName ?? 'Organizador',
            'organizer_phone' => $partido->creador->phone ?? 'No disponible',
            
            'players' => $jugadores,
            
            'match_details_url' => 'https://tupadelmatch.es/partido/' . $partido->id,
            'calendar_url'      => 'https://tupadelmatch.es/partido/' . $partido->id . '/calendar',
            'cancel_url'        => 'https://tupadelmatch.es/partido/' . $partido->id . '/cancel?token=' . $this->generateCancelToken($inscripcion->id),
            'share_url'         => 'https://tupadelmatch.es/partido/' . $partido->id . '/share',
        ];
        
        return $this->sendGeneralNotificationWithTemplate(
            $usuario->email,
            'confirmacionInscripcionPartido.html',
            $data
        );
    }

    // ... (todos los demás métodos existentes se mantienen igual) ...

    private function generatePlainText($htmlContent)
    {
        $plain = strip_tags($htmlContent);
        $plain = preg_replace('/\s+/', ' ', $plain);
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

    private function createBasicTemplate($templateName, $data)
    {
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
}