import vue from '@vitejs/plugin-vue'
import { defineConfig, type UserConfig } from 'vitest/config'
import { frontendAliases } from './scripts/frontend-modules'

export default defineConfig({
  plugins: [
    // Vitest bundles a second Vite type version; runtime plugins are compatible.
    vue() as unknown as NonNullable<UserConfig['plugins']>[number],
    {
      name: 'resolve-pwa-register-for-tests',
      resolveId(id) {
        if (id === 'virtual:pwa-register') return id
      },
    },
  ],
  resolve: { alias: frontendAliases },
  test: {
    environment: 'jsdom',
    include: [
      'resources/js/**/*.test.ts',
      'Modules/*/resources/assets/js/**/*.test.ts',
      'scripts/**/*.test.ts',
    ],
    pool: 'threads',
    poolOptions: { threads: { singleThread: true } },
  },
})
