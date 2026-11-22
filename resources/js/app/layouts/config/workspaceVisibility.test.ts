import { describe, expect, it } from 'vitest'
import type { SidebarItem } from '@/modules/core/contracts/SidebarItem.ts'
import type { AdminWorkspace } from './adminWorkspaces.ts'
import {
  findFirstAccessibleWorkspaceDestination,
  hasAccessibleWorkspaceDestination,
  isWorkspaceFeatureEnabled,
} from './workspaceVisibility.ts'

const isAccessible = (item: SidebarItem) => item.visible !== false && item.permission !== 'denied'
const hasGrantedPermission = (item: SidebarItem) =>
  item.visible !== false && item.permission === 'allowed'

describe('workspace visibility', () => {
  const workspace = (feature?: AdminWorkspace['feature']): AdminWorkspace => ({
    id: feature === 'hr' ? 'hr' : 'restaurant',
    label: 'Workspace',
    icon: 'tabler-test-pipe',
    to: '/admin',
    feature,
  })

  it('always enables workspaces without a feature dependency', () => {
    expect(isWorkspaceFeatureEnabled(workspace())).toBe(true)
  })

  it('requires feature workspaces to be explicitly enabled', () => {
    expect(isWorkspaceFeatureEnabled(workspace('hr'))).toBe(false)
    expect(isWorkspaceFeatureEnabled(workspace('hr'), { hr: { enabled: false } })).toBe(false)
    expect(isWorkspaceFeatureEnabled(workspace('hr'), { hr: { enabled: true } })).toBe(true)
  })

  it('does not count headings or inaccessible links as workspace access', () => {
    expect(
      hasAccessibleWorkspaceDestination(
        [
          { key: 'heading', label: 'Heading', isHeading: true },
          { key: 'hidden', label: 'Hidden', to: '/hidden', visible: false },
          { key: 'denied', label: 'Denied', to: '/denied', permission: 'denied' },
        ],
        isAccessible,
      ),
    ).toBe(false)
  })

  it('shows a workspace when an accessible nested destination exists', () => {
    expect(
      hasAccessibleWorkspaceDestination(
        [
          {
            key: 'group',
            label: 'Group',
            children: [{ key: 'allowed', label: 'Allowed', to: '/allowed' }],
          },
        ],
        isAccessible,
      ),
    ).toBe(true)
  })

  it('resolves the first accessible destination in sidebar order', () => {
    const destination = findFirstAccessibleWorkspaceDestination(
      [
        { key: 'missing-permission', label: 'Missing permission', to: '/missing-permission' },
        { key: 'denied', label: 'Denied', to: '/denied', permission: 'denied' },
        {
          key: 'group',
          label: 'Group',
          permission: 'allowed',
          children: [
            { key: 'first', label: 'First', to: '/first', permission: 'allowed' },
            { key: 'second', label: 'Second', to: '/second', permission: 'allowed' },
          ],
        },
      ],
      hasGrantedPermission,
    )

    expect(destination?.key).toBe('first')
    expect(destination?.to).toBe('/first')
  })
})
