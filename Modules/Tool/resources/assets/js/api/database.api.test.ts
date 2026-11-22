import { beforeEach, describe, expect, it, vi } from 'vitest'
import { http } from '@/modules/core/api/http.ts'
import { backup, downloadBackup, index, restore, restoreFromBackup } from './database.api.ts'

vi.mock('@/modules/core/api/http.ts', () => ({
  http: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

describe('database tool API', () => {
  beforeEach(() => vi.clearAllMocks())

  it('uses the registered database tool endpoints', () => {
    index()
    backup()
    restoreFromBackup('backup sqlite.sqlite')
    downloadBackup('backup sqlite.sqlite')

    expect(http.get).toHaveBeenNthCalledWith(1, '/v1/tools/database')
    expect(http.post).toHaveBeenNthCalledWith(1, '/v1/tools/database/backup')
    expect(http.post).toHaveBeenNthCalledWith(
      2,
      '/v1/tools/database/restore/backup%20sqlite.sqlite',
    )
    expect(http.get).toHaveBeenNthCalledWith(
      2,
      '/v1/tools/database/download/backup%20sqlite.sqlite',
      { responseType: 'blob' },
    )
  })

  it('uploads a restore file as multipart form data', () => {
    const file = new File(['-- backup'], 'backup.sql', { type: 'text/plain' })

    restore(file)

    expect(http.post).toHaveBeenCalledOnce()
    const call = vi.mocked(http.post).mock.calls[0]
    expect(call).toBeDefined()
    const [url, body, options] = call!
    expect(url).toBe('/v1/tools/database/restore')
    expect(body).toBeInstanceOf(FormData)
    expect((body as FormData).get('file')).toBe(file)
    expect(options).toEqual({ headers: { 'Content-Type': 'multipart/form-data' } })
  })
})
