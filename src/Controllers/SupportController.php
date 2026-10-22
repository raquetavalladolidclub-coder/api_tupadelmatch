<?php

namespace PadelClub\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PadelClub\Services\NotificationService;

/**
 * Ayuda y soporte: datos de contacto, FAQ y formulario de mensaje.
 *
 * GET  /soporte          -> contacto + preguntas frecuentes
 * POST /soporte/contacto -> envía el mensaje al buzón configurado
 */
class SupportController
{
    private array $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../../config/support.php';
    }

    public function info(Request $request, Response $response)
    {
        return $this->successResponse($response, [
            'contacto' => [
                'email'            => $this->config['email'],
                'telefono'         => $this->config['telefono'],
                'whatsapp'         => $this->config['whatsapp'],
                'whatsapp_mensaje' => $this->config['whatsapp_mensaje'],
            ],
            // La app muestra este buzón bajo el formulario.
            'contacto_email' => $this->config['contacto_destino'],
            'faqs'           => array_values($this->config['faqs']),
        ]);
    }

    public function contacto(Request $request, Response $response)
    {
        $data    = $request->getParsedBody() ?? [];
        $email   = trim((string) ($data['email'] ?? ''));
        $mensaje = trim((string) ($data['mensaje'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse($response, 'El email no es válido');
        }

        if (mb_strlen($mensaje) < 5) {
            return $this->errorResponse($response, 'El mensaje es demasiado corto');
        }

        if (mb_strlen($mensaje) > 5000) {
            return $this->errorResponse($response, 'El mensaje es demasiado largo');
        }

        try {
            $notificationService = new NotificationService();
            $notificationService->sendSupportMessage(
                $this->config['contacto_destino'],
                $email,
                $mensaje
            );

            return $this->successResponse($response, [
                'message'      => 'Mensaje enviado correctamente',
                'destinatario' => $this->config['contacto_destino'],
            ]);
        } catch (\Exception $e) {
            error_log('Error enviando mensaje de soporte: ' . $e->getMessage());

            return $this->errorResponse(
                $response,
                'No se pudo enviar el mensaje. Inténtalo más tarde.',
                500
            );
        }
    }

    private function successResponse(Response $response, $data, $statusCode = 200)
    {
        $response->getBody()->write(json_encode([
            'success' => true,
            'data'    => $data,
        ]));

        return $response->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json');
    }

    private function errorResponse(Response $response, $message, $statusCode = 400)
    {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message,
        ]));

        return $response->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json');
    }
}
