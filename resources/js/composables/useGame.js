import { reactive, ref, computed, onUnmounted } from 'vue';
import api from '../api';

const state = reactive({
    game: null,
    player: null,
    error: null,
    loading: false,
});

let echoChannels = [];

function cleanupChannels() {
    echoChannels.forEach(ch => {
        if (window.Echo) {
            window.Echo.leaveChannel(ch);
        }
    });
    echoChannels = [];
}

function listenToGame() {
    cleanupChannels();
    if (!state.game || !window.Echo) return;

    const gameChannel = `game.${state.game.id}`;
    window.Echo.channel(gameChannel)
        .listen('.game.updated', (e) => {
            state.game = e.state;
        });
    echoChannels.push(gameChannel);

    if (state.player?.token) {
        const playerChannel = `player.${state.player.token}`;
        window.Echo.channel(playerChannel)
            .listen('.private.updated', (e) => {
                // Player was kicked from the lobby
                if (e.state?.kicked) {
                    cleanupChannels();
                    clearSession();
                    state.game = null;
                    state.player = null;
                    state.error = 'Você foi expulso da sala pelo anfitrião.';
                    return;
                }
                // Merge private state into player
                state.player = { ...state.player, ...e.state };
            });
        echoChannels.push(playerChannel);
    }
}

function saveSession() {
    if (state.player?.token) {
        localStorage.setItem('coup_token', state.player.token);
        localStorage.setItem('coup_game_id', state.game?.id);
    }
}

function clearSession() {
    localStorage.removeItem('coup_token');
    localStorage.removeItem('coup_game_id');
}

