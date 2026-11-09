<?php
namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;

class FanaticRegistro extends Model
{
    protected $table = 'fanatic_registros';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'evento_id',
        'user_id',
        'categoria',
        'estado',
        'pagado',
        'importe',
        'notas',
    ];

    protected $casts = [
        'pagado'  => 'boolean',
        'importe' => 'float',
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
