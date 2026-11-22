import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { installerRequest } from './installer'

const fetchMock = vi.fn<typeof fetch>()
const state = { checks_passed: false, database: false, tasks: [], admin: false, finished: false }

beforeEach(() => {
  fetchMock.mockReset()
  vi.stubGlobal('fetch', fetchMock)
  document.head.innerHTML = '<meta name="csrf-token" content="setup-csrf-token">'
})

afterEach(() => {
  vi.unstubAllGlobals()
  document.head.innerHTML = ''
})

describe('installer web transport', () => {
  it('loads progress from web routes with the setup session', async () => {
    fetchMock.mockResolvedValue(new Response(JSON.stringify({ body: state })))

    await expect(installerRequest('status')).resolves.toEqual(state)
    expect(fetchMock).toHaveBeenCalledWith(
      '/installer/status',
      expect.objectContaining({ method: 'GET', credentials: 'same-origin', cache: 'no-store' }),
    )
  })

  it('sends mutations to web routes with JSON and CSRF protection', async () => {
    fetchMock.mockResolvedValue(new Response(JSON.stringify({ body: state })))

    await installerRequest('checks', {})
    expect(fetchMock).toHaveBeenCalledWith(
      '/installer/checks',
      expect.objectContaining({
        method: 'POST',
        credentials: 'same-origin',
        headers: expect.objectContaining({
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': 'setup-csrf-token',
        }),
        body: '{}',
      }),
    )
  })

  it('preserves API field errors and explains expired setup sessions', async () => {
    fetchMock.mockResolvedValueOnce(
      new Response(JSON.stringify({ errors: { db_port: ['Invalid port.'] } }), { status: 422 }),
    )
    await expect(installerRequest('database', { db_port: 0 })).rejects.toMatchObject({
      message: 'Review the highlighted fields and try again.',
      fields: { db_port: ['Invalid port.'] },
    })

    fetchMock.mockResolvedValueOnce(new Response('Page Expired', { status: 419 }))
    await expect(installerRequest('checks', {})).rejects.toThrow('Your setup session expired.')
  })

  it('retains the HTTP status for safe recovery from gateway timeouts', async () => {
    fetchMock.mockResolvedValueOnce(new Response('<h1>Gateway Timeout</h1>', { status: 504 }))
    await expect(installerRequest('process', {})).rejects.toMatchObject({
      status: 504,
      fields: {},
      message: expect.stringContaining('task may still be running'),
    })
  })

  it('describes a database verification failure without claiming a field is invalid', async () => {
    const errors = {
      database: ['Detected MariaDB 10.2.44. Forkiva requires MariaDB 10.3.0 or newer.'],
    }
    fetchMock.mockResolvedValueOnce(new Response(JSON.stringify({ errors }), { status: 422 }))

    await expect(installerRequest('database', {})).rejects.toMatchObject({
      message: 'Database verification failed.',
      fields: errors,
    })
  })
})
