import { describe, expect, it } from 'vitest'
import { adminSidebar as notificationSidebar } from '@/modules/notification/sidebars/admin.sidebar.ts'
import { adminSidebar as printerSidebar } from '@/modules/printer/sidebars/admin.sidebar.ts'
import { adminSidebar as reservationSidebar } from '@/modules/reservation/sidebars/admin.sidebar.ts'
import { adminSidebar as serviceSidebar } from '@/modules/service/sidebars/admin.sidebar.ts'
import { adminSidebar as settingSidebar } from '@/modules/setting/sidebars/admin.sidebar.ts'
import { adminSidebar as coreSidebar } from './admin.sidebar.ts'

describe('Settings workspace sidebar', () => {
  it('keeps the requested top-level order', () => {
    expect(coreSidebar.items.some(({ key }) => key === 'admin.heading.settings')).toBe(false)

    const items = [
      ...coreSidebar.items,
      ...notificationSidebar.items,
      ...printerSidebar.items,
      ...reservationSidebar.items,
      ...serviceSidebar.items,
      ...settingSidebar.items,
    ]
      .filter(({ parentKey }) => parentKey === 'admin.heading.settings')
      .sort((left, right) => (left.sort ?? 0) - (right.sort ?? 0))

    expect(items.map(({ key }) => key)).toEqual([
      'admin.services.index',
      'admin.localization',
      'admin.notifications',
      'admin.reservation_settings',
      'admin.manage_printers',
      'admin.settings.edit',
    ])
  })
})
