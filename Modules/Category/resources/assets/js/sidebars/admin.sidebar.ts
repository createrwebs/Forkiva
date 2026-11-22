import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'

export const adminSidebar: SidebarList = {
  target: 'admin',
  items: [
    {
      key: 'admin.categories.index',
      label: 'admin::sidebar.categories',
      to: { name: 'admin.categories.index' },
      icon: 'tabler-category',
      parentKey: 'admin.heading.menu_catalog',
      permission: 'admin.categories.index',
      sort: 102,
    },
  ],
}
