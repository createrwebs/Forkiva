import { describe, expect, it } from 'vitest'
import { adminWorkspaces, getAdminWorkspace, isAdminWorkspaceId } from './adminWorkspaces.ts'

describe('admin workspace navigation', () => {
  it('keeps the requested workspace order without using route targets as identifiers', () => {
    expect(adminWorkspaces.map((workspace) => workspace.id)).toEqual([
      'restaurant',
      'delivery',
      'hr',
      'accounting',
      'reports',
      'settings',
    ])
    expect(new Set(adminWorkspaces.map((workspace) => workspace.id)).size).toBe(6)
  })

  it('recognizes admin workspaces without treating personal panels as workspaces', () => {
    expect(isAdminWorkspaceId('delivery')).toBe(true)
    expect(isAdminWorkspaceId('employee')).toBe(false)
    expect(isAdminWorkspaceId('driver')).toBe(false)
  })

  it('resolves the workspace metadata for the contextual sidebar header', () => {
    expect(getAdminWorkspace('hr')).toMatchObject({
      feature: 'hr',
      label: 'hr::sidebar.human_resources',
      sidebarWorkspace: 'hr',
    })
  })

  it('makes reports a dedicated workspace', () => {
    expect(getAdminWorkspace('reports')).toMatchObject({
      sidebarWorkspace: 'reports',
      to: { name: 'admin.reports' },
    })
  })

  it('opens General Settings when entering the Settings workspace', () => {
    expect(getAdminWorkspace('settings')).toMatchObject({
      sidebarWorkspace: 'settings',
      to: { name: 'admin.settings.general' },
    })
  })

  it('uses the translated short Hub label', () => {
    expect(getAdminWorkspace('restaurant').label).toBe('admin::sidebar.restaurant_hub')
  })
})
