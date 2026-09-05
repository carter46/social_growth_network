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
                display: ['"Plus Jakarta Sans"', 'Inter', ...defaultTheme.fontFamily.sans],
            },
            maxWidth: {
                'content-sm': '42rem',
                'content-md': '56rem',
                'content': '80rem',
                'content-lg': '96rem',
                'content-full': '100%',
                'form': '44rem',
                'auth': '28rem',
                'marketing': '71.875rem', // 1150px
            },
            spacing: {
                'section': '1.5rem',
            },
            colors: {
                // TaskPulse brand tokens (theme-aware via --th-* from dashboard-themes / :root)
                primary: 'rgb(var(--th-primary-rgb) / <alpha-value>)',
                accent: 'rgb(var(--th-accent-rgb) / <alpha-value>)',
                success: 'rgb(var(--th-success-rgb) / <alpha-value>)',
                warning: '#F59E0B',
                danger: 'rgb(var(--th-danger-rgb) / <alpha-value>)',
                'primary-hover': 'rgb(var(--th-primary-hover-rgb) / <alpha-value>)',
                // Semantic dashboard tokens
                surface: 'rgb(var(--th-surface-rgb) / <alpha-value>)',
                card: {
                    DEFAULT: 'var(--th-card)',
                    solid: 'rgb(var(--th-elevated-rgb) / <alpha-value>)',
                },
                muted: 'rgb(var(--th-muted-rgb) / <alpha-value>)',
                elevated: 'rgb(var(--th-elevated-rgb) / <alpha-value>)',
                sidebar: 'var(--th-sidebar)',
                header: 'var(--th-header)',
                overlay: 'var(--th-overlay)',
                'surface-secondary': 'var(--th-surface-secondary)',
                'text-primary': 'var(--th-text-primary)',
                'text-secondary': 'var(--th-text-secondary)',
                'text-muted': 'var(--th-text-muted)',
                'border-default': 'var(--th-border-default)',
                'border-subtle': 'var(--th-border-subtle)',
                // Marketing/auth TaskPulse navy aliases
                dark: '#0B1220',
                glass: 'rgba(21, 34, 56, 0.7)',
                'background-dark': '#0B1220',
                'background-light': '#F8F9FF',
                'card-dark': '#152238',
                'surface-dark': '#152238',
                'border-dark': '#2A3548',
            },
            backgroundImage: {
                'gradient-primary': 'linear-gradient(135deg, #004AC6 0%, #003EA8 100%)',
                'gradient-soft': 'var(--th-gradient-soft)',
            },
            boxShadow: {
                panel: 'var(--th-shadow)',
            },
            backdropBlur: {
                xs: '2px',
                panel: 'var(--th-blur)',
            },
        },
    },

    plugins: [forms],

    safelist: [
        'pb-16', 'pb-20', 'pb-24', 'pb-28', 'pb-32', 'pb-36', 'pb-40', 'pb-44',
        'sm:pb-20', 'sm:pb-24', 'sm:pb-28', 'sm:pb-32', 'sm:pb-36', 'sm:pb-44',
        'lg:pb-32', 'lg:pb-40', 'lg:pb-44',
        'py-14', 'py-16', 'sm:py-16', 'sm:py-20',
        'hidden', 'block', 'lg:block', 'lg:hidden', 'lg:flex', 'lg:grid',
        'grid-cols-12', 'col-span-3', 'col-span-8', 'col-span-9', 'col-span-4',
        'bg-header', 'bg-sidebar', 'bg-overlay', 'bg-surface-secondary',
    ],
};
