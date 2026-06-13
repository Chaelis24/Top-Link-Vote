/**
 * Echo / WebSocket configuration for real-time events.
 *
 * Initialises Laravel Echo with the Reverb driver (Pusher
 * protocol) for broadcasting live vote tallies and audit
 * trail updates to connected clients.
 */
import Echo from "laravel-echo";

import Pusher from "pusher-js";
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: "reverb",
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? "https") === "https",
    enabledTransports: ["ws", "wss"],

    authEndpoint: "/broadcasting/auth",
    auth: {
        headers: {
            "ngrok-skip-browser-warning": "true",
            "X-Requested-With": "XMLHttpRequest",
            "X-CSRF-TOKEN": document
                .querySelector('meta[name="csrf-token"]')
                .getAttribute("content"),
        },
    },
});
