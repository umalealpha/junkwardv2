/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  // Class-based dark mode: a `.dark` on <html> flips the CSS-variable tokens
  // defined in src/index.css. No `.dark` present = light (today's behaviour),
  // so this is inert until the theme toggle ships.
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        // ── Canonical Alpha Direct brand ───────────────────────────────
        // Reconciled to the org-canonical values (was legacy #1B1464 / #F5841F).
        // Navy = structure/data/primary; orange = accent/CTA only (~5-10%).
        brand: {
          navy: '#010066',
          orange: '#FE7F0C',
          'navy-light': '#2D2490',
          'orange-light': '#FDBA74',
        },
        // ── Theme tokens (light/dark via CSS variables, see src/index.css) ──
        // Use as bg-surface / bg-app / border-line / text-ink etc. Alpha works
        // (rgb var + <alpha-value>), e.g. bg-surface/80.
        app:           'rgb(var(--app) / <alpha-value>)',
        surface:       'rgb(var(--surface) / <alpha-value>)',
        'surface-2':   'rgb(var(--surface-2) / <alpha-value>)',
        line:          'rgb(var(--border) / <alpha-value>)',
        ink:           'rgb(var(--text) / <alpha-value>)',
        'ink-muted':   'rgb(var(--text-muted) / <alpha-value>)',
        'ink-faint':   'rgb(var(--text-faint) / <alpha-value>)',
        // ── Semantic status (badges/alerts) — dark variants via CSS vars ──
        'status-success-bg': 'rgb(var(--success-bg) / <alpha-value>)',
        'status-success-fg': 'rgb(var(--success-fg) / <alpha-value>)',
        'status-warning-bg': 'rgb(var(--warning-bg) / <alpha-value>)',
        'status-warning-fg': 'rgb(var(--warning-fg) / <alpha-value>)',
        'status-danger-bg':  'rgb(var(--danger-bg) / <alpha-value>)',
        'status-danger-fg':  'rgb(var(--danger-fg) / <alpha-value>)',
        'status-info-bg':    'rgb(var(--info-bg) / <alpha-value>)',
        'status-info-fg':    'rgb(var(--info-fg) / <alpha-value>)',
        'status-accent-bg':  'rgb(var(--accent-bg) / <alpha-value>)',
        'status-accent-fg':  'rgb(var(--accent-fg) / <alpha-value>)',
        // ── Interactive brand (buttons/links) — themed via CSS vars so it
        // lightens on the dark canvas. Use as bg-primary / text-primary etc. ──
        primary:            'rgb(var(--primary) / <alpha-value>)',
        'primary-contrast': 'rgb(var(--primary-contrast) / <alpha-value>)',
      },
      boxShadow: {
        // ── Elevation system (see --elev-* in src/index.css) ──
        'elev-sm': 'var(--elev-sm)',
        'elev-md': 'var(--elev-md)',
        'elev-lg': 'var(--elev-lg)',
      },
      zIndex: {
        // ── Named stacking scale — one source of truth for the overlay layer.
        // modal(50) > overlay(40) so a modal always sits above its backdrop and
        // above transient dropdowns; toast(70) is highest. ──
        base:    '0',
        sticky:  '20',
        drawer:  '30',
        overlay: '40',
        modal:   '50',
        popover: '60',
        toast:   '70',
      },
      fontFamily: {
        // Body font = Inter; brand/heading font = Montserrat. Self-hosted via
        // @fontsource-variable (imported in src/main.tsx). Those packages
        // register the families 'Inter Variable' / 'Montserrat Variable', so
        // those names come FIRST; the plain 'Inter'/'Montserrat' names stay as
        // a fallback and the system stack after that. No font SIZES change.
        sans: ['Inter Variable', 'Inter', 'Arial', 'Helvetica', 'sans-serif'],
        heading: ['Montserrat Variable', 'Montserrat', 'Arial', 'Helvetica', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
