import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'

export const adminSidebar: SidebarList = {
  target: 'admin',
  items: [
    {
      key: 'admin.invoices.index',
      label: 'admin::sidebar.invoices',
      to: { name: 'admin.invoices.index' },
      permission: 'admin.invoices.index',
      parentKey: 'admin.sales',
      sort: 2,
    },
  ],
}
