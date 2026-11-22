import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'

export const adminSidebar: SidebarList = {
  target: 'admin',
  items: [
    {
      key: 'admin.sales',
      label: 'admin::sidebar.orders',
      icon: 'tabler-basket-dollar',
      permission: [],
      parentKey: 'admin.heading.operations',
      sort: 2,
      children: [],
    },
    {
      key: 'admin.promotions',
      label: 'admin::sidebar.promotions',
      icon: 'tabler-target-arrow',
      parentKey: 'admin.heading.customers_growth',
      permission: [],
      sort: 302,
      children: [],
    },
  ],
}
