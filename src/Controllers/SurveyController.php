<?php
// src/Controllers/SurveyController.php
namespace PadelClub\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PadelClub\Models\Survey;
use PadelClub\Services\SurveyService;

class SurveyController
{
    private $surveyService;
    
    public function __construct()
    {
        $this->surveyService = new SurveyService();
    }
    
    /**
     * Enviar/guardar encuesta de nivelación
     */
    public function submitSurvey(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            $userId = $request->getAttribute('userId');
            
            if (!$userId) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }
            
            // Validar datos requeridos
            $validation = $this->validateSurveyData($data);
            if (!$validation['valid']) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => $validation['message']
                ], 400);
            }
            
            // Crear objeto Survey
            $survey = new Survey();
            $survey->user_id = $userId;
            $survey->experience_years = (int) $data['experience_years'];
            $survey->weekly_play_frequency = (int) $data['weekly_play_frequency'];
            $survey->has_competitive_experience = (bool) $data['has_competitive_experience'];
            $survey->technical_level = (int) $data['technical_level'];
            $survey->physical_condition = (int) $data['physical_condition'];
            $survey->tactical_knowledge = (int) $data['tactical_knowledge'];
            $survey->previous_category = $data['previous_category'] ?? null;
            
            // Calcular puntuación
            $score = $this->surveyService->calculateScore($survey);
            $suggestedCategory = $this->surveyService->getSuggestedCategory($score);
            
            $survey->calculated_score = $score;
            $survey->suggested_category = $suggestedCategory;
            
            // Guardar encuesta
            $savedSurvey = $this->surveyService->saveSurvey($survey);
            
            // Actualizar nivel del usuario
            $this->surveyService->updateUserLevel($userId, $suggestedCategory, $score);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Encuesta guardada exitosamente',
                'data' => [
                    'id' => $savedSurvey->id,
                    'user_id' => $savedSurvey->user_id,
                    'calculated_score' => $savedSurvey->calculated_score,
                    'suggested_category' => $savedSurvey->suggested_category,
                    'created_at' => $savedSurvey->created_at
                ]
            ], 201);
            
        } catch (\Exception $e) {
            error_log('Error en submitSurvey: ' . $e->getMessage());
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }
    
    /**
     * Obtener encuesta del usuario
     */
    public function getUserSurvey(Request $request, Response $response): Response
    {
        try {
            $userId = $request->getAttribute('userId');
            
            if (!$userId) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }
            
            $survey = $this->surveyService->getUserSurvey($userId);
            
            if (!$survey) {
                return $this->jsonResponse($response, [
                    'success' => true,
                    'data' => null,
                    'message' => 'Usuario no ha completado la encuesta'
                ]);
            }
            
            return $this->jsonResponse($response, [
                'success' => true,
                'data' => [
                    'id' => $survey->id,
                    'user_id' => $survey->user_id,
                    'experience_years' => $survey->experience_years,
                    'weekly_play_frequency' => $survey->weekly_play_frequency,
                    'has_competitive_experience' => (bool) $survey->has_competitive_experience,
                    'technical_level' => $survey->technical_level,
                    'physical_condition' => $survey->physical_condition,
                    'tactical_knowledge' => $survey->tactical_knowledge,
                    'previous_category' => $survey->previous_category,
                    'calculated_score' => $survey->calculated_score,
                    'suggested_category' => $survey->suggested_category,
                    'created_at' => $survey->created_at,
                    'updated_at' => $survey->updated_at
                ]
            ]);
            
        } catch (\Exception $e) {
            error_log('Error en getUserSurvey: ' . $e->getMessage());
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }
    
    /**
     * Actualizar nivel del usuario (separado para cuando ya está logueado)
     */
    public function updateUserLevel(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            $userId = $request->getAttribute('userId');
            
            if (!$userId) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }
            
            // Validar datos
            if (!isset($data['level']) || empty($data['level'])) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'El campo level es requerido'
                ], 400);
            }
            
            $level = $data['level'];
            $score = isset($data['score']) ? (int) $data['score'] : 0;
            
            // Actualizar nivel
            $updated = $this->surveyService->updateUserLevel($userId, $level, $score);
            
            if ($updated) {
                return $this->jsonResponse($response, [
                    'success' => true,
                    'message' => 'Nivel actualizado exitosamente'
                ]);
            } else {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Error al actualizar el nivel'
                ], 400);
            }
            
        } catch (\Exception $e) {
            error_log('Error en updateUserLevel: ' . $e->getMessage());
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }
    
    /**
     * Obtener estadísticas de nivelación
     */
    public function getLevelingStats(Request $request, Response $response): Response
    {
        try {
            $userId = $request->getAttribute('userId');
            
            if (!$userId) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }
            
            $stats = $this->surveyService->getLevelingStats();
            
            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            error_log('Error en getLevelingStats: ' . $e->getMessage());
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }
    
    /**
     * Validar datos de la encuesta
     */
    private function validateSurveyData(array $data): array
    {
        $requiredFields = [
            'experience_years' => 'Años de experiencia',
            'weekly_play_frequency' => 'Frecuencia semanal',
            'has_competitive_experience' => 'Experiencia competitiva',
            'technical_level' => 'Nivel técnico',
            'physical_condition' => 'Condición física',
            'tactical_knowledge' => 'Conocimiento táctico'
        ];
        
        foreach ($requiredFields as $field => $label) {
            if (!isset($data[$field])) {
                return [
                    'valid' => false,
                    'message' => "Campo requerido faltante: $label ($field)"
                ];
            }
            
            // Validar tipos para campos numéricos
            if (in_array($field, ['experience_years', 'weekly_play_frequency', 
                                  'technical_level', 'physical_condition', 'tactical_knowledge'])) {
                if (!is_numeric($data[$field])) {
                    return [
                        'valid' => false,
                        'message' => "El campo $label debe ser un número"
                    ];
                }
            }
        }
        
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * Helper para respuestas JSON
     */
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}