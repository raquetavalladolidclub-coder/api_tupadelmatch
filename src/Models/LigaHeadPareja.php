<?php

namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pareja inscrita en una liga HEAD.
 *
 * Tabla: liga_head_parejas
 */
class LigaHeadPareja extends Model
{
    protected $table = 'liga_head_parejas';
    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'liga_id', 'jugador1_id', 'jugador2_id', 'estado',
        'confirmada_por', 'confirmada_at',
    ];

    protected $casts = [
        'liga_id'     => 'integer',
        'jugador1_id' => 'integer',
        'jugador2_id' => 'integer',
    ];

    public function liga()
    {
        return $this->belongsTo(LigaHead::class, 'liga_id');
    }

    public function jugador1()
    {
        return $this->belongsTo(User::class, 'jugador1_id');
    }

    public function jugador2()
    {
        return $this->belongsTo(User::class, 'jugador2_id');
    }
}
