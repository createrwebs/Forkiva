import type { DropdownItem } from '@/modules/core/contracts/DropdownItem.ts'
import type { MediaSummary } from '@/modules/media/contracts/media.ts'
import type { ThemeMode } from '@/modules/core/contracts/AppState.ts'
import type { PosSound, PosSoundAction } from '@/modules/pos/enums/posSound'

export interface KitchenSettings {
  auto_refresh_enabled: boolean
  auto_refresh_mode: string | null
  auto_refresh_interval: number | null
  auto_refresh_pause_on_idle: boolean
  auto_refresh_idle_timeout: number | null
  kitchen_show_served_orders_enabled: boolean
  kitchen_show_served_orders_minutes: number | null
  kitchen_sound_enabled: boolean
  kitchen_new_order_sound: string | null
  kitchen_order_updated_sound: string | null
  kitchen_sound_volume: number | null
}

export interface KitchenSettingsMeta {
  modes: DropdownItem[]
  sounds: DropdownItem[]
}

export interface KitchenSettingsResponse {
  settings: KitchenSettings
  meta: KitchenSettingsMeta
}

export type PosSettings = {
  pos_sound_enabled: boolean
  pos_sound_volume: number
} & Record<`pos_sound_${PosSoundAction}_enabled`, boolean> &
  Record<`pos_sound_${PosSoundAction}_sound`, PosSound | null>

export interface PosSettingsMeta {
  sounds: Array<{ id: PosSound; name: string }>
}

export interface PosSettingsResponse {
  settings: PosSettings
  meta: PosSettingsMeta
}

export interface LogoSettings {
  logo: MediaSummary | null
  email_logo: MediaSummary | null
  favicon: MediaSummary | null
}

export interface LogoSettingsResponse {
  settings: LogoSettings
}

export interface AppearanceSettings {
  default_theme_mode: ThemeMode
  theme_primary_color: string
  theme_secondary_color: string
  theme_success_color: string
  theme_info_color: string
  theme_warning_color: string
  theme_error_color: string
  theme_dark_primary_color: string
  theme_dark_secondary_color: string
  theme_dark_success_color: string
  theme_dark_info_color: string
  theme_dark_warning_color: string
  theme_dark_error_color: string
}

export interface AppearanceSettingsMeta {
  theme_modes: DropdownItem[]
}

export interface AppearanceSettingsResponse {
  settings: AppearanceSettings
  meta: AppearanceSettingsMeta
}

export interface PwaSettings {
  pwa_enabled: boolean
  pwa_name: string
  pwa_short_name: string
  pwa_icon: MediaSummary | null
  pwa_background_color: string
  pwa_theme_color: string
  pwa_description: string | null
}

export interface PwaSettingsResponse {
  settings: PwaSettings
}

export interface PasskeySettings {
  passkeys_enabled: boolean
  passkeys_relying_party_id: string
  passkeys_allowed_origins: string[]
  passkeys_timeout: number
}

export interface PasskeySettingsResponse {
  settings: PasskeySettings
}

export interface TwoFactorSettings {
  two_factor_enabled: boolean
  two_factor_authenticator_enabled: boolean
  two_factor_email_enabled: boolean
  two_factor_sms_enabled: boolean
}

export interface TwoFactorSettingsResponse {
  settings: TwoFactorSettings
}

export interface GeneralSettings {
  supported_countries: string[]
  default_country: string
  supported_locales: string[]
  default_locale: string
  default_timezone: string
  default_date_format: string
  default_time_format: string
  start_of_week: string
  end_of_week: string
}

export interface GeneralSettingsMeta {
  countries: DropdownItem[]
  locales: DropdownItem[]
  timezones: DropdownItem[]
  date_formats: DropdownItem[]
  time_formats: DropdownItem[]
  days: DropdownItem[]
}

export interface GeneralSettingsResponse {
  settings: GeneralSettings
  meta: GeneralSettingsMeta
}

