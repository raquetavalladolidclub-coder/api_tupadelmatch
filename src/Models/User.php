<?php
namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;
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

    /**
     * Mutator para password - IMPORTANTE: Si ya haces hash en el controlador,
     * NO hagas hash aquí también, o estarás haciendo doble hash
     */
    public function setPasswordAttribute($value)
    {
        // SOLO hacer hash si el valor NO empieza con $2y$ (formato bcrypt)
        if (!empty($value) && !preg_match('/^\$2[ayb]\$/', $value)) {
            // Esto significa que viene en texto plano desde el formulario
            $this->attributes['password'] = password_hash($value, PASSWORD_BCRYPT);
        } else {
            // Ya está hasheado (viene del controlador)
            $this->attributes['password'] = $value;
        }
    }
    
    /**
     * Verificar password
     */
    public function verifyPassword($password)
    {
        return password_verify($password, $this->password);
    }
    
    public function inscripciones(): HasMany
    {
        return $this->hasMany(InscripcionPartido::class, 'user_id');
    }
    
    public function partidosCreados(): HasMany
    {
        return $this->hasMany(Partido::class, 'creador_id');
    }
}