import forms from "@tailwindcss/forms";

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ["Inter", "ui-sans-serif", "system-ui", "sans-serif"],
            },
        },
        screens: {
            xs: "480px",
            md: "768px",
            lg: "1024px",
            xl: "1280px",
        },
    },

    plugins: [forms],
};
