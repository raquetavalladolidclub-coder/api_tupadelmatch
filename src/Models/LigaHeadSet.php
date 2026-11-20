<?php

namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Set de un partido de liga HEAD.
 *
 * Tabla: liga_head_sets
 */
class LigaHeadSet extends Model
{
    protected $table = 'liga_head_sets';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = ['partido_id', 'numero', 'games_a', 'games_b'];

    protected $casts = [
        'partido_id' => 'integer',
        'numero'     => 'integer',
        'games_a'    => 'integer',
        'games_b'    => 'integer',
    ];
}
