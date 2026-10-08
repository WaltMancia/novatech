import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    port: 5173,
    // En desarrollo, /api se reenvía a Nginx/PHP en Docker.
    proxy: { '/api': { target: 'http://localhost:8080', changeOrigin: false } },
  },
})
