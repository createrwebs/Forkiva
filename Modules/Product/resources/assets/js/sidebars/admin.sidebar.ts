import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'

export const adminSidebar: SidebarList = {
  target: 'admin',
  items: [
    {
      key: 'admin.products.index',
      label: 'admin::sidebar.products',
      to: { name: 'admin.products.index' },
      icon: 'tabler-package',
      parentKey: 'admin.heading.menu_catalog',
      permission: 'admin.products.index',
      sort: 103,
    },
  ],
}
