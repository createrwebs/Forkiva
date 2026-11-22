import type { TwoFactorMethod } from '@/modules/auth/contracts/TwoFactor.ts'
import { http } from '@/modules/core/api/http.ts'

export function verifyLogin(challengeToken: string, code: string) {
  return http.post(
    '/v1/auth/two-factor/verify',
    { challenge_token: challengeToken, code },
    { headers: { Authorization: undefined } },
  )
}

export function resendLogin(challengeToken: string) {
  return http.post(
    '/v1/auth/two-factor/resend',
    { challenge_token: challengeToken },
    { headers: { Authorization: undefined } },
  )
}

export function getStatus() {
  return http.get('/v1/accounts/two-factor')
}

export function setup(method: TwoFactorMethod, currentPassword: string, code?: string) {
  return http.post('/v1/accounts/two-factor/setup', {
    method,
    current_password: currentPassword,
    ...(code ? { code } : {}),
  })
}

export function confirm(code: string) {
  return http.post('/v1/accounts/two-factor/confirm', { code })
}

export function resendSetup() {
  return http.post('/v1/accounts/two-factor/setup/resend')
}

export function sendAccountCode() {
  return http.post('/v1/accounts/two-factor/code')
}

export function disable(code: string, currentPassword: string) {
  return http.delete('/v1/accounts/two-factor', {
    data: { code, current_password: currentPassword },
  })
}

export function regenerateRecoveryCodes(code: string, currentPassword: string) {
  return http.post('/v1/accounts/two-factor/recovery-codes', {
    code,
    current_password: currentPassword,
  })
}
