import axios from 'axios';

const api = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// Inject player token on every request
api.interceptors.request.use((config) => {
    const token = localStorage.getItem('coup_token');
    if (token) {
        config.headers['X-Player-Token'] = token;
    }
    return config;
});

export default api;
