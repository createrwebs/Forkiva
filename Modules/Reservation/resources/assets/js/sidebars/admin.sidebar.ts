import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'

export const adminSidebar: SidebarList = {
  target: 'admin',
  items: [
    {
      key: 'admin.reservations.group',
      label: 'reservation::sidebar.reservations',
      icon: 'tabler-calendar-event',
      permission: [
        'admin.reservations.index',
        'admin.reservations.analytics',
        'admin.reservation_waitlists.index',
        'admin.reservation_table_blocks.index',
      ],
      parentKey: 'admin.heading.operations',
      sort: 4,
      children: [
        {
          key: 'admin.reservation_analytics.index',
          label: 'reservation::sidebar.analytics',
          to: { name: 'admin.reservation_analytics.index' },
          permission: 'admin.reservations.analytics',
        },
        {
          key: 'admin.reservations.index',
          label: 'reservation::sidebar.reservations',
          to: { name: 'admin.reservations.index' },
          permission: 'admin.reservations.index',
        },
        {
          key: 'admin.reservation_waitlists.index',
          label: 'reservation::sidebar.reservation_waitlists',
          to: { name: 'admin.reservation_waitlists.index' },
          permission: 'admin.reservation_waitlists.index',
        },
        {
          key: 'admin.reservation_table_blocks.index',
          label: 'reservation::sidebar.reservation_table_blocks',
          to: { name: 'admin.reservation_table_blocks.index' },
          permission: 'admin.reservation_table_blocks.index',
        },
      ],
    },
    {
      key: 'admin.reservation_settings',
      label: 'reservation::sidebar.reservation_settings',
      workspace: 'settings',
      icon: 'tabler-calendar',
      parentKey: 'admin.heading.settings',
      permission: 'admin.reservation_settings.index',
      sort: 505,
      to: { name: 'admin.reservation_settings.index' },
    },
  ],
}
