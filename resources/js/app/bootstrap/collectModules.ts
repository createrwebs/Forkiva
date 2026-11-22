import type { ModuleManifest } from '@/modules/core/contracts/ModuleManifest.ts'
import moduleStatuses from '../../../../modules_statuses.json'

const modules = import.meta.glob<{
  default: ModuleManifest
}>('/Modules/*/resources/assets/js/**/manifest.ts', { eager: true })

export const collectedModules = Object.entries(modules)
  .filter(([path]) => (moduleStatuses as Record<string, boolean>)[path.split('/')[2]!])
  .map(([, module]) => module.default)
