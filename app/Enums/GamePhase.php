<?php

namespace App\Enums;

enum GamePhase: string
{
    case LOBBY = 'lobby';
    case ACTION_SELECTION = 'action_selection';
    case AWAITING_CHALLENGE_ACTION = 'awaiting_challenge_action';
    case RESOLVING_CHALLENGE_ACTION = 'resolving_challenge_action';
    case AWAITING_BLOCK = 'awaiting_block';
    case AWAITING_CHALLENGE_BLOCK = 'awaiting_challenge_block';
    case RESOLVING_CHALLENGE_BLOCK = 'resolving_challenge_block';
    case AWAITING_INFLUENCE_LOSS = 'awaiting_influence_loss';
    case AWAITING_EXCHANGE_RETURN = 'awaiting_exchange_return';
    case RESOLVING_ACTION = 'resolving_action';
    case TURN_COMPLETE = 'turn_complete';
    case GAME_OVER = 'game_over';

    public function label(): string
    {
        return match ($this) {
            self::LOBBY => 'Aguardando jogadores',
            self::ACTION_SELECTION => 'Escolher ação',
            self::AWAITING_CHALLENGE_ACTION => 'Janela de contestação',
            self::RESOLVING_CHALLENGE_ACTION => 'Resolvendo contestação',
            self::AWAITING_BLOCK => 'Janela de bloqueio',
            self::AWAITING_CHALLENGE_BLOCK => 'Contestar bloqueio?',
            self::RESOLVING_CHALLENGE_BLOCK => 'Resolvendo contestação do bloqueio',
            self::AWAITING_INFLUENCE_LOSS => 'Escolher influência para perder',
            self::AWAITING_EXCHANGE_RETURN => 'Escolher cartas para devolver',
            self::RESOLVING_ACTION => 'Resolvendo ação',
            self::TURN_COMPLETE => 'Turno concluído',
            self::GAME_OVER => 'Fim de jogo',
        };
    }
}
