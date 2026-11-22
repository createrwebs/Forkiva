import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'

export const adminSidebar: SidebarList = {
  target: 'admin',
  items: [
    {
      key: 'admin.customers.index',
      label: 'admin::sidebar.customers',
      to: { name: 'admin.customers.index' },
      icon: 'tabler-users',
      parentKey: 'admin.heading.customers_growth',
      permission: 'admin.customers.index',
      sort: 301,
    },
    {
      key: 'admin.users_and_roles_management',
      label: 'admin::sidebar.users_access',
      icon: 'tabler-users-group',
      permission: ['admin.users.index', 'admin.roles.index'],
      sort: 402,
      parentKey: 'admin.heading.administration',
      children: [
        {
          key: 'admin.users.index',
          label: 'admin::sidebar.users',
          to: { name: 'admin.users.index' },
          permission: 'admin.users.index',
        },
        {
          key: 'admin.roles.index',
          label: 'admin::sidebar.roles',
          to: { name: 'admin.roles.index' },
          permission: 'admin.roles.index',
        },
      ],
    },
  ],
}
