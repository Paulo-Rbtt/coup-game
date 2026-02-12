<?php

namespace App\Enums;

enum Character: string
{
    case DUKE = 'duke';
    case ASSASSIN = 'assassin';
    case CAPTAIN = 'captain';
    case AMBASSADOR = 'ambassador';
    case CONTESSA = 'contessa';

    public function label(): string
    {
        return match ($this) {
            self::DUKE => 'Duque',
            self::ASSASSIN => 'Assassino',
            self::CAPTAIN => 'Capitão',
            self::AMBASSADOR => 'Embaixador',
            self::CONTESSA => 'Condessa',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DUKE => '#7c3aed',
            self::ASSASSIN => '#1e1e1e',
            self::CAPTAIN => '#2563eb',
            self::AMBASSADOR => '#16a34a',
            self::CONTESSA => '#dc2626',
        };
    }
}
