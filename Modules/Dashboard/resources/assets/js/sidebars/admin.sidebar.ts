import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'

export const adminSidebar: SidebarList = {
  target: 'admin',
  items: [
    {
      key: 'admin.dashboard',
      label: 'admin::sidebar.overview',
      to: { name: 'admin.dashboard' },
      icon: 'tabler-layout-dashboard',
      parentKey: 'admin.heading.operations',
      sort: 1,
    },
  ],
}
