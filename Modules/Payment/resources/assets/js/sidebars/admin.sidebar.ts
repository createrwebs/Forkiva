import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'

export const adminSidebar: SidebarList = {
  target: 'admin',
  items: [
    {
      key: 'admin.payments.index',
      label: 'admin::sidebar.payments',
      to: { name: 'admin.payments.index' },
      permission: 'admin.payments.index',
      parentKey: 'admin.sales',
      sort: 3,
    },
  ],
}
