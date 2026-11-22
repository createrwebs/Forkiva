import { describe, expect, it } from 'vitest'
import { adminSidebar } from './admin.sidebar.ts'

describe('settings workspace sidebar', () => {
  it('places every settings section in the final Settings workspace group', () => {
    const settingsGroup = adminSidebar.items.find(({ key }) => key === 'admin.settings.edit')

    expect(adminSidebar).toMatchObject({ target: 'admin', workspace: 'settings' })
    expect(settingsGroup).toMatchObject({
      label: 'setting::settings.settings',
      sort: 599,
    })
    expect(settingsGroup?.children?.map(({ key }) => key)).toEqual([
      'admin.settings.general',
      'admin.settings.application',
      'admin.settings.appearance',
      'admin.settings.logo',
      'admin.settings.currency',
      'admin.settings.pos',
      'admin.settings.kitchen',
      'admin.settings.table_qr_ordering',
      'admin.settings.pwa',
      'admin.settings.two_factor',
      'admin.settings.passkeys',
      'admin.settings.password_recovery',
      'admin.settings.filesystem',
      'admin.settings.pdf',
    ])
    expect(settingsGroup?.children?.every(({ exact }) => exact)).toBe(true)
  })
})
