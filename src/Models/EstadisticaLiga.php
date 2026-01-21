<?php
namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstadisticaLiga extends Model
{
    protected $table = 'estadisticas_liga';
    protected $primaryKey = 'id';
    public $timestamps = true;
    
    protected $fillable = [
        'user_id',
        'codLiga',
        'partidos_jugados',
        'partidos_ganados',
        'partidos_perdidos',
        'sets_ganados',
        'sets_perdidos',
        'puntos_a_favor',
        'puntos_en_contra',
        'puntos_ranking'
    ];
    
    protected $casts = [
        'partidos_jugados' => 'integer',
        'partidos_ganados' => 'integer',
        'partidos_perdidos' => 'integer',
        'sets_ganados' => 'integer',
        'sets_perdidos' => 'integer',
        'puntos_a_favor' => 'integer',
        'puntos_en_contra' => 'integer',
        'puntos_ranking' => 'integer'
    ];
    
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
    
    public function getPorcentajeVictoriasAttribute(): float
    {
        if ($this->partidos_jugados == 0) {
            return 0.0;
        }
        
        return round(($this->partidos_ganados / $this->partidos_jugados) * 100, 1);
    }
    
    public function getDiferenciaSetsAttribute(): int
    {
        return $this->sets_ganados - $this->sets_perdidos;
    }
    
    public function getDiferenciaPuntosAttribute(): int
    {
        return $this->puntos_a_favor - $this->puntos_en_contra;
    }
    
    public function getPromedioPuntosPorPartidoAttribute(): float
    {
        if ($this->partidos_jugados == 0) {
            return 0.0;
        }
        
        return round($this->puntos_a_favor / $this->partidos_jugados, 1);
    }
}