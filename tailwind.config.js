import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                ink: {
                    DEFAULT: '#1C2140',
                    light: '#2E3566',
                    dark: '#111428',
                },
                meskel: {
                    DEFAULT: '#F4B400',
                    light: '#FFCA28',
                    dark: '#D99F00',
                },
                highland: {
                    DEFAULT: '#1E5B47',
                    light: '#28795E',
                    dark: '#133D30',
                },
                orchid: {
                    DEFAULT: '#8E3B6E',
                    light: '#AC4D88',
                    dark: '#6E2C55',
                },
                paper: {
                    DEFAULT: '#F6F5FA',
                    subtle: '#EEEBF6',
                    pure: '#FFFFFF',
                },
                mist: {
                    DEFAULT: '#E4E3EE',
                    border: '#D5D3E4',
                    light: '#F0EFF8',
                },
            },
            fontFamily: {
                serif: ['"Young Serif"', '"Noto Serif Ethiopic"', 'serif'],
                sans: ['"Instrument Sans"', '"Noto Sans Ethiopic"', ...defaultTheme.fontFamily.sans],
                'ethiopic-serif': ['"Noto Serif Ethiopic"', 'serif'],
                'ethiopic-sans': ['"Noto Sans Ethiopic"', 'sans-serif'],
            },
            borderRadius: {
                sm: '4px',
                DEFAULT: '8px',
                md: '12px',
                lg: '16px',
                xl: '24px',
                pill: '9999px',
            },
            spacing: {
                '18': '4.5rem',
                '22': '5.5rem',
                '26': '6.5rem',
                '30': '7.5rem',
            },
            maxWidth: {
                prose: '68ch',
            },
        },
    },

    plugins: [forms],
};
