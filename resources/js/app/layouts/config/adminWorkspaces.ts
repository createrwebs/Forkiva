import type { RouteLocationRaw } from 'vue-router'

export type AdminWorkspaceId =
  'restaurant' | 'settings' | 'reports' | 'hr' | 'delivery' | 'accounting'

export interface AdminWorkspace {
  id: AdminWorkspaceId
  sidebarWorkspace?: string
  label: string
  icon: string
  to: RouteLocationRaw
  feature?: 'accounting' | 'delivery' | 'hr'
}

export const adminWorkspaces: AdminWorkspace[] = [
  {
    id: 'restaurant',
    label: 'admin::sidebar.restaurant_hub',
    icon: 'tabler-tools-kitchen-2',
    to: { name: 'admin.dashboard' },
  },
  {
    id: 'delivery',
    sidebarWorkspace: 'delivery',
    label: 'delivery::sidebar.delivery',
    icon: 'tabler-truck-delivery',
    to: { name: 'admin.delivery_statistics_dashboard.index' },
    feature: 'delivery',
  },
  {
    id: 'hr',
    sidebarWorkspace: 'hr',
    label: 'hr::sidebar.human_resources',
    icon: 'tabler-users-group',
    to: { name: 'admin.hr_dashboard.index' },
    feature: 'hr',
  },
  {
    id: 'accounting',
    sidebarWorkspace: 'accounting',
    label: 'accounting::sidebar.accounting',
    icon: 'tabler-receipt-dollar',
    to: { name: 'admin.accounting.dashboard' },
    feature: 'accounting',
  },
  {
    id: 'reports',
    sidebarWorkspace: 'reports',
    label: 'admin::sidebar.reports',
    icon: 'tabler-chart-histogram',
    to: { name: 'admin.reports' },
  },
  {
    id: 'settings',
    sidebarWorkspace: 'settings',
    label: 'admin::sidebar.settings',
    icon: 'tabler-settings',
    to: { name: 'admin.settings.general' },
  },
]

const adminWorkspaceIds = new Set<AdminWorkspaceId>(adminWorkspaces.map(({ id }) => id))

export function isAdminWorkspaceId(workspace: unknown): workspace is AdminWorkspaceId {
  return adminWorkspaceIds.has(workspace as AdminWorkspaceId)
}

export function getAdminWorkspace(id: AdminWorkspaceId): AdminWorkspace {
  return adminWorkspaces.find((workspace) => workspace.id === id) ?? adminWorkspaces[0]!
}
