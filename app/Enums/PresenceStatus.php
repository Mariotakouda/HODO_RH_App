<?php

namespace App\Enums;

enum PresenceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Présent',
            self::Absent => 'Absent',
            self::Late => 'En retard',
        };
    }
}
