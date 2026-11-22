import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'

export const adminSidebar: SidebarList = {
  target: 'admin',
  workspace: 'settings',
  items: [
    {
      key: 'admin.manage_printers',
      label: 'admin::sidebar.printing',
      icon: 'tabler-printer',
      permission: ['admin.printers.index', 'admin.print_agents.index'],
      sort: 506,
      parentKey: 'admin.heading.settings',
      children: [
        {
          key: 'admin.printers.index',
          label: 'admin::sidebar.printers',
          to: { name: 'admin.printers.index' },
          permission: 'admin.printers.index',
        },
        {
          key: 'admin.print_agents.index',
          label: 'admin::sidebar.print_agents',
          to: { name: 'admin.print_agents.index' },
          permission: 'admin.print_agents.index',
        },
      ],
    },
  ],
}
