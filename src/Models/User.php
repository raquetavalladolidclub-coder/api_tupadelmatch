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
    
    // Verificar password
    public function verifyPassword($password)
    {
        return password_verify($password, $this->password);
    }
    
    // Hash password antes de guardar
    public function setPasswordAttribute($password)
    {
        if (!empty($password)) {
            $this->attributes['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
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