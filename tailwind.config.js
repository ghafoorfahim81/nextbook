import forms from '@tailwindcss/forms'
import defaultTheme from 'tailwindcss/defaultTheme'

/** @type {import('tailwindcss').Config} */
export default {
  darkMode: ['class', 'class'],
  content: [
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    './storage/framework/views/*.php',
    './resources/views/**/*.blade.php',
    './resources/js/**/*.{ts,tsx,vue}',
  ],

  theme: {
  	container: {
  		center: true,
  		padding: '2rem',
  		screens: {
  			'2xl': '1400px'
  		}
  	},
  	extend: {
  		colors: {
  			border: 'hsl(var(--border))',
  			input: 'hsl(var(--input))',
  			ring: 'hsl(var(--ring))',
  			background: 'hsl(var(--background))',
  			foreground: 'hsl(var(--foreground))',
  			primary: {
  				DEFAULT: 'hsl(var(--primary))',
  				foreground: 'hsl(var(--primary-foreground))'
  			},
  			secondary: {
  				DEFAULT: 'hsl(var(--secondary))',
  				foreground: 'hsl(var(--secondary-foreground))'
  			},
  			destructive: {
  				DEFAULT: 'hsl(var(--destructive))',
  				foreground: 'hsl(var(--destructive-foreground))'
  			},
  			muted: {
  				DEFAULT: 'hsl(var(--muted))',
  				foreground: 'hsl(var(--muted-foreground))'
  			},
  			accent: {
  				DEFAULT: 'hsl(var(--accent))',
  				foreground: 'hsl(var(--accent-foreground))'
  			},
  			popover: {
  				DEFAULT: 'hsl(var(--popover))',
  				foreground: 'hsl(var(--popover-foreground))'
  			},
  			card: {
  				DEFAULT: 'hsl(var(--card))',
  				foreground: 'hsl(var(--card-foreground))'
  			},
			sidebar: {
				DEFAULT: 'hsl(var(--sidebar-background))',
				foreground: 'hsl(var(--sidebar-foreground))',
				primary: 'hsl(var(--sidebar-primary))',
				'primary-foreground': 'hsl(var(--sidebar-primary-foreground))',
				accent: 'hsl(var(--sidebar-accent))',
				'accent-foreground': 'hsl(var(--sidebar-accent-foreground))',
				border: 'hsl(var(--sidebar-border))',
				ring: 'hsl(var(--sidebar-ring))'
			},
  						chart: {
				'1': 'hsl(var(--chart-1))',
				'2': 'hsl(var(--chart-2))',
				'3': 'hsl(var(--chart-3))',
				'4': 'hsl(var(--chart-4))',
				'5': 'hsl(var(--chart-5))'
			},
			// Custom colors matching the login design
  			nextbook: {
  				'purple': {
  					'50': '#f8f7ff',
  					'100': '#f0eeff',
  					'200': '#e6e2ff',
  					'300': '#d1c7ff',
  					'400': '#b3a3ff',
  					'500': '#8b5cf6', // Main purple from login button
  					'600': '#7c3aed',
  					'700': '#6d28d9',
  					'800': '#5b21b6',
  					'900': '#4c1d95',
  					'950': '#2e1065'
  				},
  				'lavender': {
  					'50': '#faf9ff',
  					'100': '#f3f1ff',
  					'200': '#e9e5ff',
  					'300': '#d8d0ff',
  					'400': '#c0b3ff',
  					'500': '#a78bfa',
  					'600': '#9333ea',
  					'700': '#7c3aed',
  					'800': '#6b21a8',
  					'900': '#581c87',
  					'950': '#3b0764'
  				},
  				'yellow': {
  					'50': '#fffbeb',
  					'100': '#fef3c7',
  					'200': '#fde68a',
  					'300': '#fcd34d',
  					'400': '#fbbf24',
  					'500': '#f59e0b', // Bright yellow from character jacket
  					'600': '#d97706',
  					'700': '#b45309',
  					'800': '#92400e',
  					'900': '#78350f',
  					'950': '#451a03'
  				},
  				'blue-gray': {
  					'50': '#f8fafc',
  					'100': '#f1f5f9',
  					'200': '#e2e8f0',
  					'300': '#cbd5e1',
  					'400': '#94a3b8',
  					'500': '#64748b',
  					'600': '#475569',
  					'700': '#334155',
  					'800': '#1e293b',
  					'900': '#0f172a',
  					'950': '#020617'
  				}
  			}
  		},
  		borderRadius: {
  			lg: 'var(--radius)',
  			md: 'calc(var(--radius) - 2px)',
  			sm: 'calc(var(--radius) - 4px)'
  		},
  		fontFamily: {
  			sans: ['Poppins', ...defaultTheme.fontFamily.sans],
  			mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
  		},
  		keyframes: {
  			'accordion-down': {
  				from: {
  					height: '0'
  				},
  				to: {
  					height: 'var(--reka-accordion-content-height)'
  				}
  			},
  			'accordion-up': {
  				from: {
  					height: 'var(--reka-accordion-content-height)'
  				},
  				to: {
  					height: '0'
  				}
  			}
  		},
  		animation: {
  			'accordion-down': 'accordion-down 0.2s ease-out',
  			'accordion-up': 'accordion-up 0.2s ease-out'
  		}
  	}
  },

  plugins: [forms, require('tailwindcss-animate')],
}
