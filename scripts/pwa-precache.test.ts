// @vitest-environment node
import { describe, expect, it } from 'vitest'
import {
  assertPwaPrecacheBudget,
  PWA_PRECACHE_MAX_BYTES,
  PWA_PRECACHE_MAX_ENTRIES,
  selectPwaPrecacheEntries,
  type SizedManifestEntry,
  type ViteBuildManifest,
} from './pwa-precache'

describe('PWA precache manifest', () => {
  const manifest: ViteBuildManifest = {
    'resources/js/app.ts': {
      file: 'assets/app-123.js',
      imports: ['_vendor-vue.js'],
      css: ['assets/app-123.css'],
    },
    '_vendor-vue.js': {
      file: 'assets/vendor-vue-123.js',
      css: ['assets/vendor-vue-123.css'],
    },
    'node_modules/workbox-window/build/workbox-window.prod.es5.mjs': {
      file: 'assets/workbox-window-123.js',
    },
    'Modules/User/resources/assets/js/auth/pages/Login.vue': {
      file: 'assets/login-123.js',
    },
  }

  it('selects the app shell and excludes lazy routes', () => {
    const entries: SizedManifestEntry[] = [
      '/build/assets/app-123.js',
      '/build/assets/app-123.css',
      '/build/assets/vendor-vue-123.js',
      '/build/assets/vendor-vue-123.css',
      '/build/assets/workbox-window-123.js',
      '/build/assets/login-123.js',
    ].map((url) => ({ url, revision: 'revision', size: 10 }))

    expect(selectPwaPrecacheEntries(entries, manifest).map(({ url }) => url)).toEqual([
      '/build/assets/app-123.js',
      '/build/assets/app-123.css',
      '/build/assets/vendor-vue-123.js',
      '/build/assets/vendor-vue-123.css',
      '/build/assets/workbox-window-123.js',
    ])
  })

  it('fails when a required shell asset is not available to Workbox', () => {
    expect(() => selectPwaPrecacheEntries([], manifest)).toThrow(
      'PWA shell assets are missing from the Workbox manifest',
    )
  })

  it('enforces both entry-count and byte budgets', () => {
    const entry = (size = 1): SizedManifestEntry => ({
      url: '/build/assets/app.js',
      revision: 'revision',
      size,
    })

    expect(() =>
      assertPwaPrecacheBudget(Array.from({ length: PWA_PRECACHE_MAX_ENTRIES }, () => entry())),
    ).not.toThrow()
    expect(() =>
      assertPwaPrecacheBudget(Array.from({ length: PWA_PRECACHE_MAX_ENTRIES + 1 }, () => entry())),
    ).toThrow('PWA precache contains')
    expect(() => assertPwaPrecacheBudget([entry(PWA_PRECACHE_MAX_BYTES + 1)])).toThrow(
      'PWA precache is',
    )
  })
})
