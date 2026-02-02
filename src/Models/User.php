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
            'idClub',
            'tokenDevice',
            'username',
            'password',
            'imagePath',
            'nombre',
            'apellidos',
            'email',
            'phone',
            'fullName',
            'role',
            'nivel',
            'genero',
            'categoria',
            'nivel_puntuacion',
            'liga',
            'encuesta',
            'fiabilidad',
            'asistencias',
            'ausencias',
            'is_active',
            'codLiga',
            'notificacionesPush',
            'notificacionesEmail'
        ];
        
        protected $hidden = [
            'tokenDevice',
            'password'
        ];
        
        public function verifyPassword($password)
        {
            return PasswordHelper::verify($password, $this->password);
        }
        
        public function setPasswordAttribute($value)
        {
            if (!empty($value) && !preg_match('/^\$2[ayb]\$/', $value)) {
                $this->attributes['password'] = PasswordHelper::hash($value);
            } else {
                $this->attributes['password'] = $value;
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