<?php
namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;

class FanaticNivel extends Model
{
    protected $table = 'fanatic_niveles';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'evento_id',
        'categoria',
        'precio',
        'plazas',
    ];

    protected $casts = [
        'precio' => 'float',
        'plazas' => 'integer',
    ];

    public function evento()
    {
        return $this->belongsTo(FanaticEvento::class, 'evento_id');
    }
}
