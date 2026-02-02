<?php
namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Survey extends Model
{
    protected $table = 'surveys';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'experience_years',
        'weekly_play_frequency',
        'has_competitive_experience',
        'technical_level',
        'physical_condition',
        'tactical_knowledge',
        'previous_category',
        'calculated_score',
        'suggested_category'
    ];

    protected $casts = [
        'has_competitive_experience' => 'boolean',
        'experience_years' => 'integer',
        'weekly_play_frequency' => 'integer',
        'technical_level' => 'integer',
        'physical_condition' => 'integer',
        'tactical_knowledge' => 'integer',
        'calculated_score' => 'integer'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
