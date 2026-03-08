import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY ?? 'armaani-key',
    wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
    wsPort:
        import.meta.env.VITE_REVERB_PORT ??
        (Number(window.location.port) ||
            (window.location.protocol === 'https:' ? 443 : 80)),
    wssPort:
        import.meta.env.VITE_REVERB_PORT ??
        (Number(window.location.port) || 443),
    forceTLS:
        (import.meta.env.VITE_REVERB_SCHEME ??
            window.location.protocol.replace(':', '')) === 'https',
    enabledTransports: ['ws', 'wss'],
});

export default echo;
