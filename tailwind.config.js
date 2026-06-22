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
            keyframes: {
                float: {
                    "0%, 100%": { transform: "translateY(0) scale(1)" },
                    "50%": { transform: "translateY(-20px) scale(1.05)" },
                },
            },
            animation: {
                float: "float 6s ease-in-out infinite",
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
