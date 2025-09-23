<?php
namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    public $timestamps = true;
    
    protected $fillable = [
        'google_id',
        'email',
        'name',
        'avatar',
        'phone',
        'level',
        'is_active'
    ];
    
    protected $hidden = [
        'google_id'
    ];

    public function inscripciones(): HasMany
    {
        return $this->hasMany(InscripcionPartido::class, 'user_id');
    }
    
    public function partidosCreados(): HasMany
    {
        return $this->hasMany(Partido::class, 'creador_id');
    }
}