export function useGame() {
    // ── Lobby actions ────────────────
    async function createGame(playerName) {
        state.loading = true;
        state.error = null;
        try {
            const { data } = await api.post('/games', { player_name: playerName });
            state.game = data.game;
            state.player = data.player;
            saveSession();
            listenToGame();
        } catch (e) {
            state.error = e.response?.data?.error || 'Erro ao criar sala.';
        } finally {
            state.loading = false;
        }
    }

    async function joinGame(code, playerName) {
        state.loading = true;
        state.error = null;
        try {
            const { data } = await api.post('/games/join', {
                code: code.toUpperCase(),
                player_name: playerName,
            });
            state.game = data.game;
            state.player = data.player;
            saveSession();
            listenToGame();
        } catch (e) {
            state.error = e.response?.data?.error || 'Erro ao entrar na sala.';
        } finally {
            state.loading = false;
        }
    }

    async function reconnect() {
        const token = localStorage.getItem('coup_token');
        if (!token) return false;
        try {
            const { data } = await api.post('/games/reconnect', { token });
            state.game = data.game;
            state.player = data.player;
            listenToGame();
            return true;
        } catch {
            clearSession();
            return false;
        }
    }

    async function startGame() {
        state.error = null;
        try {
            await api.post(`/games/${state.game.id}/start`);
        } catch (e) {
            state.error = e.response?.data?.error || 'Erro ao iniciar.';
        }
    }

    async function toggleReady() {
        state.error = null;
        try {
            const { data } = await api.post(`/games/${state.game.id}/toggle-ready`);
            if (state.player) {
                state.player.is_ready = data.is_ready;
            }
        } catch (e) {
            state.error = e.response?.data?.error || 'Erro ao alterar status.';
        }
    }

    async function refreshState() {
        if (!state.game) return;
        try {
            const { data } = await api.get(`/games/${state.game.id}/state`);
            state.game = data.game;
            if (data.player) {
                state.player = { ...state.player, ...data.player };
            }
        } catch {
            // silent
        }
    }

    // ── Game actions ─────────────────
    async function declareAction(action, targetId = null) {
        state.error = null;
        try {
            await api.post(`/games/${state.game.id}/action`, {
                action,
                target_id: targetId,
            });
        } catch (e) {
            state.error = e.response?.data?.error || 'Erro ao declarar ação.';
        }
    }

    async function pass() {
        state.error = null;
        try {
            await api.post(`/games/${state.game.id}/pass`);
        } catch (e) {
            state.error = e.response?.data?.error || 'Erro ao passar.';
        }
    }

    async function challengeAction() {
        state.error = null;
        try {
            await api.post(`/games/${state.game.id}/challenge`);
        } catch (e) {
            state.error = e.response?.data?.error || 'Erro ao contestar.';
        }
    }

    async function declareBlock(character) {
        state.error = null;
        try {
            await api.post(`/games/${state.game.id}/block`, { character });
        } catch (e) {
            state.error = e.response?.data?.error || 'Erro ao bloquear.';
        }
    }

    async function challengeBlock() {
        state.error = null;
        try {
            await api.post(`/games/${state.game.id}/challenge-block`);
        } catch (e) {
            state.error = e.response?.data?.error || 'Erro ao contestar bloqueio.';
        }
    }

    async function loseInfluence(character) {
        state.error = null;
        try {
            await api.post(`/games/${state.game.id}/lose-influence`, { character });
        } catch (e) {
            state.error = e.response?.data?.error || 'Erro ao perder influência.';
        }
    }

    async function exchangeCards(keep) {
        state.error = null;
        try {
            await api.post(`/games/${state.game.id}/exchange`, { keep });
        } catch (e) {
            state.error = e.response?.data?.error || 'Erro ao trocar cartas.';
        }
    }

    function leaveGame() {
        cleanupChannels();
        clearSession();
        state.game = null;
        state.player = null;
        state.error = null;
    }

    async function leaveLobby() {
        if (!state.game || !state.player) return;
        try {
            await api.post(`/games/${state.game.id}/leave-lobby`);
        } catch {
            // Even if API fails, leave locally
        }
        leaveGame();
    }

    async function abandonGame() {
        if (!state.game || !state.player) return;
        try {
            await api.post(`/games/${state.game.id}/leave`);
        } catch {
            // Even if API fails, leave locally
        }
        leaveGame();
    }

    async function rematchGame() {
        state.error = null;
        try {
            await api.post(`/games/${state.game.id}/rematch`);
        } catch (e) {
            state.error = e.response?.data?.error || 'Erro ao solicitar revanche.';
        }
    }

    // ── Computed ─────────────────────
    const isMyTurn = computed(() => {
        return state.game?.current_player_id === state.player?.id;
    });

    const myInfluences = computed(() => {
        return state.player?.influences || [];
    });

    const isHost = computed(() => {
        return state.player?.is_host ?? false;
    });

    const phase = computed(() => state.game?.phase || 'lobby');

    const isSpectator = computed(() => {
        return state.player?.is_spectator ?? false;
    });

    const otherPlayers = computed(() => {
        if (!state.game?.players || !state.player) return [];
        // Spectators see all game players (non-spectators)
        if (state.player.is_spectator) {
            return state.game.players.filter(p => !p.is_spectator);
        }
        return state.game.players.filter(p => p.id !== state.player.id);
    });

    const aliveOpponents = computed(() => {
        return otherPlayers.value.filter(p => p.is_alive);
    });

    const mustCoup = computed(() => {
        return state.player?.coins >= 10;
    });

    const hasPassed = computed(() => {
        const passed = state.game?.turn_state?.passed_players || [];
        return passed.includes(state.player?.id);
    });

    return {
        state,
        createGame,
        joinGame,
        reconnect,
        startGame,
        toggleReady,
        refreshState,
        declareAction,
        pass,
        challengeAction,
        declareBlock,
        challengeBlock,
        loseInfluence,
        exchangeCards,
        leaveGame,
        leaveLobby,
        abandonGame,
        rematchGame,
        isMyTurn,
        myInfluences,
        isHost,
        isSpectator,
        phase,
        otherPlayers,
        aliveOpponents,
        mustCoup,
        hasPassed,
    };
}
