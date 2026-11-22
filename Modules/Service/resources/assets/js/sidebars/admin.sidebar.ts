import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'

export const adminSidebar: SidebarList = {
  target: 'admin',
  workspace: 'settings',
  items: [
    {
      key: 'admin.services.index',
      label: 'admin::sidebar.services_integrations',
      to: { name: 'admin.services.index' },
      icon: 'tabler-plug-connected',
      permission: 'admin.services.index',
      sort: 500,
      parentKey: 'admin.heading.settings',
    },
  ],
}
