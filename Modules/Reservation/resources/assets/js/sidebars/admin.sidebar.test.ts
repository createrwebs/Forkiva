import { describe, expect, it } from 'vitest'
import { adminSidebar } from './admin.sidebar.ts'

describe('reservation settings sidebar item', () => {
  it('is a direct Settings workspace link rather than a group', () => {
    const item = adminSidebar.items.find(({ key }) => key === 'admin.reservation_settings')

    expect(item).toMatchObject({
      workspace: 'settings',
      icon: 'tabler-calendar',
      to: { name: 'admin.reservation_settings.index' },
    })
    expect(item?.children).toBeUndefined()
  })
})
