<?php
namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultadoSet extends Model
{
    protected $table = 'resultados_sets';
    protected $primaryKey = 'id';
    public $timestamps = true;
    
    protected $fillable = [
        'resultado_id',
        'numero_set',
        'puntos_equipo_a',
        'puntos_equipo_b'
    ];
    
    protected $casts = [
        'numero_set' => 'integer',
        'puntos_equipo_a' => 'integer',
        'puntos_equipo_b' => 'integer'
    ];
    
    public function resultado(): BelongsTo
    {
        return $this->belongsTo(ResultadoPartido::class, 'resultado_id');
    }
    
    public function getResultadoAttribute(): string
    {
        return "{$this->puntos_equipo_a}-{$this->puntos_equipo_b}";
    }
    
    public function getGanadorSetAttribute(): string
    {
        if ($this->puntos_equipo_a > $this->puntos_equipo_b) {
            return 'A';
        } elseif ($this->puntos_equipo_b > $this->puntos_equipo_a) {
            return 'B';
        }
        return 'Empate';
    }
}