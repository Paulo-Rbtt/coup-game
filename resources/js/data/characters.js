export const CHARACTERS = {
    duke: {
        name: 'Duque',
        color: '#7c3aed',
        darkColor: '#5b21b6',
        icon: 'crown',
        action: 'Taxar (+3 moedas)',
        blocks: 'Ajuda Externa',
    },
    assassin: {
        name: 'Assassino',
        color: '#be123c',
        darkColor: '#9f1239',
        icon: 'skull',
        action: 'Assassinar (3 moedas, alvo perde influência)',
        blocks: null,
    },
    captain: {
        name: 'Capitão',
        color: '#2563eb',
        darkColor: '#1d4ed8',
        icon: 'anchor',
        action: 'Extorquir (pega 2 moedas do alvo)',
        blocks: 'Extorsão',
    },
    ambassador: {
        name: 'Embaixador',
        color: '#16a34a',
        darkColor: '#15803d',
        icon: 'scroll',
        action: 'Trocar (troca cartas com o baralho)',
        blocks: 'Extorsão',
    },
    contessa: {
        name: 'Condessa',
        color: '#dc2626',
        darkColor: '#b91c1c',
        icon: 'shield',
        action: null,
        blocks: 'Assassinato',
    },
};

export const ACTIONS = {
    income: { label: 'Renda', description: '+1 moeda', cost: 0, needsTarget: false, character: null },
    foreign_aid: { label: 'Ajuda Externa', description: '+2 moedas', cost: 0, needsTarget: false, character: null },
    coup: { label: 'Golpe de Estado', description: 'Alvo perde influência', cost: 7, needsTarget: true, character: null },
    tax: { label: 'Taxar', description: '+3 moedas', cost: 0, needsTarget: false, character: 'duke' },
    assassinate: { label: 'Assassinar', description: 'Alvo perde influência', cost: 3, needsTarget: true, character: 'assassin' },
    steal: { label: 'Extorquir', description: 'Pega 2 moedas do alvo', cost: 0, needsTarget: true, character: 'captain' },
    exchange: { label: 'Trocar', description: 'Troca cartas com baralho', cost: 0, needsTarget: false, character: 'ambassador' },
};

export const PHASE_LABELS = {
    lobby: 'Aguardando jogadores',
    action_selection: 'Escolher ação',
    awaiting_challenge_action: 'Janela de contestação',
    resolving_challenge_action: 'Resolvendo contestação',
    awaiting_block: 'Janela de bloqueio',
    awaiting_challenge_block: 'Contestar bloqueio?',
    resolving_challenge_block: 'Resolvendo contestação do bloqueio',
    awaiting_influence_loss: 'Escolher influência para perder',
    awaiting_exchange_return: 'Escolher cartas para devolver',
    resolving_action: 'Resolvendo ação',
    turn_complete: 'Turno concluído',
    game_over: 'Fim de jogo',
};
