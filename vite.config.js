import laravel from "laravel-vite-plugin";
import { defineConfig } from "vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/admin/admin-app.css",
                "resources/css/admin/admin-sidebar.css",
                "resources/css/admin/admin.css",
                "resources/js/admin.js",
                "resources/css/students/app.css",
                "resources/css/students/student-sidebar.css",
                "resources/css/students/students.css",
                "resources/js/app.js",
            ],
            refresh: true,
        }),
    ],
    server: {
        host: "192.168.254.100",
        port: 5173,
        strictPort: true,
        hmr: {
            host: "wispy-stunned-overrun.ngrok-free.dev",
            protocol: "wss",
            clientPort: 443,
        },
        allowedHosts: ["wispy-stunned-overrun.ngrok-free.dev"],
    },
});
