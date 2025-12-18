<?php
namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResultadoPartido extends Model
{
    protected $table = 'resultados_partidos';
    protected $primaryKey = 'id';
    public $timestamps = true;
    
    protected $fillable = [
        'partido_id',
        'sets_ganados_equipo_a',
        'sets_ganados_equipo_b',
        'puntos_totales_equipo_a',
        'puntos_totales_equipo_b',
        'equipo_ganador'
    ];
    
    protected $casts = [
        'sets_ganados_equipo_a' => 'integer',
        'sets_ganados_equipo_b' => 'integer',
        'puntos_totales_equipo_a' => 'integer',
        'puntos_totales_equipo_b' => 'integer'
    ];
    
    public function partido(): BelongsTo
    {
        return $this->belongsTo(Partido::class, 'partido_id');
    }
    
    public function sets(): HasMany
    {
        return $this->hasMany(ResultadoSet::class, 'resultado_id');
    }
    
    public function getGanadorAttribute(): string
    {
        return $this->equipo_ganador == 'A' ? 'Equipo A' : 'Equipo B';
    }
    
    public function getResultadoSetsAttribute(): string
    {
        return "{$this->sets_ganados_equipo_a}-{$this->sets_ganados_equipo_b}";
    }
}