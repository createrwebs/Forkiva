import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'

export const adminSidebar: SidebarList = {
  target: 'admin',
  items: [
    {
      key: 'admin.daily_finance.index',
      label: 'dailyfinance::daily_finance.title',
      to: { name: 'admin.daily_finance.index' },
      icon: 'tabler-wallet',
      permission: 'admin.daily_finance.index',
      parentKey: 'admin.heading.operations',
      sort: 2,
    },
  ],
}
