import { describe, expect, it } from 'vitest'
import { reports } from '@/modules/report/pages/admin/report/reports.ts'
import { adminSidebar } from './admin.sidebar.ts'

describe('reports workspace sidebar', () => {
  it('lists every report beneath its group heading without an index link', () => {
    const reportCount = Object.values(reports).flat().length
    const headings = adminSidebar.items.filter(({ isHeading }) => isHeading)
    const links = adminSidebar.items.filter(({ to }) => to)

    expect(adminSidebar).toMatchObject({ target: 'admin', workspace: 'reports' })
    expect(headings).toHaveLength(Object.keys(reports).length)
    expect(links).toHaveLength(reportCount)
    expect(adminSidebar.items.some(({ key }) => key === 'admin.reports.index')).toBe(false)
  })
})
