import type { Router } from 'vue-router'
import { describe, expect, it, vi } from 'vitest'
import { getDefaultRoute } from './defaultRoute.ts'

const accountingRoles = ['accountant', 'accounting_manager', 'accounting_auditor']

function createAuthStore(role: string, permissions: string[]) {
  return {
    getUser: null,
    hasRole: (roles: string | string[]) => (Array.isArray(roles) ? roles : [roles]).includes(role),
    can: (permission: string) => permissions.includes(permission),
  }
}

describe('getDefaultRoute', () => {
  it.each(accountingRoles)('sends %s to the accounting dashboard', (role) => {
    const router = {
      hasRoute: vi.fn((name: string) => name === 'admin.accounting.dashboard'),
    }

    const route = getDefaultRoute(
      createAuthStore(role, ['admin.accounting_dashboard.index']) as never,
      router as unknown as Router,
    )

    expect(route).toEqual({ name: 'admin.accounting.dashboard' })
  })

  it('does not send accounting roles to an unavailable dashboard route', () => {
    const router = { hasRoute: vi.fn(() => false) }

    const route = getDefaultRoute(
      createAuthStore('accountant', ['admin.accounting_dashboard.index']) as never,
      router as unknown as Router,
    )

    expect(route).toEqual({ name: 'admin.dashboard' })
  })
})
