export type TwoFactorMethod = 'authenticator' | 'email' | 'sms'

export interface TwoFactorChallenge {
  two_factor_required: true
  challenge_token: string
  method: TwoFactorMethod
  destination: string | null
  can_resend: boolean
  resend_available_in: number
  expires_in: number
  delivery_failed?: boolean
}

export interface TwoFactorMethodOption {
  id: TwoFactorMethod
  enabled: boolean
  available: boolean
  destination: string | null
}

export interface TwoFactorStatus {
  available: boolean
  enabled: boolean
  method: TwoFactorMethod | null
  confirmed_at: string | null
  recovery_codes_remaining: number
  resend_available_in: number
  methods: TwoFactorMethodOption[]
}

export interface TwoFactorSetup {
  method: TwoFactorMethod
  secret?: string
  otpauth_uri?: string
  destination?: string
  expires_in: number
  resend_available_in: number
}
