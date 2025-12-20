<?php
// src/Models/Survey.php
namespace PadelClub\Models;

class Survey
{
    public $id;
    public $user_id;
    public $experience_years;
    public $weekly_play_frequency;
    public $has_competitive_experience;
    public $technical_level;
    public $physical_condition;
    public $tactical_knowledge;
    public $previous_category;
    public $calculated_score;
    public $suggested_category;
    public $created_at;
    public $updated_at;
    
    public function __construct()
    {
        $this->created_at = date('Y-m-d H:i:s');
        $this->updated_at = date('Y-m-d H:i:s');
    }
}