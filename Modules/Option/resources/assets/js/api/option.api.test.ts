import { beforeEach, describe, expect, it, vi } from 'vitest'
import { http } from '@/modules/core/api/http.ts'
import { store, toOptionPayload, update } from './option.api.ts'

vi.mock('@/modules/core/api/http.ts', () => ({
  http: {
    post: vi.fn(),
    put: vi.fn(),
  },
}))

describe('option API', () => {
  beforeEach(() => vi.clearAllMocks())

  it('removes temporary value IDs while preserving persisted numeric IDs', () => {
    const item = {
      name: { en: 'Size' },
      values: [
        { id: 12, label: { en: 'Small' }, price_type: 'fixed' },
        { id: '34', label: { en: 'Medium' }, price_type: 'fixed' },
        {
          id: '9f66bebe-45a4-4c8f-bae1-aae84161ed2f',
          label: { en: 'Large' },
          price_type: 'fixed',
        },
      ],
    }

    const payload = toOptionPayload(item)

    expect(payload.values).toEqual([
      item.values[0],
      item.values[1],
      { label: { en: 'Large' }, price_type: 'fixed' },
    ])
    expect(item.values[2]).toHaveProperty('id')
  })

  it('sanitizes option values before create and update requests', () => {
    const item = {
      values: [{ id: 'temporary-row-id', label: { en: 'New value' }, price_type: 'fixed' }],
    }

    store(item)
    update(7, item)

    const expected = {
      values: [{ label: { en: 'New value' }, price_type: 'fixed' }],
    }
    expect(http.post).toHaveBeenCalledWith('/v1/options', expected)
    expect(http.put).toHaveBeenCalledWith('/v1/options/7', expected)
  })
})
