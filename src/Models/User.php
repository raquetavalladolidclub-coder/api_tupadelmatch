<?php
namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;

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
}