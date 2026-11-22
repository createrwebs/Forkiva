import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'

export const adminSidebar: SidebarList = {
  target: 'admin',
  items: [
    {
      key: 'admin.orders.index',
      label: 'admin::sidebar.orders',
      to: { name: 'admin.orders.index' },
      permission: 'admin.orders.index',
      parentKey: 'admin.sales',
      sort: 1,
    },
    {
      key: 'admin.reasons.index',
      label: 'admin::sidebar.reasons',
      to: { name: 'admin.reasons.index' },
      permission: 'admin.reasons.index',
      parentKey: 'admin.sales',
      sort: 4,
    },
  ],
}
