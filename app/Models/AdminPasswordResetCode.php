<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminPasswordResetCode extends Model
{
    use HasFactory;

    protected $table = 'admin_password_reset_codes';
    protected $primaryKey = 'idResetCode';
    public $timestamps = true;

    protected $fillable = [
        'email',
        'code',
        'expires_at',
        'is_used'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean'
    ];

    // Vérifier si le code est valide
    public function isValid()
    {
        return !$this->is_used && $this->expires_at->isFuture();
    }

    // Marquer comme utilisé
    public function markAsUsed()
    {
        $this->update(['is_used' => true]);
    }

    // Supprimer les anciens codes
  // Supprimer les anciens codes
    public static function cleanExpiredCodes($email = null)
    {
        $query = self::where(function($q) {
            $q->where('expires_at', '<', now())
            ->orWhere('is_used', true);
        });
        
        if ($email) {
            $query->where('email', $email);
        }
        
        $query->delete();
    }
}