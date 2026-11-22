import { http } from '@/modules/core/api/http.ts'
import type {
  RecoveryChannel,
  RecoveryChallenge,
  RecoveryGrant,
  RecoveryIdentity,
} from '@/modules/auth/contracts/PasswordRecovery.ts'
const config = { headers: { Authorization: undefined } }
export function getRecoveryOptions() {
  return http.get('/v1/auth/password-recovery/options', config)
}
export function identifyRecoveryAccount(identifier: string) {
  return http.post<{ body: RecoveryIdentity }>(
    '/v1/auth/password-recovery/identify',
    { identifier },
    config,
  )
}
export function requestRecoveryCode(channel: RecoveryChannel, recoveryToken: string) {
  return http.post<{ body: RecoveryChallenge }>(
    '/v1/auth/password-recovery/code',
    { channel, recovery_token: recoveryToken },
    config,
  )
}
export function verifyRecoveryCode(challengeToken: string, code: string) {
  return http.post<{ body: RecoveryGrant }>(
    '/v1/auth/password-recovery/verify',
    { challenge_token: challengeToken, code },
    config,
  )
}
export function resetPassword(data: {
  reset_token: string
  password: string
  password_confirmation: string
}) {
  return http.post('/v1/auth/password-recovery/reset', data, config)
}
