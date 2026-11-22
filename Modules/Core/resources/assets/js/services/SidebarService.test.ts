import { describe, expect, it } from 'vitest'
import { sidebarRegistry } from './SidebarService.ts'

describe('SidebarService workspaces', () => {
  it('keeps admin workspace navigation trees isolated', () => {
    sidebarRegistry.register('admin', [
      { key: 'test.restaurant', label: 'Restaurant' },
      { key: 'test.inventory', label: 'Inventory', workspace: 'test-inventory' },
    ])
    sidebarRegistry.register(
      'admin',
      [{ key: 'test.delivery', label: 'Delivery' }],
      'test-delivery',
    )

    expect(sidebarRegistry.build('admin').map(({ key }) => key)).toContain('test.restaurant')
    expect(sidebarRegistry.build('admin').map(({ key }) => key)).not.toContain('test.delivery')
    expect(sidebarRegistry.build('admin').map(({ key }) => key)).not.toContain('test.inventory')
    expect(sidebarRegistry.build('admin', false, 'test-delivery').map(({ key }) => key)).toEqual([
      'test.delivery',
    ])
    expect(sidebarRegistry.build('admin', false, 'test-inventory').map(({ key }) => key)).toEqual([
      'test.inventory',
    ])
  })
})
