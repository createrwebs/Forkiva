import { startAuthentication, startRegistration } from '@simplewebauthn/browser'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { http } from '@/modules/core/api/http.ts'
import { deletePasskey, getPasskeys, loginWithPasskey, registerPasskey } from './passkey.api.ts'

vi.mock('@simplewebauthn/browser', () => ({
  browserSupportsWebAuthn: vi.fn(() => true),
  startAuthentication: vi.fn(),
  startRegistration: vi.fn(),
}))

vi.mock('@/modules/core/api/http.ts', () => ({
  http: {
    delete: vi.fn(),
    get: vi.fn(),
    post: vi.fn(),
  },
}))

describe('passkey API', () => {
  beforeEach(() => vi.clearAllMocks())

  it('performs the guest passkey login ceremony without an auth token', async () => {
    const options = { challenge: 'login-challenge' }
    const credential = { id: 'credential-id', type: 'public-key' }

    vi.mocked(http.get).mockResolvedValueOnce({ data: { body: { options } } })
    vi.mocked(startAuthentication).mockResolvedValueOnce(credential as never)
    vi.mocked(http.post).mockResolvedValueOnce({ data: { body: { token: 'token' } } })

    await loginWithPasskey()

    expect(http.get).toHaveBeenCalledWith('/v1/auth/passkeys/options', {
      headers: { Authorization: undefined },
    })
    expect(startAuthentication).toHaveBeenCalledWith({ optionsJSON: options })
    expect(http.post).toHaveBeenCalledWith(
      '/v1/auth/passkeys/login',
      { credential },
      { headers: { Authorization: undefined } },
    )
  })

  it('performs registration and exposes passkey management endpoints', async () => {
    const options = { challenge: 'registration-challenge' }
    const credential = { id: 'credential-id', type: 'public-key' }

    vi.mocked(http.post).mockResolvedValueOnce({ data: { body: { options } } })
    vi.mocked(startRegistration).mockResolvedValueOnce(credential as never)
    vi.mocked(http.post).mockResolvedValueOnce({ data: { body: {} } })

    await registerPasskey('Work laptop', 'fixture-password', 'ABCDE-12345')
    getPasskeys()
    deletePasskey(9)

    expect(http.post).toHaveBeenNthCalledWith(1, '/v1/accounts/passkeys/options', {
      current_password: 'fixture-password',
      code: 'ABCDE-12345',
    })
    expect(startRegistration).toHaveBeenCalledWith({ optionsJSON: options })
    expect(http.post).toHaveBeenCalledWith('/v1/accounts/passkeys', {
      name: 'Work laptop',
      credential,
    })
    expect(http.get).toHaveBeenCalledWith('/v1/accounts/passkeys')
    expect(http.delete).toHaveBeenCalledWith('/v1/accounts/passkeys/9')
  })

  it('does not open a browser ceremony when reauthentication is rejected', async () => {
    vi.mocked(http.post).mockRejectedValueOnce(new Error('Identity confirmation required'))
    await expect(registerPasskey('Laptop', 'wrong-password')).rejects.toThrow(
      'Identity confirmation required',
    )
    expect(startRegistration).not.toHaveBeenCalled()
    expect(http.post).toHaveBeenCalledTimes(1)
    expect(http.get).not.toHaveBeenCalled()
  })
})
