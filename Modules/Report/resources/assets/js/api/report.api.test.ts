import { beforeEach, describe, expect, it, vi } from 'vitest'
import { exportReport } from './report.api'

const mocks = vi.hoisted(() => ({ get: vi.fn() }))
vi.mock('@/modules/core/api/http.ts', () => ({ http: { get: mocks.get } }))

describe('report downloads', () => {
  beforeEach(() => vi.clearAllMocks())

  it.each(['pdf', 'json', 'xlsx', 'xls', 'csv'])(
    'downloads payroll %s with GET and preserves query context',
    async (format) => {
      const blob = new Blob(['report'])
      mocks.get.mockResolvedValue({ data: blob })
      const filters = { branch_id: 7, payroll_period_id: 3 }
      const sorts = [{ key: 'net_amount', order: 'desc' as const }]
      const response = await exportReport(format, filters, {
        apiUri: '/v1/payroll-reports/run_summary/export',
        sorts,
      })
      expect(mocks.get).toHaveBeenCalledExactlyOnceWith(
        `/v1/payroll-reports/run_summary/export/${format}`,
        { params: { filters, sorts }, responseType: 'blob' },
      )
      expect(response.data).toBe(blob)
    },
  )
})
