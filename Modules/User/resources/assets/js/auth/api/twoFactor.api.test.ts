import { beforeEach, describe, expect, it, vi } from 'vitest'
import { http } from '@/modules/core/api/http.ts'
import { setup, verifyLogin, resendLogin, resendSetup } from './twoFactor.api.ts'

vi.mock('@/modules/core/api/http.ts', () => ({ http: { post: vi.fn() } }))

describe('two-factor API contract', () => {
  beforeEach(() => vi.clearAllMocks())

  it('includes the existing factor when replacing a method', () => {
    setup('email', 'fixture-password', 'ABCDE-12345')
    expect(http.post).toHaveBeenCalledWith('/v1/accounts/two-factor/setup', {
      method: 'email',
      current_password: 'fixture-password',
      code: 'ABCDE-12345',
    })
  })

  it('keeps first-time enrollment compatible', () => {
    setup('authenticator', 'fixture-password')
    expect(http.post).toHaveBeenCalledWith('/v1/accounts/two-factor/setup', {
      method: 'authenticator',
      current_password: 'fixture-password',
    })
  })

  it('resends the authenticated pending setup without starting a new enrollment', () => {
    resendSetup()
    expect(http.post).toHaveBeenCalledWith('/v1/accounts/two-factor/setup/resend')
  })

  it('does not attach an existing account token to guest verification or resend', () => {
    verifyLogin('fixture-challenge', 'ABCDE-12345')
    resendLogin('fixture-challenge')
    expect(http.post).toHaveBeenNthCalledWith(
      1,
      '/v1/auth/two-factor/verify',
      {
        challenge_token: 'fixture-challenge',
        code: 'ABCDE-12345',
      },
      { headers: { Authorization: undefined } },
    )
    expect(http.post).toHaveBeenNthCalledWith(
      2,
      '/v1/auth/two-factor/resend',
      {
        challenge_token: 'fixture-challenge',
      },
      { headers: { Authorization: undefined } },
    )
  })
})
