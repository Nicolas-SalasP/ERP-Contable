import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  plugins: [
    react(),
    VitePWA({
      // 'prompt' evita recargas silenciosas del SW que descartarian formularios activos.
      registerType: 'prompt',
      includeAssets: ['favicon.ico', 'apple-touch-icon.png', 'masked-icon.svg'],
      workbox: {
        // Sin esto, NavigationRoute de Workbox intercepta TODA navegacion
        // (default allowlist: [/./], sin denylist) -- incluye abrir un PDF
        // o un manual HTML en pestaña nueva (target="_blank" es una
        // navegacion de documento, no un fetch). El SW le servia index.html
        // en vez del archivo real, la SPA no matcheaba esa ruta y terminaba
        // en login. Excluye /manuales/ (PDFs + HTML) del fallback.
        navigateFallbackDenylist: [/^\/manuales\//],
      },
      manifest: {
        name: 'Sistema de Gestión Contable',
        short_name: 'ContaSys', 
        description: 'Gestión de facturas y contabilidad',
        theme_color: '#ffffff',
        background_color: '#ffffff',
        display: 'standalone',
        start_url: '/',
        icons: [
          {
            src: 'pwa-192x192.png',
            sizes: '192x192',
            type: 'image/png'
          },
          {
            src: 'pwa-512x512.png', 
            sizes: '512x512',
            type: 'image/png'
          }
        ]
      }
    })
  ],
  server: {
    port: 3000,
    proxy: {
      '/api': {
        target: 'http://localhost:8001',
        changeOrigin: true,
        secure: false,
      },
      '/storage': {
        target: 'http://localhost:8001',
        changeOrigin: true,
        secure: false,
      }
    }
  }
})