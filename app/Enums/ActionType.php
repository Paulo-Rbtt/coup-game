<?php

namespace App\Enums;

enum ActionType: string
{
    // General actions (no character required)
    case INCOME = 'income';
    case FOREIGN_AID = 'foreign_aid';
    case COUP = 'coup';

    // Character actions
    case TAX = 'tax';               // Duke
    case ASSASSINATE = 'assassinate'; // Assassin
    case STEAL = 'steal';           // Captain
    case EXCHANGE = 'exchange';     // Ambassador

    public function label(): string
    {
        return match ($this) {
            self::INCOME => 'Renda',
            self::FOREIGN_AID => 'Ajuda Externa',
            self::COUP => 'Golpe de Estado',
            self::TAX => 'Taxar',
            self::ASSASSINATE => 'Assassinar',
            self::STEAL => 'Extorquir',
            self::EXCHANGE => 'Trocar',
        };
    }

    public function cost(): int
    {
        return match ($this) {
            self::COUP => 7,
            self::ASSASSINATE => 3,
            default => 0,
        };
    }

    public function requiresTarget(): bool
    {
        return match ($this) {
            self::COUP, self::ASSASSINATE, self::STEAL => true,
            default => false,
        };
    }

    public function requiredCharacter(): ?Character
    {
        return match ($this) {
            self::TAX => Character::DUKE,
            self::ASSASSINATE => Character::ASSASSIN,
            self::STEAL => Character::CAPTAIN,
            self::EXCHANGE => Character::AMBASSADOR,
            default => null,
        };
    }

    public function isChallengeable(): bool
    {
        return $this->requiredCharacter() !== null;
    }

    public function canBeBlocked(): bool
    {
        return match ($this) {
            self::FOREIGN_AID, self::ASSASSINATE, self::STEAL => true,
            default => false,
        };
    }

    /**
     * Returns the characters that can block this action.
     * @return Character[]
     */
    public function blockedBy(): array
    {
        return match ($this) {
            self::FOREIGN_AID => [Character::DUKE],
            self::ASSASSINATE => [Character::CONTESSA],
            self::STEAL => [Character::AMBASSADOR, Character::CAPTAIN],
            default => [],
        };
    }
}
