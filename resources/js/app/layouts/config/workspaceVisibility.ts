import type { SidebarItem } from '@/modules/core/contracts/SidebarItem.ts'
import type { AdminWorkspace } from './adminWorkspaces.ts'

type WorkspaceFeatures = Record<string, { enabled: boolean }>

export function isWorkspaceFeatureEnabled(
  workspace: AdminWorkspace,
  features?: WorkspaceFeatures,
): boolean {
  return !workspace.feature || !!features?.[workspace.feature]?.enabled
}

export function hasAccessibleWorkspaceDestination(
  items: SidebarItem[],
  isAccessible: (item: SidebarItem) => boolean,
): boolean {
  return !!findFirstAccessibleWorkspaceDestination(items, isAccessible)
}

export function findFirstAccessibleWorkspaceDestination(
  items: SidebarItem[],
  isAccessible: (item: SidebarItem) => boolean,
): SidebarItem | undefined {
  for (const item of items) {
    if (!isAccessible(item)) {
      continue
    }

    if (item.to || item.href) {
      return item
    }

    const childDestination = findFirstAccessibleWorkspaceDestination(
      item.children ?? [],
      isAccessible,
    )
    if (childDestination) {
      return childDestination
    }
  }

  return undefined
}
