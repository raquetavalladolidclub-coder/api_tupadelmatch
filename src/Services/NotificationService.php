<?php
namespace PadelClub\Services;

use PadelClub\Utils\Mailer;
use PadelClub\Models\User;

class NotificationService
{
    public $mailer;

    public function __construct()
    {
        $this->mailer = new Mailer();
    }

    /**
     * Enviar email de bienvenida al registrar
     */
    public function sendWelcomeEmail(User $user, $plainPassword = null)
    {
        $data = [
            'subject' => '¡Bienvenido a Padel Club!',
            'user_name' => $user->full_name ?? $user->username,
            'email' => $user->email,
            'app_name' => 'Padel Club',
            'login_url' => $_ENV['APP_URL'] . '/login',
            'support_email' => $_ENV['MAIL_SUPPORT'] ?? 'soporte@padelclub.com',
            'password' => $plainPassword ? 'Tu contraseña es: ' . $plainPassword : 'La contraseña que elegiste al registrarte'
        ];

        return $this->mailer->sendTemplate($user->email, 'welcome', $data);
    }

    /**
     * Enviar email de recuperación de contraseña
     */
    public function sendPasswordResetEmail(User $user, $resetToken)
    {
        $resetUrl = $_ENV['APP_URL'] . '/reset-password?token=' . $resetToken;

        $data = [
            'subject' => 'Restablecer tu contraseña - Padel Club',
            'user_name' => $user->full_name ?? $user->username,
            'reset_url' => $resetUrl,
            'expiration_hours' => 24,
            'support_email' => $_ENV['MAIL_SUPPORT'] ?? 'soporte@padelclub.com'
        ];

        return $this->mailer->sendTemplate($user->email, 'password_reset', $data);
    }

    /**
     * Enviar email de verificación de cuenta
     */
    public function sendAccountVerificationEmail(User $user, $verificationToken)
    {
        $verificationUrl = $_ENV['APP_URL'] . '/verify-account?token=' . $verificationToken;

        $data = [
            'subject' => 'Verifica tu cuenta - Padel Club',
            'user_name' => $user->full_name ?? $user->username,
            'verification_url' => $verificationUrl,
            'expiration_hours' => 48
        ];

        return $this->mailer->sendTemplate($user->email, 'account_verification', $data);
    }

    /**
     * Enviar notificación general
     */
    public function sendGeneralNotification($to, $subject, $message)
    {
        $data = [
            'subject' => $subject,
            'message' => $message,
            'app_name' => 'Padel Club',
            'support_email' => $_ENV['MAIL_SUPPORT'] ?? 'soporte@padelclub.com'
        ];

        return $this->mailer->sendTemplate($to, 'general', $data);
    }
}