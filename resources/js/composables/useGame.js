import { reactive, ref, computed, onUnmounted } from 'vue';
import api, { setServerUrl, getServerUrl } from '../api';
import { initEcho } from '../bootstrap';

const state = reactive({
    game: null,
    player: null,
    error: null,
    loading: false,
    // Connection state
    connected: false,
    connectionMode: null, // 'host' | 'client' | null
    hostInfo: null, // { ips, primary_ip, server_port, ws_port }
});

let echoChannels = [];
let pollInterval = null;

function cleanupChannels() {
    echoChannels.forEach(ch => {
        if (window.Echo) {
            window.Echo.leaveChannel(ch);
        }
    });
    echoChannels = [];
}

function stopPolling() {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
}

function startPolling() {
    stopPolling();
    pollInterval = setInterval(() => {
        if (state.game && state.game.phase !== 'lobby') {
            refreshState();
        }
    }, 2000);
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
                // Merge private state into player
                state.player = { ...state.player, ...e.state };
            });
        echoChannels.push(playerChannel);
    }

    // Also start polling as fallback (WebSocket may be unreliable on mobile)
    startPolling();
}

function saveSession() {
    if (state.player?.token) {
        localStorage.setItem('coup_token', state.player.token);
        localStorage.setItem('coup_game_id', state.game?.id);
    }
    // Save connection info for reconnect
    const serverUrl = getServerUrl();
    if (serverUrl) {
        localStorage.setItem('coup_server_url', serverUrl);
    }
}

function clearSession() {
    localStorage.removeItem('coup_token');
    localStorage.removeItem('coup_game_id');
    localStorage.removeItem('coup_server_url');
}

export function useGame() {
    // ── Connection actions ────────────
    async function connectAsHost() {
        state.loading = true;
        state.error = null;
        try {
            // Host mode: server is local
            setServerUrl('');
            const { data } = await api.get('/network/info');
            state.hostInfo = data;
            state.connectionMode = 'host';
            state.connected = true;

            // Re-init Echo to point to local server
            const wsHost = data.primary_ip || window.location.hostname;
            initEcho(wsHost, data.ws_port || 8080);
        } catch (e) {
            state.error = 'Erro ao detectar rede. Verifique se o servidor está rodando.';
        } finally {
            state.loading = false;
        }
    }

    async function connectAsClient(hostIp, serverPort = 8000, wsPort = 8080) {
        state.loading = true;
        state.error = null;
        try {
            const serverUrl = `http://${hostIp}:${serverPort}`;
            setServerUrl(serverUrl);

            // Test connection
            const { data } = await api.get('/network/info');
            state.hostInfo = data;
            state.connectionMode = 'client';
            state.connected = true;

            // Re-init Echo to point to host
            initEcho(hostIp, wsPort);
        } catch (e) {
            setServerUrl('');
            state.error = `Não foi possível conectar a ${hostIp}:${serverPort}. Verifique o IP e se o host está ativo.`;
        } finally {
            state.loading = false;
        }
    }

    function disconnect() {
        cleanupChannels();
        stopPolling();
        clearSession();
        setServerUrl('');
        state.game = null;
        state.player = null;
        state.error = null;
        state.connected = false;
        state.connectionMode = null;
        state.hostInfo = null;
    }

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

        // Restore server URL if it was saved
        const savedServerUrl = localStorage.getItem('coup_server_url');
        if (savedServerUrl) {
            setServerUrl(savedServerUrl);
            state.connectionMode = 'client';
        } else {
            state.connectionMode = 'host';
        }
        state.connected = true;

        try {
            const { data } = await api.post('/games/reconnect', { token });
            state.game = data.game;
            state.player = data.player;

            // Fetch host info
            try {
                const { data: netData } = await api.get('/network/info');
                state.hostInfo = netData;
            } catch (_) {}

            listenToGame();
            return true;
        } catch {
            clearSession();
            setServerUrl('');
            state.connected = false;
            state.connectionMode = null;
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
        stopPolling();
        clearSession();
        state.game = null;
        state.player = null;
        state.error = null;
        // Keep connection alive — user can create/join another game
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

    const otherPlayers = computed(() => {
        if (!state.game?.players || !state.player) return [];
        return state.game.players.filter(p => p.id !== state.player.id);
    });

    const aliveOpponents = computed(() => {
        return otherPlayers.value.filter(p => p.is_alive);
    });

    const mustCoup = computed(() => {
        return state.player?.coins >= 10;
    });

    const isHostMode = computed(() => state.connectionMode === 'host');

    return {
        state,
        connectAsHost,
        connectAsClient,
        disconnect,
        createGame,
        joinGame,
        reconnect,
        startGame,
        refreshState,
        declareAction,
        pass,
        challengeAction,
        declareBlock,
        challengeBlock,
        loseInfluence,
        exchangeCards,
        leaveGame,
        abandonGame,
        isMyTurn,
        myInfluences,
        isHost,
        phase,
        otherPlayers,
        aliveOpponents,
        mustCoup,
        isHostMode,
    };
}
