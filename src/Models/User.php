<?php
namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;
use PadelClub\Utils\PasswordHelper;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    protected $table      = 'users';
    protected $primaryKey = 'id';
    public $timestamps    = true;
    
    protected $fillable = [
        'google_id',
        'username',
        'password', // ← Agregar este campo
        'image_path',
        'nombre',
        'apellidos',
        'email',
        'full_name',
        'role',
        'nivel',
        'genero',
        'categoria',
        'fiabilidad',
        'asistencias',
        'ausencias',
        'is_active'
    ];
    
    protected $hidden = [
        'google_id',
        'password' // ← Ocultar password en respuestas
    ];
    
    public function inscripciones(): HasMany
    {
        return $this->hasMany(InscripcionPartido::class, 'user_id');
    }
    
    public function partidosCreados(): HasMany
    {
        return $this->hasMany(Partido::class, 'creador_id');
    }

    public function verifyPassword($password)
    {
        return PasswordHelper::verify($password, $this->password);
    }
    
    /**
     * Mutator para password
     */
    public function setPasswordAttribute($value)
    {
        if (!empty($value) && !preg_match('/^\$2[ayb]\$/', $value)) {
            // Usar PasswordHelper para hash consistente
            $this->attributes['password'] = PasswordHelper::hash($value);
        } else {
            $this->attributes['password'] = $value;
        }
    }
}