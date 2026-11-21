<?php

namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Categoría en la que se juega una liga HEAD (una liga puede tener varias).
 *
 * Tabla: liga_head_categorias
 */
class LigaHeadCategoria extends Model
{
    protected $table = 'liga_head_categorias';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = ['liga_id', 'categoria', 'plazas_max'];

    protected $casts = [
        'liga_id'    => 'integer',
        'plazas_max' => 'integer',
    ];

    public function liga()
    {
        return $this->belongsTo(LigaHead::class, 'liga_id');
    }
}
