import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'

export const adminSidebar: SidebarList = {
  target: 'admin',
  items: [
    {
      key: 'admin.discounts.index',
      label: 'admin::sidebar.discounts',
      to: { name: 'admin.discounts.index' },
      permission: 'admin.discounts.index',
      parentKey: 'admin.promotions',
      sort: 1,
    },
  ],
}
