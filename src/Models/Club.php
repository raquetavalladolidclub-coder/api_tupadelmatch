<?php

namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Club extends Model
{
    protected $table = 'clubes';

    protected $fillable = [
        'nombre',
        'direccion',
        'telefono',
        'email',
        'url_logo',
        'url_imagen'
    ];

    public function partidos(): HasMany
    {
        return $this->hasMany(Partido::class, 'idClub');
    }
}
