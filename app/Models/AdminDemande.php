<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminDemande extends Model
{
    use HasFactory;

    protected $table = 'admin_demandes';
    protected $primaryKey = 'idDemande';
        // ⭐⭐ CONSTANTES POUR LES STATUTS
    const STATUT_EN_ATTENTE = 0;
    const STATUT_APPROUVE = 1;
    const STATUT_REJETE = 2;

    protected $fillable = [
        'idUtilisateur',
        'idInvitation',
        'statut',
        'admin_validateur',
        'date_validation',
        'raison_rejet'
    ];

    protected $casts = [
        'date_validation' => 'datetime',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'idUtilisateur');
    }

    public function invitation()
    {
        return $this->belongsTo(AdminInvitation::class, 'idInvitation');
    }

    public function validateur()
    {
        return $this->belongsTo(Administrateur::class, 'admin_validateur', 'idAdministrateur');
    }


    public function getStatutTexteAttribute()
    {
        switch ($this->statut) {
            case self::STATUT_EN_ATTENTE:
                return 'en_attente';
            case self::STATUT_APPROUVE:
                return 'approuve';
            case self::STATUT_REJETE:
                return 'rejete';
            default:
                return 'inconnu';
        }
    }
}
