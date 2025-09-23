<?php
namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    protected $table = 'tokens';
    protected $primaryKey = 'id';
    public $timestamps = true;
    
    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
        'is_valid'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}