import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'

export const adminSidebar: SidebarList = {
  target: 'admin',
  items: [
    {
      key: 'admin.menus',
      label: 'admin::sidebar.menus',
      icon: 'tabler-list-details',
      permission: ['admin.menus.index', 'admin.online_menus.index'],
      parentKey: 'admin.heading.menu_catalog',
      sort: 101,
      children: [
        {
          key: 'admin.menus.index',
          label: 'admin::sidebar.all_menus',
          to: { name: 'admin.menus.index' },
          permission: 'admin.menus.index',
        },
        {
          key: 'admin.online_menus.index',
          label: 'admin::sidebar.online_menus',
          to: { name: 'admin.online_menus.index' },
          permission: 'admin.online_menus.index',
        },
      ],
    },
  ],
}
