<?php
namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialRanking extends Model
{
    protected $table = 'historial_ranking';
    protected $primaryKey = 'id';
    public $timestamps = true;
    
    protected $fillable = [
        'usuario_id',
        'cod_liga',
        'puntos_anterior',
        'puntos_nuevo',
        'diferencia',
        'motivo',
        'partido_id'
    ];
    
    protected $casts = [
        'puntos_anterior' => 'integer',
        'puntos_nuevo' => 'integer',
        'diferencia' => 'integer'
    ];
    
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
    
    public function partido(): BelongsTo
    {
        return $this->belongsTo(Partido::class, 'partido_id');
    }
    
    public function getTendenciaAttribute(): string
    {
        return $this->diferencia >= 0 ? 'positiva' : 'negativa';
    }
}