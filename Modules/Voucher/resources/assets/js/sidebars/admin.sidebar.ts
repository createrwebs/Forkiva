import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'

export const adminSidebar: SidebarList = {
  target: 'admin',
  items: [
    {
      key: 'admin.vouchers.index',
      label: 'admin::sidebar.vouchers',
      to: { name: 'admin.vouchers.index' },
      permission: 'admin.vouchers.index',
      parentKey: 'admin.promotions',
      sort: 2,
    },
  ],
}
