/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        'claims-primary': '#DC2626',
        'claims-dark': '#991B1B',
        'claims-light': '#FEE2E2',
      }
    }
  },
  plugins: [],
}
