<?php

namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordResetToken extends Model
{
    protected $table = 'password_reset_tokens';
    
    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
        'is_used'
    ];
    
    protected $dates = ['expires_at'];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function isValid(): bool
    {
        return !$this->is_used && $this->expires_at > now();
    }
}