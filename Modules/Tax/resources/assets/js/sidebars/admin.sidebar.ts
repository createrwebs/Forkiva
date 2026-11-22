import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'

export const adminSidebar: SidebarList = {
  target: 'admin',
  workspace: 'settings',
  items: [
    {
      key: 'admin.taxes.index',
      label: 'admin::sidebar.taxes',
      to: { name: 'admin.taxes.index' },
      permission: 'admin.taxes.index',
      parentKey: 'admin.localization',
      sort: 3,
    },
  ],
}
