<?php
namespace PadelClub\Utils;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private $mailer;
    private $fromEmail;
    private $fromName;

    public function __construct()
    {
        $this->mailer    = new PHPMailer(true);
        $this->fromEmail = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@padelclub.com';
        $this->fromName  = $_ENV['MAIL_FROM_NAME'] ?? 'Padel Club';

        // Configuración SMTP
        $this->mailer->isSMTP();
        $this->mailer->SMTPDebug  = 2;
        $this->mailer->Host       = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
        $this->mailer->SMTPAuth   = true;
        $this->mailer->Username   = $_ENV['MAIL_USERNAME'];
        $this->mailer->Password   = $_ENV['MAIL_PASSWORD'];
        $this->mailer->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? 'tls';
        $this->mailer->Port       = $_ENV['MAIL_PORT'] ?? 587;
        $this->mailer->CharSet    = 'UTF-8';
        
        // Configuración general
        $this->mailer->isHTML(true);
    }

    public function send($to, $subject, $body, $altBody = null)
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->setFrom($this->fromEmail, $this->fromName);
            
            // Puede ser un email o array de emails
            if (is_array($to)) {
                foreach ($to as $email) {
                    $this->mailer->addAddress($email);
                }
            } else {
                $this->mailer->addAddress($to);
            }

            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->AltBody = $altBody ?? strip_tags($body);

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log('Error enviando email: ' . $this->mailer->ErrorInfo);
            return false;
        }
    }

    public function sendTemplate($to, $template, $data = [])
    {
        // Busca la plantilla
        $templatePath = __DIR__ . '/../Templates/Emails/' . $template . '.html';
        
        if (!file_exists($templatePath)) {
            throw new \Exception("Plantilla de email no encontrada: " . $template);
        }

        $content = file_get_contents($templatePath);
        
        // Reemplazar variables en la plantilla
        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        $subject = $data['subject'] ?? 'Notificación de Padel Club';

        return $this->send($to, $subject, $content);
    }
}