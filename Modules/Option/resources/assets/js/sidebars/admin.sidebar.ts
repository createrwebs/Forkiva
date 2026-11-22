import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'

export const adminSidebar: SidebarList = {
  target: 'admin',
  items: [
    {
      key: 'admin.options.index',
      label: 'admin::sidebar.options',
      to: { name: 'admin.options.index' },
      icon: 'tabler-adjustments',
      parentKey: 'admin.heading.menu_catalog',
      permission: 'admin.options.index',
      sort: 104,
    },
  ],
}
