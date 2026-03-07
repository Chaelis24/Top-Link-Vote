import laravel from "laravel-vite-plugin";
import { defineConfig } from "vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/css/auth.css",
                "resources/css/admin.css",
                "resources/css/students.css",
                "resources/js/app.js",
            ],
            refresh: true,
        }),
    ],
});
