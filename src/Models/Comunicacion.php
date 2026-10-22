<?php

namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Comunicación que el club publica en la app (texto, imagen o ambos).
 *
 * Tabla: comunicaciones
 */
class Comunicacion extends Model
{
    protected $table = 'comunicaciones';
    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'titulo',
        'texto',
        'imagen',
        'activa',
        'orden',
        'fecha_inicio',
        'fecha_fin',
        'id_admin',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'orden'  => 'integer',
    ];
}
