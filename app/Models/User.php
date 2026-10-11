<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'needs_password_change',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'needs_password_change' => 'boolean',
    ];

    /**
     * Relation vers l'employé associé
     */
    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Cache mémoire (durée de la requête HTTP) pour éviter de refaire
     * les mêmes requêtes SQL d'autorisation plusieurs fois par requête
     * (middleware + contrôleur faisaient chacun leur propre vérification).
     */
    private ?bool $isManagerCache = null;
    private ?bool $isAdminCache = null;

    /**
     * Vérifie si l'utilisateur est admin (insensible à la casse)
     */
    public function isAdmin(): bool
    {
        return $this->isAdminCache ??= strtolower($this->role) === UserRole::Admin->value;
    }

    /**
     * Vérifie si l'utilisateur est manager (plusieurs sources possibles).
     * Résultat mémorisé sur l'instance : appelée plusieurs fois pendant la même
     * requête (middleware + contrôleur), elle ne fait les requêtes SQL qu'une seule fois.
     */
    public function isManager(): bool
    {
        if ($this->isManagerCache !== null) {
            return $this->isManagerCache;
        }

        // 1. Vérification du rôle direct (insensible à la casse)
        if (in_array(strtolower($this->role), [UserRole::Admin->value, UserRole::Manager->value])) {
            return $this->isManagerCache = true;
        }

        // 2. Chargement de l'employé (une seule requête, mise en cache par Eloquent
        //    via l'accès en propriété, réutilisée ensuite dans toute la requête HTTP).
        $employee = $this->employee;

        if (!$employee) {
            return $this->isManagerCache = false;
        }

        // 3. Vérifie la table pivot puis, seulement si besoin, la table managers
        //    (au lieu de toujours exécuter les deux requêtes systématiquement).
        $hasManagerRole = $employee->roles()
            ->whereRaw('LOWER(name) = ?', ['manager'])
            ->exists();

        $existsInManagersTable = !$hasManagerRole && \App\Models\Manager::where('employee_id', $employee->id)->exists();

        return $this->isManagerCache = ($hasManagerRole || $existsInManagersTable);
    }

    /**
     * Est admin OU manager (utile pour les middlewares/permissions groupées)
     */
    public function isAdminOrManager(): bool
    {
        return $this->isAdmin() || $this->isManager();
    }
}