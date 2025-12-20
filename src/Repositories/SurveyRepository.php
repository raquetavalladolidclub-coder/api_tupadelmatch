<?php
// src/Repositories/SurveyRepository.php
namespace PadelClub\Repositories;

use PadelClub\Models\Survey;
use PDO;

class SurveyRepository
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getConnection();
    }
    
    /**
     * Guardar encuesta
     */
    public function save(Survey $survey): Survey
    {
        $sql = "INSERT INTO surveys 
                (user_id, experience_years, weekly_play_frequency, has_competitive_experience,
                 technical_level, physical_condition, tactical_knowledge, previous_category,
                 calculated_score, suggested_category, created_at, updated_at)
                VALUES (:user_id, :experience_years, :weekly_play_frequency, :has_competitive_experience,
                        :technical_level, :physical_condition, :tactical_knowledge, :previous_category,
                        :calculated_score, :suggested_category, :created_at, :updated_at)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $survey->user_id,
            ':experience_years' => $survey->experience_years,
            ':weekly_play_frequency' => $survey->weekly_play_frequency,
            ':has_competitive_experience' => $survey->has_competitive_experience ? 1 : 0,
            ':technical_level' => $survey->technical_level,
            ':physical_condition' => $survey->physical_condition,
            ':tactical_knowledge' => $survey->tactical_knowledge,
            ':previous_category' => $survey->previous_category,
            ':calculated_score' => $survey->calculated_score,
            ':suggested_category' => $survey->suggested_category,
            ':created_at' => $survey->created_at,
            ':updated_at' => $survey->updated_at
        ]);
        
        $survey->id = $this->db->lastInsertId();
        return $survey;
    }
    
    /**
     * Buscar encuesta por ID de usuario
     */
    public function findByUserId(int $userId): ?Survey
    {
        $sql = "SELECT * FROM surveys WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        $survey = new Survey();
        $survey->id = $row['id'];
        $survey->user_id = $row['user_id'];
        $survey->experience_years = $row['experience_years'];
        $survey->weekly_play_frequency = $row['weekly_play_frequency'];
        $survey->has_competitive_experience = (bool) $row['has_competitive_experience'];
        $survey->technical_level = $row['technical_level'];
        $survey->physical_condition = $row['physical_condition'];
        $survey->tactical_knowledge = $row['tactical_knowledge'];
        $survey->previous_category = $row['previous_category'];
        $survey->calculated_score = $row['calculated_score'];
        $survey->suggested_category = $row['suggested_category'];
        $survey->created_at = $row['created_at'];
        $survey->updated_at = $row['updated_at'];
        
        return $survey;
    }
    
    /**
     * Obtener estadísticas de las encuestas
     */
    public function getStats(): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_surveys,
                AVG(calculated_score) as average_score,
                AVG(experience_years) as average_experience,
                (SUM(has_competitive_experience) / COUNT(*) * 100) as competitive_percentage,
                suggested_category,
                COUNT(suggested_category) as category_count
            FROM surveys
            GROUP BY suggested_category
        ";
        
        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stats = [
            'total_surveys' => 0,
            'average_score' => 0,
            'average_experience' => 0,
            'competitive_players_percentage' => 0,
            'category_distribution' => [
                'Principiante' => 0,
                'Principiante-Avanzado' => 0,
                'Intermedio' => 0,
                'Intermedio-Alto' => 0,
                'Avanzado' => 0
            ]
        ];
        
        if ($rows) {
            foreach ($rows as $row) {
                $stats['total_surveys'] += $row['category_count'];
                $stats['category_distribution'][$row['suggested_category']] = $row['category_count'];
            }
            
            // Obtener promedios generales
            $avgSql = "SELECT 
                        AVG(calculated_score) as avg_score,
                        AVG(experience_years) as avg_exp,
                        (SUM(has_competitive_experience) / COUNT(*) * 100) as comp_percent
                       FROM surveys";
            $avgStmt = $this->db->query($avgSql);
            $avgRow = $avgStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($avgRow) {
                $stats['average_score'] = round($avgRow['avg_score'], 2);
                $stats['average_experience'] = round($avgRow['avg_exp'], 1);
                $stats['competitive_players_percentage'] = round($avgRow['comp_percent'], 1);
            }
        }
        
        return $stats;
    }
}