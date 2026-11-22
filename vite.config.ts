import { defineConfig } from 'vite'

import { fileURLToPath, URL } from 'node:url'
import laravel from 'laravel-vite-plugin'
import { assertModuleSources, frontendAliases } from './scripts/frontend-modules'
import { createPwaPrecacheManifest, PWA_PRECACHE_MAX_BYTES } from './scripts/pwa-precache'
import vue from '@vitejs/plugin-vue'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { VueRouterAutoImports } from 'unplugin-vue-router'
import vueRouter from 'unplugin-vue-router/vite'
import { VitePWA } from 'vite-plugin-pwa'
import vueDevTools from 'vite-plugin-vue-devtools'
import Vuetify, { transformAssetUrls } from 'vite-plugin-vuetify'
import svgLoader from 'vite-svg-loader'

export default defineConfig(({ command }) => {
  assertModuleSources()
  const basePath = (process.env.VITE_BASE_PATH || '/').replace(/\/?$/, '/')

  return {
    base: command === 'build' ? `${basePath}build/` : undefined,
    plugins: [
      laravel({
        input: ['resources/js/app.ts', 'Modules/Installer/resources/assets/js/main.ts'],
        refresh: ['resources/views/**/*.blade.php', 'Modules/*/routes/**/*.php'],
      }),
      vue({
        template: {
          transformAssetUrls: {
            base: null,
            includeAbsolute: false,
            tags: transformAssetUrls,
          },
        },
      }),
      vueRouter({
        routesFolder: [],
        dts: 'resources/js/typed-router.d.ts',
        watch: command === 'serve',
      }),
      Components({
        dirs: [
          'Modules/Core/resources/assets/js/components',
          'Modules/Core/resources/assets/js/Partials',
        ],
        // dts: true,
        dts: 'resources/js/components.d.ts',
        resolvers: [
          (componentName) => {
            if (componentName === 'VueApexCharts') {
              return {
                name: 'default',
                from: 'vue3-apexcharts',
                as: 'VueApexCharts',
              }
            }
          },
        ],
      }),
      ...(command === 'serve' ? [vueDevTools()] : []),
      AutoImport({
        imports: [
          'vue',
          VueRouterAutoImports,
          {
            pinia: ['defineStore', 'storeToRefs'],
          },
          '@vueuse/core',
          '@vueuse/math',
        ],
        dts: 'resources/js/auto-imports.d.ts',
        eslintrc: {
          enabled: true,
        },
        vueTemplate: true,
        ignore: ['useCookies', 'useStorage'],
      }),
      svgLoader(),
      VitePWA({
        registerType: 'autoUpdate',
        injectRegister: false,
        manifest: false,
        base: basePath,
        buildBase: basePath,
        scope: basePath,
        outDir: 'public/build',
        includeAssets: [],
        workbox: {
          cleanupOutdatedCaches: true,
          inlineWorkboxRuntime: true,
          modifyURLPrefix: { '': `${basePath}build/` },
          navigateFallback: `${basePath}offline`,
          navigateFallbackAllowlist: [
            new RegExp(`^${basePath === '/' ? '\\/' : basePath}(?:$|admin(?:\\/|$)|auth(?:\\/|$)|employee(?:\\/|$)|driver(?:\\/|$)|online-menu(?:\\/|$)|table-order(?:\\/|$))`),
          ],
          manifestTransforms: [createPwaPrecacheManifest],
          runtimeCaching: [
            {
              urlPattern: ({ request, sameOrigin, url }) =>
                sameOrigin &&
                url.pathname.startsWith(`${basePath}build/assets/`) &&
                ['script', 'style', 'worker'].includes(request.destination),
              handler: 'CacheFirst',
              options: {
                cacheName: 'forkiva-code-v1',
                cacheableResponse: { statuses: [200] },
                expiration: {
                  maxEntries: 180,
                  maxAgeSeconds: 30 * 24 * 60 * 60,
                  purgeOnQuotaError: true,
                },
              },
            },
            {
              urlPattern: ({ request, sameOrigin, url }) =>
                sameOrigin &&
                url.pathname.startsWith(`${basePath}build/assets/`) &&
                request.destination === 'image',
              handler: 'CacheFirst',
              options: {
                cacheName: 'forkiva-images-v1',
                cacheableResponse: { statuses: [200] },
                expiration: {
                  maxEntries: 60,
                  maxAgeSeconds: 30 * 24 * 60 * 60,
                  purgeOnQuotaError: true,
                },
              },
            },
            {
              urlPattern: ({ request, sameOrigin, url }) =>
                sameOrigin &&
                url.pathname.startsWith(`${basePath}build/assets/`) &&
                request.destination === 'font',
              handler: 'CacheFirst',
              options: {
                cacheName: 'forkiva-fonts-v1',
                cacheableResponse: { statuses: [200] },
                expiration: {
                  maxEntries: 24,
                  maxAgeSeconds: 365 * 24 * 60 * 60,
                  purgeOnQuotaError: true,
                },
              },
            },
          ],
          clientsClaim: true,
          globPatterns: ['**/*.{js,css}'],
          maximumFileSizeToCacheInBytes: PWA_PRECACHE_MAX_BYTES,
        },
        devOptions: {
          enabled: false,
          suppressWarnings: true,
        },
      }),
      Vuetify({
        autoImport: true,
        styles: {
          configFile: 'resources/js/assets/scss/styles/variables/_vuetify.scss',
        },
      }),
    ],
    optimizeDeps: {
      exclude: [
        'vuetify',
        'vue-router',
        'unplugin-vue-router/runtime',
        'unplugin-vue-router/data-loaders',
        'unplugin-vue-router/data-loaders/basic',
      ],
    },
    define: { 'process.env': {} },
    resolve: {
      alias: [
        ...frontendAliases,
        {
          find: '@configured-variables',
          replacement: fileURLToPath(
            new URL('resources/js/assets/scss/styles/variables/_template.scss', import.meta.url),
          ),
        },
      ],
    },
    server: {
      port: 3101,
      strictPort: true,
      warmup: {
        clientFiles: [
          'resources/js/app.ts',
          'resources/js/app/bootstrap/collectModules.ts',
          'resources/js/app/layouts/**/*.vue',
        ],
      },
    },
    build: {
      chunkSizeWarningLimit: 1000,
      rollupOptions: {
        output: {
          manualChunks(id) {
            if (!id.includes('node_modules')) {
              return
            }

            if (id.includes('vuetify')) {
              return 'vendor-vuetify'
            }

            if (id.includes('leaflet')) {
              return 'vendor-leaflet'
            }

            if (id.includes('dropzone')) {
              return 'vendor-dropzone'
            }

            if (id.includes('@vueup/vue-quill') || id.includes('/quill/')) {
              return 'vendor-quill'
            }

            if (
              id.includes('/chart.js/') ||
              id.includes('/@kurkle/color/') ||
              id.includes('apexcharts') ||
              id.includes('vue3-apexcharts')
            ) {
              return 'vendor-charts'
            }

            if (id.includes('tiptap') || id.includes('prosemirror')) {
              return 'vendor-editor'
            }

            if (id.includes('vue-router')) {
              return 'vendor-router'
            }

            if (id.includes('pinia')) {
              return 'vendor-store'
            }

            if (id.includes('axios')) {
              return 'vendor-http'
            }

            if (id.includes('vue-i18n')) {
              return 'vendor-i18n'
            }

            if (id.includes('@vueuse')) {
              return 'vendor-utils'
            }

            if (id.includes('lodash')) {
              return 'vendor-lodash'
            }

            if (id.includes('xlsx')) {
              return 'vendor-xlsx'
            }

            if (id.includes('sweetalert2')) {
              return 'vendor-swal'
            }

            // Keep other dependencies with the routes that actually use them.
            return undefined
          },
        },
      },
    },
  }
})
