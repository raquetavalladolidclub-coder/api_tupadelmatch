<?php
namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partido extends Model
{
    protected $table = 'partidos';
    protected $primaryKey = 'id';
    public $timestamps = true;
    
    protected $fillable = [
        'fecha',
        'hora',
        'duracion',
        'pista',
        'tipo',
        'nivel_min',
        'nivel_max',
        'genero',
        'estado',
        'creador_id'
    ];
    
    protected $casts = [
        'fecha' => 'date',
        'hora' => 'string',
        'nivel_min' => 'integer',
        'nivel_max' => 'integer'
    ];
    
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creador_id');
    }
    
    public function inscripciones(): HasMany
    {
        return $this->hasMany(InscripcionPartido::class, 'partido_id');
    }
    
    public function jugadoresConfirmados(): HasMany
    {
        return $this->inscripciones()->where('estado', 'confirmado');
    }
    
    public function getPlazasDisponiblesAttribute(): int
    {
        $maxJugadores = $this->tipo === 'individual' ? 2 : 4;
        $confirmados = $this->jugadoresConfirmados()->count();
        return max(0, $maxJugadores - $confirmados);
    }
    
    public function getEstaCompletoAttribute(): bool
    {
        return $this->plazas_disponibles === 0;
    }
    
    public function usuarioInscrito($userId): bool
    {
        return $this->inscripciones()
            ->where('user_id', $userId)
            ->whereIn('estado', ['pendiente', 'confirmado'])
            ->exists();
    }
}