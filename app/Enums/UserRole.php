<?php

namespace App\Enums;

/**
 * Rôle stocké sur la table `users` (colonne `role`).
 * Un manager reste un cas particulier détecté dynamiquement
 * (table `managers`, rôle direct, ou rôle via `employee_role`) :
 * voir User::isManager().
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Manager => 'Manager',
            self::Employee => 'Employé',
        };
    }
}
