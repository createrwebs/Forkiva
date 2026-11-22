export type RecoveryChannel = 'email' | 'sms'
export interface RecoveryIdentity {
  recovery_token: string
  channels: RecoveryChannel[]
  expires_in: number
}
export interface RecoveryChallenge {
  challenge_token: string
  expires_in: number
  resend_available_in: number
}
export interface RecoveryGrant {
  reset_token: string
  expires_in: number
}