export interface TableQrOrderingSettings {
  table_qr_ordering_enabled: boolean
  table_qr_auto_send_to_kitchen: boolean
  table_qr_require_guest_count: boolean
  table_qr_allow_order_notes: boolean
  table_qr_allow_discount_code: boolean
  table_qr_register_session_strategy: string
  table_qr_cart_expires_minutes: number
}

export interface TableQrOrderingSettingsMeta {
  table_qr_register_session_strategies: DropdownItem[]
}

export interface TableQrOrderingSettingsResponse {
  settings: TableQrOrderingSettings
  meta: TableQrOrderingSettingsMeta
}

export interface CurrencySettings {
  supported_currencies: string[]
  default_currency: string
  currency_rate_exchange_service: string | null
  forge_api_key: string | null
  fixer_access_key: string | null
  currency_data_feed_api_key: string | null
  auto_refresh_currency_rates: boolean
  auto_refresh_currency_rate_frequency: string | null
}

export interface CurrencySettingsMeta {
  currencies: DropdownItem[]
  frequencies: DropdownItem[]
  exchange_services: DropdownItem[]
}

export interface CurrencySettingsResponse {
  settings: CurrencySettings
  meta: CurrencySettingsMeta
}

export interface FilesystemSettings {
  default_filesystem_disk: string
  default_private_filesystem_disk: string
  filesystem_s3_public_use_path_style_endpoint: boolean
  filesystem_s3_public_url: string | null
  filesystem_s3_public_endpoint: string | null
  filesystem_s3_public_region: string | null
  filesystem_s3_public_bucket: string | null
  filesystem_s3_private_use_path_style_endpoint: boolean
  filesystem_s3_private_url: string | null
  filesystem_s3_private_endpoint: string | null
  filesystem_s3_private_region: string | null
  filesystem_s3_private_bucket: string | null
  encryptable: {
    filesystem_s3_public_key: string | null
    filesystem_s3_public_secret: string | null
    filesystem_s3_private_key: string | null
    filesystem_s3_private_secret: string | null
  }
}

export interface FilesystemSettingsMeta {
  disks: DropdownItem[]
  private_disks: DropdownItem[]
}

export interface FilesystemSettingsResponse {
  settings: FilesystemSettings
  meta: FilesystemSettingsMeta
}

export interface ApplicationSettings {
  app_name: Record<string, string>
}

export interface SupportedLanguagesState {
  id: string
  name: string
}

export interface ApplicationSettingsResponse {
  settings: ApplicationSettings
}

export type PdfDriver =
  'dompdf' | 'browsershot' | 'chrome' | 'cloudflare' | 'gotenberg' | 'weasyprint'

export interface PdfSettings {
  pdf_default_driver: PdfDriver
  pdf_dompdf_remote_enabled: boolean
  pdf_dompdf_chroot: string | null
  pdf_browsershot_node_binary: string | null
  pdf_browsershot_npm_binary: string | null
  pdf_browsershot_chrome_path: string | null
  pdf_browsershot_node_modules_path: string | null
  pdf_browsershot_no_sandbox: boolean
  pdf_chrome_binary: string | null
  pdf_chrome_no_sandbox: boolean
  pdf_chrome_startup_timeout: number
  pdf_chrome_timeout: number
  pdf_chrome_operation_timeout: number
  pdf_chrome_user_data_dir: string | null
  pdf_cloudflare_account_id: string | null
  pdf_gotenberg_url: string
  pdf_gotenberg_username: string | null
  pdf_weasyprint_binary: string
  pdf_weasyprint_timeout: number
  encryptable: {
    pdf_cloudflare_api_token: string | null
    pdf_gotenberg_password: string | null
  }
  pdf_secret_status: {
    cloudflare_api_token: boolean
    gotenberg_password: boolean
  }
  pdf_clear_secrets: {
    cloudflare_api_token: boolean
    gotenberg_password: boolean
  }
}

export interface PdfSettingsMeta {
  drivers: Array<{
    id: PdfDriver
    name: string
    requirements: string
    available: boolean
    message: string | null
    verification: null | {
      ready: boolean
      checked_at: string
      duration_ms: number
    }
  }>
}

export interface PdfSettingsResponse {
  settings: PdfSettings
  meta: PdfSettingsMeta
}
