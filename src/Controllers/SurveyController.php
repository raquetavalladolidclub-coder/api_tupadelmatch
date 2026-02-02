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
        /*$required = [
            'experience_years',
            'weekly_play_frequency',
            'has_competitive_experience',
            'technical_level',
            'physical_condition',
            'tactical_knowledge'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return $this->errorResponse($response, "Campo requerido: $field");
            }
        }*/

        try {
            // Evitar más de una encuesta por usuario
            if (Survey::where('user_id', $userId)->exists()) {
                return $this->errorResponse($response, 'La encuesta ya fue completada', 409);
            }

            // Calcular score
            $score =
                (int)$data['experience_years'] +
                (int)$data['weekly_play_frequency'] +
                (int)$data['technical_level'] +
                (int)$data['physical_condition'] +
                (int)$data['tactical_knowledge'];

            $suggestedCategory =
                $score >= 20 ? 'Avanzado' :
                ($score >= 12 ? 'Intermedio' : 'Inicial');

            // Crear encuesta
            $survey = Survey::create([
                'user_id' => $userId,
                'experience_years' => $data['experience_years'],
                'weekly_play_frequency' => $data['weekly_play_frequency'],
                'has_competitive_experience' => $data['has_competitive_experience'],
                'technical_level' => $data['technical_level'],
                'physical_condition' => $data['physical_condition'],
                'tactical_knowledge' => $data['tactical_knowledge'],
                'previous_category' => $data['previous_category'] ?? null,
                'calculated_score' => $score,
                'suggested_category' => $suggestedCategory
            ]);

            // Actualizar usuario
            $user = User::find($userId);
            if ($user) {
                $user->nivel = $suggestedCategory;
                $user->nivel_puntuacion = $score;
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
