<?php
namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;

class FanaticEvento extends Model
{
    protected $table = 'fanatic_eventos';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'nombre',
        'fecha',
        'hora',
        'lugar',
        'reglamento',
        'activo',
    ];

    protected $casts = [
        'fecha'  => 'date',
        'activo' => 'boolean',
    ];

    public function niveles()
    {
        return $this->hasMany(FanaticNivel::class, 'evento_id');
    }

    public function registros()
    {
        return $this->hasMany(FanaticRegistro::class, 'evento_id');
    }
}
