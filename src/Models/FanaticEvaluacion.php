<?php
namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;

class FanaticEvaluacion extends Model
{
    protected $table = 'fanatic_evaluaciones';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'registro_id',
        'evento_id',
        'user_id',
        'evaluador',
        'tecnica',
        'tactica',
        'fisico',
        'actitud',
        'puntuacion',
        'nivel_anterior',
        'nivel_nuevo',
        'comentario',
    ];

    protected $casts = [
        'tecnica'    => 'integer',
        'tactica'    => 'integer',
        'fisico'     => 'integer',
        'actitud'    => 'integer',
        'puntuacion' => 'float',
    ];

    public function evento()
    {
        return $this->belongsTo(FanaticEvento::class, 'evento_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
