import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

/**
 * Initialize (or re-initialize) the Echo WebSocket connection.
 * Called when the user connects to a host.
 *
 * @param {string} wsHost - WebSocket host (IP or hostname)
 * @param {number} wsPort - WebSocket port (default 8080)
 */
export function initEcho(wsHost, wsPort = 8080) {
    // Disconnect previous connection if any
    if (window.Echo) {
        try { window.Echo.disconnect(); } catch (_) {}
    }

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY || 'hdg3y2tj7k10krzct9ec',
        wsHost: wsHost,
        wsPort: wsPort,
        wssPort: 443,
        forceTLS: false,
        enabledTransports: ['ws', 'wss'],
    });

    return window.Echo;
}

// Initialize with default config (will be re-initialized on connect)
const defaultHost = import.meta.env.VITE_REVERB_HOST || window.location.hostname;
const defaultPort = import.meta.env.VITE_REVERB_PORT || 8080;
initEcho(defaultHost, defaultPort);
