<?php
// src/Services/SurveyService.php
namespace PadelClub\Services;

use PadelClub\Models\Survey;
use PadelClub\Repositories\SurveyRepository;
use PadelClub\Repositories\UserRepository;

class SurveyService
{
    private $surveyRepository;
    private $userRepository;
    
    public function __construct()
    {
        $this->surveyRepository = new SurveyRepository();
        $this->userRepository = new UserRepository();
    }
    
    /**
     * Calcular puntuación basada en las respuestas
     */
    public function calculateScore(Survey $survey): int
    {
        $score = 0;
        
        // Experiencia (0-20 puntos) - Máximo 5 años cuenta como 20 puntos
        $score += min($survey->experience_years, 5) * 4;
        
        // Frecuencia semanal (0-15 puntos)
        $score += min($survey->weekly_play_frequency, 3) * 5;
        
        // Experiencia competitiva (0-10 puntos)
        if ($survey->has_competitive_experience) {
            $score += 10;
        }
        
        // Niveles técnicos (0-45 puntos)
        $score += min($survey->technical_level, 10) * 3;      // 0-30 puntos
        $score += min($survey->physical_condition, 10) * 2;   // 0-20 puntos
        $score += min($survey->tactical_knowledge, 10) * 1;   // 0-10 puntos
        
        return $score;
    }
    
    /**
     * Obtener categoría sugerida basada en la puntuación
     */
    public function getSuggestedCategory(int $score): string
    {
        if ($score >= 80) {
            return 'Avanzado';
        } elseif ($score >= 60) {
            return 'Intermedio-Alto';
        } elseif ($score >= 40) {
            return 'Intermedio';
        } elseif ($score >= 20) {
            return 'Principiante-Avanzado';
        } else {
            return 'Principiante';
        }
    }
    
    /**
     * Guardar encuesta en la base de datos
     */
    public function saveSurvey(Survey $survey): Survey
    {
        return $this->surveyRepository->save($survey);
    }
    
    /**
     * Obtener encuesta del usuario
     */
    public function getUserSurvey(int $userId): ?Survey
    {
        return $this->surveyRepository->findByUserId($userId);
    }
    
    /**
     * Actualizar nivel del usuario
     */
    public function updateUserLevel(int $userId, string $level, int $score): bool
    {
        $user = $this->userRepository->findById($userId);
        
        if (!$user) {
            return false;
        }
        
        $user->categoria = $level;
        $user->nivel_puntuacion = $score;
        $user->updated_at = date('Y-m-d H:i:s');
        
        return $this->userRepository->update($user);
    }
    
    /**
     * Obtener estadísticas de nivelación
     */
    public function getLevelingStats(): array
    {
        $stats = $this->surveyRepository->getStats();
        
        // Si no hay estadísticas, devolver estructura vacía
        if (!$stats) {
            return [
                'total_surveys' => 0,
                'average_score' => 0,
                'category_distribution' => [
                    'Principiante' => 0,
                    'Principiante-Avanzado' => 0,
                    'Intermedio' => 0,
                    'Intermedio-Alto' => 0,
                    'Avanzado' => 0
                ],
                'average_experience' => 0,
                'competitive_players_percentage' => 0
            ];
        }
        
        return $stats;
    }
}