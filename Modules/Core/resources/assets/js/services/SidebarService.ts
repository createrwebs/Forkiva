import type { SidebarItem } from '@/modules/core/contracts/SidebarItem'
import type { TargetName } from '@/modules/core/contracts/Target'

type NormalizedItem = Omit<SidebarItem, 'permission' | 'role'> & {
  permission: string[]
  role: string[]
}

class SidebarService {
  private static readonly instance = new SidebarService()
  private readonly lists = new Map<TargetName, Map<string, SidebarItem[]>>()

  private constructor() {}

  static getInstance(): SidebarService {
    return SidebarService.instance
  }

  register(target: TargetName, items: SidebarItem[], workspace?: string): void {
    const targetLists = this.lists.get(target) ?? new Map<string, SidebarItem[]>()
    const itemsByWorkspace = new Map<string, SidebarItem[]>()

    for (const item of items) {
      const workspaceKey = item.workspace ?? workspace ?? ''
      const workspaceItems = itemsByWorkspace.get(workspaceKey) ?? []
      workspaceItems.push(item)
      itemsByWorkspace.set(workspaceKey, workspaceItems)
    }

    for (const [workspaceKey, workspaceItems] of itemsByWorkspace) {
      const incomingKeys = this.collectKeys(workspaceItems)
      const existing = targetLists.get(workspaceKey) ?? []

      targetLists.set(workspaceKey, [
        ...existing.filter((item) => !incomingKeys.has(item.key)),
        ...workspaceItems.map((item) => this.cloneItem(item)),
      ])
    }

    this.lists.set(target, targetLists)
  }

  get(target: TargetName, workspace?: string): SidebarItem[] {
    return (
      this.lists
        .get(target)
        ?.get(workspace ?? '')
        ?.slice() ?? []
    )
  }

  build(target: TargetName, attachChildrenToHeadings = false, workspace?: string): SidebarItem[] {
    const normalize = (v?: string | string[]): string[] => (v ? (Array.isArray(v) ? v : [v]) : [])

    const source = this.get(target, workspace)

    const items: NormalizedItem[] = []
    const byKey = new Map<string, NormalizedItem>()
    const childrenByParent = new Map<string, NormalizedItem[]>()
    const roots: NormalizedItem[] = []

    for (const item of source) {
      if (item.disable) {
        continue
      }

      const normalized: NormalizedItem = {
        ...item,
        permission: normalize(item.permission),
        role: normalize(item.role),
        children: item.children ? [...item.children] : undefined,
      }

      items.push(normalized)
      byKey.set(normalized.key, normalized)

      if (normalized.parentKey) {
        const list = childrenByParent.get(normalized.parentKey) ?? []
        list.push(normalized)
        childrenByParent.set(normalized.parentKey, list)
      }
    }

    for (const item of items) {
      if (!item.parentKey) {
        roots.push(item)
        continue
      }

      const parent = byKey.get(item.parentKey)
      if (!parent) {
        roots.push(item)
        continue
      }

      if (parent.isHeading && !attachChildrenToHeadings) {
        roots.push(item)
        continue
      }

      parent.children ??= []
      parent.children.push(item)
    }

    const permMemo = new Map<string, string[]>()
    const roleMemo = new Map<string, string[]>()
    const visiting = new Set<string>()

    const dfs = (key: string): void => {
      if (permMemo.has(key)) {
        return
      }

      if (visiting.has(key)) {
        const node = byKey.get(key)
        permMemo.set(key, node?.permission ?? [])
        roleMemo.set(key, node?.role ?? [])
        return
      }

      visiting.add(key)

      const node = byKey.get(key)
      const perm = node?.permission.slice() ?? []
      const role = node?.role.slice() ?? []

      for (const child of childrenByParent.get(key) ?? []) {
        dfs(child.key)
        perm.push(...(permMemo.get(child.key) ?? []))
        role.push(...(roleMemo.get(child.key) ?? []))
      }

      permMemo.set(key, Array.from(new Set(perm)))
      roleMemo.set(key, Array.from(new Set(role)))
      visiting.delete(key)
    }

    for (const item of items) {
      dfs(item.key)
    }

    for (const item of items) {
      item.permission = permMemo.get(item.key) ?? item.permission
      item.role = roleMemo.get(item.key) ?? item.role
    }

    const sortTree = (list: SidebarItem[]) => {
      list.sort((a, b) => (a.sort ?? 0) - (b.sort ?? 0))
      for (const item of list) {
        if (item.children) {
          sortTree(item.children)
        }
      }
    }

    sortTree(roots)
    return roots
  }

  private collectKeys(items: SidebarItem[]): Set<string> {
    const keys = new Set<string>()

    const visit = (item: SidebarItem): void => {
      keys.add(item.key)
      item.children?.forEach(visit)
    }

    items.forEach(visit)

    return keys
  }

  private cloneItem(item: SidebarItem): SidebarItem {
    return {
      ...item,
      children: item.children?.map((child) => this.cloneItem(child)),
    }
  }
}

export const sidebarRegistry = SidebarService.getInstance()
