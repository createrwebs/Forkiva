import type { AuthenticationResponseJSON, RegistrationResponseJSON } from '@simplewebauthn/browser'
import {
  browserSupportsWebAuthn,
  startAuthentication,
  startRegistration,
} from '@simplewebauthn/browser'
import { http } from '@/modules/core/api/http.ts'

const publicHeaders = {
  Authorization: undefined,
}

export function supportsPasskeys(): boolean {
  return browserSupportsWebAuthn()
}

export async function loginWithPasskey() {
  const optionsResponse = await http.get('/v1/auth/passkeys/options', {
    headers: publicHeaders,
  })
  const credential: AuthenticationResponseJSON = await startAuthentication({
    optionsJSON: optionsResponse.data.body.options,
  })

  return http.post(
    '/v1/auth/passkeys/login',
    { credential },
    {
      headers: publicHeaders,
    },
  )
}

export function getPasskeys() {
  return http.get('/v1/accounts/passkeys')
}

export async function registerPasskey(name: string, currentPassword: string, code?: string) {
  const optionsResponse = await http.post('/v1/accounts/passkeys/options', {
    current_password: currentPassword,
    ...(code ? { code } : {}),
  })
  const credential: RegistrationResponseJSON = await startRegistration({
    optionsJSON: optionsResponse.data.body.options,
  })

  return http.post('/v1/accounts/passkeys', { name, credential })
}

export function deletePasskey(id: number) {
  return http.delete(`/v1/accounts/passkeys/${id}`)
}
