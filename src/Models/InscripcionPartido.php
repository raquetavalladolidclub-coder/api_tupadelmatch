<?php
namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InscripcionPartido extends Model
{
    protected $table = 'inscripciones_partidos';
    protected $primaryKey = 'id';
    public $timestamps = true;
    
    protected $fillable = [
        'partido_id',
        'user_id',
        'tipoReserva',
        'estado',
        'comentario'
    ];
    
    protected $casts = [
        'fecha_inscripcion' => 'datetime'
    ];
    
    public function partido(): BelongsTo
    {
        return $this->belongsTo(Partido::class, 'partido_id');
    }
    
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}