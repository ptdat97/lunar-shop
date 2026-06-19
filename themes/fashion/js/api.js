// Single axios instance for the storefront. Talks to /api/v1; same-domain so
// the Sanctum session cookie + CSRF cookie ride along automatically.
import axios from 'axios';

const api = axios.create({
    baseURL: '/api/v1',
    withCredentials: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

export default api;
