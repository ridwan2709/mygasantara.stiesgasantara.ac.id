import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: "class",
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ["Inter", "Figtree", ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    DEFAULT: "rgb(var(--primary-rgb) / <alpha-value>)",
                    hover: "rgb(var(--primary-hover-rgb) / <alpha-value>)",
                    light: "rgb(var(--primary-light-rgb) / <alpha-value>)",
                    text: "var(--primary-text)",
                },
                siakad: {
                    dark: "rgb(var(--primary-hover-rgb) / <alpha-value>)",
                    primary: "rgb(var(--primary-rgb) / <alpha-value>)",
                    secondary: "#456882",
                    light: "rgb(var(--primary-light-rgb) / <alpha-value>)",
                    50: "rgb(var(--primary-light-rgb) / <alpha-value>)",
                    100: "var(--primary-100)",
                    200: "var(--primary-200)",
                    300: "var(--primary-300)",
                    400: "var(--primary-400)",
                    500: "rgb(var(--primary-rgb) / <alpha-value>)",
                    600: "rgb(var(--primary-hover-rgb) / <alpha-value>)",
                    700: "var(--primary-700)",
                    800: "rgb(var(--primary-hover-rgb) / <alpha-value>)",
                    900: "var(--primary-900)",
                },
            },
            boxShadow: {
                saas: "0 1px 3px 0 rgb(0 0 0 / 0.05), 0 1px 2px -1px rgb(0 0 0 / 0.05)",
                "saas-md":
                    "0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05)",
                "saas-lg":
                    "0 10px 15px -3px rgb(0 0 0 / 0.05), 0 4px 6px -4px rgb(0 0 0 / 0.05)",
                card: "0 0 0 1px rgb(0 0 0 / 0.03), 0 2px 4px rgb(0 0 0 / 0.05)",
            },
            borderRadius: {
                saas: "0.625rem",
            },
            animation: {
                "fade-in": "fadeIn 0.2s ease-out",
                "slide-in": "slideIn 0.2s ease-out",
            },
            keyframes: {
                fadeIn: {
                    "0%": { opacity: "0" },
                    "100%": { opacity: "1" },
                },
                slideIn: {
                    "0%": { opacity: "0", transform: "translateY(-4px)" },
                    "100%": { opacity: "1", transform: "translateY(0)" },
                },
            },
        },
    },

    plugins: [forms],
};
