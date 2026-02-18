import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Dynamically resolve WebSocket host/port from the current browser URL.
// This means NO hardcoded hostname — works on localhost, LAN, EC2, any domain
// without rebuilding the image.
const wsHost   = window.location.hostname;
const isTLS    = window.location.protocol === 'https:';
const wsPort   = window.location.port
    ? parseInt(window.location.port)
    : (isTLS ? 443 : 80);

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost,
    wsPort,
    wssPort: wsPort,
    forceTLS: isTLS,
    disableStats: true,
    enabledTransports: ['ws', 'wss'],  // both; forceTLS controls which is used
});
