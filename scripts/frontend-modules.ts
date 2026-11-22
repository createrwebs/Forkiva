import { existsSync, readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

export const applicationRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..')

interface TypeScriptPaths {
  compilerOptions: { paths: Record<string, string[]> }
}

const paths = JSON.parse(
  readFileSync(resolve(applicationRoot, 'tsconfig.paths.json'), 'utf8'),
) as TypeScriptPaths

// Keep bundler, test-runner and TypeScript resolution on the same explicit map.
export const frontendAliases = Object.entries(paths.compilerOptions.paths)
  .filter(([name]) => name.startsWith('@'))
  .sort(([a], [b]) => b.length - a.length)
  .map(([name, targets]) => ({
    find: name.replace(/\/\*$/, ''),
    replacement: resolve(applicationRoot, targets[0]!.replace(/\/\*$/, '')),
  }))

export function assertModuleSources(root = applicationRoot): void {
  const statuses = JSON.parse(
    readFileSync(resolve(root, 'modules_statuses.json'), 'utf8'),
  ) as Record<string, boolean>
  const missing: string[] = []

  for (const [name, enabled] of Object.entries(statuses)) {
    if (!enabled) continue

    for (const file of ['module.json', 'composer.json']) {
      const relative = `Modules/${name}/${file}`
      if (!existsSync(resolve(root, relative))) missing.push(relative)
    }
  }

  for (const [name, targets] of Object.entries(paths.compilerOptions.paths)) {
    if (!name.startsWith('@/modules/')) continue
    const relative = targets[0]!.replace(/^\.\//, '').replace(/\/\*$/, '')
    const owner = relative.split('/')[1]!
    if (statuses[owner] && !existsSync(resolve(root, relative))) missing.push(relative)
  }

  if (missing.length) {
    throw new Error(
      `Enabled module sources are missing. Restore the module repositories before building:\n${missing.join('\n')}`,
    )
  }
}

export function frontendManifestPaths(root = applicationRoot): string[] {
  const statuses = JSON.parse(
    readFileSync(resolve(root, 'modules_statuses.json'), 'utf8'),
  ) as Record<string, boolean>

  return Object.entries(paths.compilerOptions.paths)
    .filter(([name, targets]) => {
      if (!name.startsWith('@/modules/')) return false
      const owner = targets[0]!.replace(/^\.\//, '').split('/')[1]!
      return statuses[owner]
    })
    .map(([, targets]) =>
      resolve(root, targets[0]!.replace(/^\.\//, '').replace(/\/\*$/, ''), 'manifest.ts'),
    )
    .filter(existsSync)
}
