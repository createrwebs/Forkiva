import { describe, expect, it } from 'vitest'
import manifest from './manifest'
import dashboardRoutes from '@/modules/dashboard/routes/admin.routes'
import { adminSidebar as dashboardSidebar } from '@/modules/dashboard/sidebars/admin.sidebar'

describe('daily finance module registration', () => {
  it('owns its route and sidebar entry with a dedicated permission', () => {
    expect(manifest.name).toBe('dailyfinance')
    const route = manifest.routes?.[0]?.routes.find(
      (item) => item.name === 'admin.daily_finance.index',
    )
    expect(route).toMatchObject({
      path: 'daily-finance',
      meta: { permission: 'admin.daily_finance.index', title: 'dailyfinance::daily_finance.title' },
    })
    expect(manifest.sidebars?.[0]?.items).toEqual([
      expect.objectContaining({
        key: 'admin.daily_finance.index',
        permission: 'admin.daily_finance.index',
        to: { name: 'admin.daily_finance.index' },
      }),
    ])
    expect(dashboardRoutes.some((item) => item.name === 'admin.daily_finance.index')).toBe(false)
    expect(dashboardSidebar.items.some((item) => item.key === 'admin.daily_finance.index')).toBe(
      false,
    )
  })
})
