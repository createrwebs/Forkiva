import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'

export const adminSidebar: SidebarList = {
  target: 'admin',
  items: [
    {
      key: 'admin.database_tools.index',
      label: 'admin::sidebar.database',
      to: { name: 'admin.tools.database' },
      icon: 'tabler-database',
      parentKey: 'admin.heading.administration',
      permission: 'admin.database_tools.index',
      sort: 405,
    },
  ],
}
