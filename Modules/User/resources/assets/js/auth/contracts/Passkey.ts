export interface Passkey {
  id: number
  name: string
  authenticator: string | null
  last_used_at: string | null
  created_at: string | null
}
