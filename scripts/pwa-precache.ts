import { createHash } from 'node:crypto'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { applicationRoot } from './frontend-modules'

export const PWA_PRECACHE_MAX_ENTRIES = 24
export const PWA_PRECACHE_MAX_BYTES = 3 * 1024 * 1024

const basePath = (process.env.VITE_BASE_PATH || '/').replace(/\/?$/, '/')
const BUILD_URL_PREFIX = `${basePath}build/`
const PWA_ENTRY_SOURCES = [
  'resources/js/app.ts',
  'node_modules/workbox-window/build/workbox-window.prod.es5.mjs',
]
const PWA_SHELL_FILES = ['/loader.css', '/logo.png', '/favicon.ico']

export interface ViteBuildManifestEntry {
  file: string
  imports?: string[]
  css?: string[]
}

export type ViteBuildManifest = Record<string, ViteBuildManifestEntry>

export interface SizedManifestEntry {
  url: string
  revision: string | null
  size: number
}

function collectStaticEntryFiles(
  manifest: ViteBuildManifest,
  entrySources: string[] = PWA_ENTRY_SOURCES,
): Set<string> {
  const visited = new Set<string>()
  const files = new Set<string>()

  const visit = (source: string) => {
    if (visited.has(source)) return

    const entry = manifest[source]
    if (!entry) {
      throw new Error(`PWA entry is missing from the Vite manifest: ${source}`)
    }

    visited.add(source)
    files.add(entry.file)
    entry.css?.forEach((file) => files.add(file))
    entry.imports?.forEach(visit)
  }

  entrySources.forEach(visit)

  return files
}

export function selectPwaPrecacheEntries(
  entries: SizedManifestEntry[],
  manifest: ViteBuildManifest,
  entrySources: string[] = PWA_ENTRY_SOURCES,
): SizedManifestEntry[] {
  const expectedUrls = new Set(
    [...collectStaticEntryFiles(manifest, entrySources)].map(
      (file) => `${BUILD_URL_PREFIX}${file}`,
    ),
  )
  const selectedEntries = entries.filter((entry) => expectedUrls.has(entry.url))
  const selectedUrls = new Set(selectedEntries.map((entry) => entry.url))
  const missingUrls = [...expectedUrls].filter((url) => !selectedUrls.has(url))

  if (missingUrls.length > 0) {
    throw new Error(
      `PWA shell assets are missing from the Workbox manifest: ${missingUrls.join(', ')}`,
    )
  }

  return selectedEntries
}

export function assertPwaPrecacheBudget(entries: SizedManifestEntry[]): void {
  const totalBytes = entries.reduce((total, entry) => total + entry.size, 0)

  if (entries.length > PWA_PRECACHE_MAX_ENTRIES) {
    throw new Error(
      `PWA precache contains ${entries.length} entries; budget is ${PWA_PRECACHE_MAX_ENTRIES}`,
    )
  }

  if (totalBytes > PWA_PRECACHE_MAX_BYTES) {
    throw new Error(
      `PWA precache is ${totalBytes} bytes; budget is ${PWA_PRECACHE_MAX_BYTES} bytes`,
    )
  }
}

export async function createPwaPrecacheManifest(
  entries: SizedManifestEntry[],
): Promise<{ manifest: SizedManifestEntry[]; warnings: string[] }> {
  const buildManifest = JSON.parse(
    readFileSync(resolve(applicationRoot, 'public/build/manifest.json'), 'utf8'),
  ) as ViteBuildManifest
  const shellEntries = selectPwaPrecacheEntries(entries, buildManifest)
  const shellRevision = createHash('sha256')
    .update(JSON.stringify(shellEntries))
    .update(readFileSync(resolve(applicationRoot, 'resources/views/app.blade.php')))
    .digest('hex')
  const publicEntries = PWA_SHELL_FILES.map((file): SizedManifestEntry => {
    const contents = readFileSync(resolve(applicationRoot, `public${file}`))

    return {
      url: `${basePath}${file.slice(1)}`,
      size: contents.length,
      revision: createHash('sha256').update(contents).digest('hex'),
    }
  })
  const manifest = [
    ...shellEntries,
    { url: `${basePath}offline`, revision: shellRevision, size: 0 },
    ...publicEntries,
  ]

  assertPwaPrecacheBudget(manifest)

  return { manifest, warnings: [] }
}
