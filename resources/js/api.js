import axios from 'axios';

let serverUrl = '';

const api = axios.create({
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// Inject player token and dynamic base URL on every request
api.interceptors.request.use((config) => {
    config.baseURL = serverUrl ? `${serverUrl}/api` : '/api';

    const token = localStorage.getItem('coup_token');
    if (token) {
        config.headers['X-Player-Token'] = token;
    }
    return config;
});

/**
 * Set the remote server URL for client mode.
 * @param {string} url - e.g. "http://192.168.43.1:8000"
 */
export function setServerUrl(url) {
    serverUrl = url ? url.replace(/\/+$/, '') : '';
}

/**
 * Get the current server URL.
 */
export function getServerUrl() {
    return serverUrl;
}

export default api;
