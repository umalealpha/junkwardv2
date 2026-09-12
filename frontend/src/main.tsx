import React from 'react'
import ReactDOM from 'react-dom/client'
// Self-hosted webfonts (replaces the Google Fonts CDN <link> in index.html).
// Variable packages: one woff2 per subset, all weights, font-display: swap
// baked in. They register the families 'Inter Variable' / 'Montserrat Variable'
// (see tailwind.config.js fontFamily — those names are listed first).
import '@fontsource-variable/inter'
import '@fontsource-variable/montserrat'
import App from './App'
import './index.css'

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
)
