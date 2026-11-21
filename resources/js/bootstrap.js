import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// ─── Laravel Echo + Reverb ─────────────────────────────────────────────────
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

try {
    const config = (typeof window !== 'undefined' && window.__REVERB_CONFIG__) ? window.__REVERB_CONFIG__ : {};
    const key = config.key || import.meta.env.VITE_REVERB_APP_KEY;
    const host = config.host || import.meta.env.VITE_REVERB_HOST || (typeof window !== 'undefined' ? window.location.hostname : 'localhost');
    const port = config.port || import.meta.env.VITE_REVERB_PORT || 8080;
    const scheme = config.scheme || import.meta.env.VITE_REVERB_SCHEME || (typeof window !== 'undefined' && window.location.protocol === 'https:' ? 'https' : 'http');
    const isHttps = scheme === 'https';

    if (key) {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: key,
            wsHost: host,
            wsPort: isHttps ? 443 : port,
            wssPort: isHttps ? port : 443,
            forceTLS: isHttps,
            enabledTransports: ['ws', 'wss'],
        });
    } else {
        console.warn('Reverb key not found. Realtime WebSocket listener skipped.');
    }
} catch (e) {
    console.warn('Could not initialize Laravel Echo:', e);
}
