<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminInvitation extends Model
{
    use HasFactory;

    protected $table = 'admin_invitations';
    protected $primaryKey = 'idInvitation';

    protected $fillable = [
        'token',
        'email',
        'niveau_acces',
        'generer_par',
        'expire_a',
        'est_actif',
        'utilise_a',
        'utilise_par',
        'statut_approbation', // ⭐⭐ NOUVEAU: en_attente, approuve, rejete
        'admin_validateur',   // ⭐⭐ NOUVEAU: ID de l'admin qui a validé/rejeté
        'date_approbation',   // ⭐⭐ NOUVEAU: Date de validation
    ];

    protected $casts = [
        'expire_a' => 'datetime',
        'utilise_a' => 'datetime',
        'est_actif' => 'boolean',
        'date_approbation' => 'datetime',
    ];

    /**
     * Relation avec l'admin qui a généré l'invitation
     */
    public function generateur(): BelongsTo
    {
        return $this->belongsTo(Administrateur::class, 'generer_par', 'idAdministrateur');
    }

    /**
     * Relation avec l'admin qui a utilisé l'invitation
     */
    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Administrateur::class, 'utilise_par', 'idAdministrateur');
    }

    /**
     * Vérifier si l'invitation est expirée
     */
    public function isExpired(): bool
    {
        return $this->expire_a->isPast();
    }

    /**
     * Vérifier si l'invitation est valide
     */
    public function isValid(): bool
    {
        return $this->est_actif && !$this->isExpired() && !$this->utilise_a;
    }

    /**
     * Nettoyer les invitations expirées
     */
    public static function cleanExpiredInvitations(): void
    {
        static::where('expire_a', '<', now())
            ->orWhere('est_actif', false)
            ->delete();
    }
}
