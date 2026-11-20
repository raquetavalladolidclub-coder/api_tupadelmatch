<?php

namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jornada semanal de una liga HEAD.
 *
 * Tabla: liga_head_jornadas
 */
class LigaHeadJornada extends Model
{
    protected $table = 'liga_head_jornadas';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = ['liga_id', 'numero', 'fecha_desde', 'fecha_hasta'];

    protected $casts = [
        'numero' => 'integer',
        'liga_id' => 'integer',
    ];

    public function liga()
    {
        return $this->belongsTo(LigaHead::class, 'liga_id');
    }

    public function partidos()
    {
        return $this->hasMany(LigaHeadPartido::class, 'jornada_id');
    }
}
