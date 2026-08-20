<?php
namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;

class Pista extends Model
{
    protected $table      = 'pistas';
    protected $primaryKey = 'id';
    public $timestamps    = true;

    protected $fillable = [
        'club_id',
        'nombre',
        'imagen',
        'numero',
        'tipo_superficie',
        'tipo_pista',
        'cubierta',
        'iluminacion',
        'numero_pista',
        'precio_hora_individual',
        'precio_hora_completa',
        'disponible',
        'activo',
        'caracteristicas'
    ];
}
