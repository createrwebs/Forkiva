export type Step = 'welcome' | 'checks' | 'configuration' | 'database' | 'admin' | 'launch'
export interface Check {
  id: string
  label: string
  passed: boolean
  detail: string
  remedy: string | null
}
export interface Task {
  id: string
  label: string
  status: 'pending' | 'running' | 'complete' | 'failed'
}
export interface InstallationState {
  processing?: boolean
  configuration?: Configuration
  options?: ConfigurationOptions
  checks_passed: boolean
  checks?: Check[]
  database: boolean
  tasks: Task[]
  admin: boolean
  finished: boolean
  error?: string | null
}
export type FieldErrors = Record<string, string[]>

export interface Configuration {
  app_name: string
  app_url: string
  supported_countries: string[]
  default_country: string
  supported_locales: string[]
  default_locale: string
  default_timezone: string
  supported_currencies: string[]
  default_currency: string
  passkeys_enabled: boolean
  passkeys_relying_party_id: string
  passkeys_allowed_origins: string[]
  cache_enabled: boolean
  cache_store: 'file' | 'database'
}
export interface ConfigurationOption {
  id: string
  name: string
}
export interface ConfigurationOptions {
  countries: ConfigurationOption[]
  locales: ConfigurationOption[]
  currencies: ConfigurationOption[]
  timezones: ConfigurationOption[]
}
