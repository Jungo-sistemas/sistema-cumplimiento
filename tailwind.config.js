import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            // Color de marca Jungo Cumple. `brand` es el morado de botones, enlaces y elementos
            // activos; `brand-ink` el morado oscuro del encabezado y los títulos.
            colors: {
                brand: {
                    DEFAULT: '#5B1690',
                    dark: '#47106F',
                    50: '#F5EEFB',
                    100: '#EADCF7',
                    ink: '#21004B',
                },
            },
        },
    },

    plugins: [forms],
};
