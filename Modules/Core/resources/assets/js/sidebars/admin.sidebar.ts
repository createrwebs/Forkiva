import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'

export const adminSidebar: SidebarList = {
  target: 'admin',
  items: [
    {
      key: 'admin.heading.operations',
      label: 'admin::sidebar.operations',
      isHeading: true,
      permission: [],
      sort: 0,
    },
    {
      key: 'admin.heading.menu_catalog',
      label: 'admin::sidebar.menu_catalog',
      isHeading: true,
      permission: [],
      sort: 100,
    },
    {
      key: 'admin.heading.inventory',
      label: 'admin::sidebar.inventory',
      isHeading: true,
      permission: [],
      sort: 200,
    },
    {
      key: 'admin.heading.customers_growth',
      label: 'admin::sidebar.customers_growth',
      isHeading: true,
      permission: [],
      sort: 300,
    },
    {
      key: 'admin.heading.administration',
      label: 'admin::sidebar.administration',
      isHeading: true,
      permission: [],
      sort: 400,
    },
    {
      key: 'admin.localization',
      label: 'admin::sidebar.localization',
      workspace: 'settings',
      icon: 'tabler-world',
      parentKey: 'admin.heading.settings',
      permission: [],
      sort: 502,
      children: [],
    },
  ],
}
