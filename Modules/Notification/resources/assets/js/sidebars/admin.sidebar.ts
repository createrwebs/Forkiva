import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'

export const adminSidebar: SidebarList = {
  target: 'admin',
  workspace: 'settings',
  items: [
    {
      key: 'admin.notifications',
      label: 'admin::sidebar.notifications',
      icon: 'tabler-message-cog',
      parentKey: 'admin.heading.settings',
      permission: ['admin.notification_workflows.index', 'admin.notification_templates.index'],
      sort: 503,
      children: [
        {
          key: 'admin.notification_settings.index',
          label: 'admin::sidebar.notification_settings',
          to: { name: 'admin.notification_settings.index' },
          permission: 'admin.notification_workflows.index',
          sort: 1,
        },
        {
          key: 'admin.notification_templates.index',
          label: 'admin::sidebar.notification_templates',
          to: { name: 'admin.notification_templates.index' },
          permission: 'admin.notification_templates.index',
          sort: 2,
        },
      ],
    },
  ],
}
