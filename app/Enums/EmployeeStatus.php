<?php

namespace App\Enums;

enum EmployeeStatus: string
{
    case Actif = 'actif';
    case Demission = 'demission';
    case Renvoye = 'renvoyer';
    case Retraite = 'retraite';

    public function label(): string
    {
        return match ($this) {
            self::Actif => 'Actif',
            self::Demission => 'Démission',
            self::Renvoye => 'Renvoyé',
            self::Retraite => 'Retraite',
        };
    }
}
