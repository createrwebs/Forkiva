// @vitest-environment node
import { mkdtempSync, mkdirSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
import {
  applicationRoot,
  assertModuleSources,
  frontendAliases,
  frontendManifestPaths,
} from './frontend-modules'

const discoveredManifests = import.meta.glob<string>(
  '/Modules/*/resources/assets/js/**/manifest.ts',
  { eager: true, query: '?raw', import: 'default' },
)

describe('unified module sources', () => {
  it('resolves feature aliases before the shared application alias', () => {
    expect(frontendAliases.find(({ find }) => find === '@/modules/auth')?.replacement).toContain(
      '/Modules/User/resources/assets/js/auth',
    )
    expect(
      frontendAliases.find(({ find }) => find === '@/modules/activity')?.replacement,
    ).toContain('/Modules/ActivityLog/resources/assets/js')
    expect(frontendAliases.at(-1)?.find).toBe('@')
    // Export is a shared API/component feature and intentionally has no route manifest.
    expect(frontendManifestPaths()).toHaveLength(37)
    expect(frontendManifestPaths()).toContain(
      `${applicationRoot}/Modules/DailyFinance/resources/assets/js/manifest.ts`,
    )
    expect(Object.keys(discoveredManifests).sort()).toEqual(
      frontendManifestPaths()
        .map((path) => path.slice(applicationRoot.length))
        .sort(),
    )
    expect(
      frontendManifestPaths().some((path) =>
        path.endsWith('/User/resources/assets/js/auth/manifest.ts'),
      ),
    ).toBe(true)
    expect(() => assertModuleSources()).not.toThrow()
  })

  it('allows absent disabled modules and rejects absent enabled modules', () => {
    const root = mkdtempSync(join(tmpdir(), 'forkiva-module-check-'))
    try {
      writeFileSync(join(root, 'modules_statuses.json'), JSON.stringify({ Hr: false }))
      expect(() => assertModuleSources(root)).not.toThrow()
      expect(frontendManifestPaths(root)).toEqual([])

      writeFileSync(join(root, 'modules_statuses.json'), JSON.stringify({ Hr: true }))
      expect(() => assertModuleSources(root)).toThrow('Modules/Hr/module.json')
      mkdirSync(join(root, 'Modules/Hr'), { recursive: true })
      writeFileSync(join(root, 'Modules/Hr/module.json'), '{}')
      writeFileSync(join(root, 'Modules/Hr/composer.json'), '{}')
      expect(() => assertModuleSources(root)).toThrow('Modules/Hr/resources/assets/js')
      mkdirSync(join(root, 'Modules/Hr/resources/assets/js'), { recursive: true })
      expect(() => assertModuleSources(root)).not.toThrow()
    } finally {
      rmSync(root, { recursive: true })
    }
  })
})
