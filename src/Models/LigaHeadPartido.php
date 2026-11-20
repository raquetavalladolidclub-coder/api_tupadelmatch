<?php

namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Partido de una liga HEAD.
 *
 * Tabla: liga_head_partidos
 */
class LigaHeadPartido extends Model
{
    protected $table = 'liga_head_partidos';
    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'liga_id', 'jornada_id', 'pareja_a_id', 'pareja_b_id', 'fecha', 'hora',
        'estado', 'propuesto_por', 'propuesto_at', 'validado_rival',
        'validado_rival_at', 'confirmado_club', 'confirmado_por', 'confirmado_at',
    ];

    protected $casts = [
        'liga_id'         => 'integer',
        'jornada_id'      => 'integer',
        'pareja_a_id'     => 'integer',
        'pareja_b_id'     => 'integer',
        'validado_rival'  => 'boolean',
        'confirmado_club' => 'boolean',
    ];

    public function liga()
    {
        return $this->belongsTo(LigaHead::class, 'liga_id');
    }

    public function jornada()
    {
        return $this->belongsTo(LigaHeadJornada::class, 'jornada_id');
    }

    public function parejaA()
    {
        return $this->belongsTo(LigaHeadPareja::class, 'pareja_a_id');
    }

    public function parejaB()
    {
        return $this->belongsTo(LigaHeadPareja::class, 'pareja_b_id');
    }

    public function sets()
    {
        return $this->hasMany(LigaHeadSet::class, 'partido_id')->orderBy('numero');
    }
}
