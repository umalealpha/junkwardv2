import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig(({ mode }) => ({
  plugins: [react()],
  server: {
    port: 3000,
  },
  build: {
    outDir: 'dist',
    sourcemap: true,
  },
  // In production builds, dead-code-eliminate dev-only console calls
  // (.log / .debug / .info) but keep .error / .warn so real error
  // signal still reaches the browser console. Also drop debugger
  // statements outright.
  //
  // UAT 2026-05-26: COO observed `Risk address lookups fetched: Object`
  // logging on /policies/create in the production bundle — a stray
  // dev console.log that shouldn't ship.
  esbuild: mode === 'production'
    ? {
        drop: ['debugger'],
        pure: ['console.log', 'console.debug', 'console.info'],
      }
    : {},
}))
