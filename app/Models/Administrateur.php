<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Foundation\Auth\User as Authenticatable;
    use Illuminate\Notifications\Notifiable;
    use Illuminate\Support\Facades\Hash;
    use Illuminate\Support\Facades\DB;

    class Administrateur extends Authenticatable
    {
        use HasFactory;

        protected $table = 'administrateurs';
        protected $primaryKey = 'idAdministrateur';
        public $timestamps = true;

        protected $fillable = [
            'idUtilisateur',
            'niveau_acces',
            'permissions',
            'est_actif',
            'derniere_connexion',
            'ip_connexion'
        ];

        protected $hidden = [
            'remember_token',
        ];

        protected $casts = [
            'permissions' => 'array',
            'est_actif' => 'boolean',
            'derniere_connexion' => 'datetime',
        ];

        // Relations
        public function utilisateur()
        {
            return $this->belongsTo(Utilisateur::class, 'idUtilisateur');
        }

        // Méthodes d'accès
        public function getNomCompletAttribute()
        {
            return $this->utilisateur ? $this->utilisateur->prenomUtilisateur . ' ' . $this->utilisateur->nomUtilisateur : 'N/A';
        }

        public function getEmailAttribute()
        {
            return $this->utilisateur ? $this->utilisateur->email : 'N/A';
        }

        public function getTelephoneAttribute()
        {
            return $this->utilisateur ? $this->utilisateur->tel : 'N/A';
        }

        // Méthodes de vérification de permissions
        public function isSuperAdmin()
        {
            return $this->niveau_acces === 'super_admin';
        }

        public function isAdmin()
        {
            return $this->niveau_acces === 'admin';
        }

        public function isModerateur()
        {
            return $this->niveau_acces === 'moderateur';
        }

        public function hasPermission($permission)
        {
            if ($this->isSuperAdmin()) {
                return true;
            }

            $permissions = $this->permissions ?? [];
            return in_array($permission, $permissions);
        }

        // Marquer la connexion
        public function marquerConnexion($ip = null)
        {
            $this->update([
                'derniere_connexion' => now(),
                'ip_connexion' => $ip ?? request()->ip()
            ]);
        }

        

        // Création d'un administrateur
                // Création d'un administrateur
        public static function creerAdministrateur($dataUtilisateur, $dataAdmin = [])
        {
            DB::beginTransaction();
            try {
                // Créer l'utilisateur
                $utilisateur = Utilisateur::create([
                    'prenomUtilisateur' => $dataUtilisateur['prenomUtilisateur'],
                    'nomUtilisateur' => $dataUtilisateur['nomUtilisateur'],
                    'email' => $dataUtilisateur['email'],
                    'tel' => $dataUtilisateur['tel'],
                    'mot_de_passe' => Hash::make($dataUtilisateur['mot_de_passe']),
                    'idRole' => 1, // Rôle admin
                ]);

                // ⭐⭐ CORRECTION : Par défaut, les admins sont INACTIFS
                // Sauf si explicitement spécifié comme actif dans $dataAdmin
                $estActif = isset($dataAdmin['est_actif']) ? $dataAdmin['est_actif'] : false;

                // Créer l'administrateur
                $admin = self::create(array_merge([
                    'idUtilisateur' => $utilisateur->idUtilisateur,
                    'niveau_acces' => 'admin',
                    'est_actif' => $estActif, // ⭐⭐ Utiliser la variable
                ], $dataAdmin));

                DB::commit();
                return $admin->load('utilisateur');

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        }

        // Dans app/Models/Administrateur.php
        public function scopeEnLigne($query)
        {
            return $query->whereHas('utilisateur', function($q) {
                $q->where('derniere_connexion', '>=', now()->subMinutes(15));
            });
        }

        public function scopeActifs($query)
        {
            return $query->where('est_actif', true);
        }

        // Dans app/Models/Utilisateur.php
        public function scopeRecentActivity($query, $days = 7)
        {
            return $query->where('derniere_connexion', '>=', now()->subDays($days))
                        ->orWhere('created_at', '>=', now()->subDays($days));
        }
    }
