<?php
namespace PadelClub\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PadelClub\Models\Survey;
use PadelClub\Models\User;

class SurveyController
{
    /**
     * Guardar encuesta
     */
    public function submitSurvey(Request $request, Response $response): Response
    {
        $data   = $request->getParsedBody();
        $userId = $request->getAttribute('user_id');

        if (!$userId) {
            return $this->errorResponse($response, 'Usuario no autenticado', 401);
        }

        // Validación básica
        $required = [
            'nivel_percepcion',
            'partidos_semana',
            'nivel_club',
            'nivel_club_texto'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return $this->errorResponse($response, "Campo requerido: $field");
            }
        }

        try {
            // Evitar más de una encuesta por usuario
            if (Survey::where('user_id', $userId)->exists()) {
                return $this->successResponse($response, 'La encuesta ya fue completada', 201);
            }

            $categorias = [
                'promesas' => 1,
                'cobre'    => 2,
                'bronce'   => 3,
                'plata'    => 4,
                'diamante' => 5,
                'oro'      => 6,
                'pro'      => 7];

            // Crear encuesta
            $survey = Survey::create([
                'user_id' => $userId,
                'nivel_percepcion' => $data['nivel_percepcion'],
                'partidos_semana'  => $data['partidos_semana'],
                'nivel_club'       => $data['nivel_club'],
                'nivel_club_texto' => $data['nivel_club_texto'],
                'puntuacion'       => $categorias[$data['nivel_club']]
            ]);

            // Actualizar usuario
            $user = User::find($userId);
            if ($user) {
                $user->categoria = strtolower($data['nivel_club']);
                $user->nivel_puntuacion = $categorias[$data['nivel_club']];
                $user->encuesta = 1;
                $user->save();
            }

            return $this->successResponse($response, [
                'id' => $survey->id,
                'calculated_score' => $survey->calculated_score,
                'suggested_category' => $survey->suggested_category
            ], 201);

        } catch (\Exception $e) {
            error_log('Survey error: ' . $e->getMessage());
            return $this->errorResponse($response, 'Error interno del servidor');
        }
    }

    /**
     * Obtener encuesta del usuario
     */
    public function getUserSurvey(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        if (!$userId) {
            return $this->errorResponse($response, 'Usuario no autenticado', 401);
        }

        $survey = Survey::where('user_id', $userId)->first();

        if (!$survey) {
            return $this->successResponse($response, null);
        }

        return $this->successResponse($response, $survey);
    }

    /* ===================== HELPERS ===================== */

    private function successResponse(Response $response, $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $data
        ]));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }

    private function errorResponse(Response $response, string $message, int $status = 400): Response
    {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message
        ]));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
