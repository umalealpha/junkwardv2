/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        'brand-navy': '#1B2559',
        'ace-primary': '#7C3AED',
        'ace-dark': '#5B21B6',
      }
    }
  },
  plugins: [],
}
