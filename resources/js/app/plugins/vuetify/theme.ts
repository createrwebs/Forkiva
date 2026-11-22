import type { ThemeDefinition } from 'vuetify'
import type { AppSettings, ThemeMode } from '@/modules/core/contracts/AppState.ts'

const SYSTEM_THEME_MEDIA_QUERY = '(prefers-color-scheme: dark)'

export const staticPrimaryColor = '#F57C00'
export const staticPrimaryDarkenColor = '#EF6C00'
export const staticSecondaryColor = '#043A63' // 00695C
export const staticDarkSurfaceColor = '#2B2C40'
export const staticDarkOnSurfaceColor = '#E6E6F1'
export const themes: Record<string, ThemeDefinition> = {
  light: {
    dark: false,
    colors: {
      primary: staticPrimaryColor,
      'on-primary': '#fff',
      'primary-darken-1': staticPrimaryDarkenColor,
      'primary-light': '#8789FF',
      secondary: staticSecondaryColor,
      'on-secondary': '#fff',
      'secondary-darken-1': staticSecondaryColor,
      'secondary-light': staticSecondaryColor,
      success: '#2ecc71',
      'on-success': '#fff',
      'success-darken-1': '#66C732',
      'success-light': '#53D28C',
      info: '#03C3EC',
      'on-info': '#fff',
      'info-darken-1': '#03AFD4',
      'info-light': '#35CFF0',
      warning: '#f1c40f',
      'on-warning': '#fff',
      'warning-darken-1': '#E69A00',
      'warning-light': '#FFBC33',
      error: '#e74c3c',
      'on-error': '#fff',
      'error-darken-1': '#E6381A',
      'error-light': '#FF654A',
      background: '#f5f5f9',
      'on-background': '#22303E',
      surface: '#fff',
      'on-surface': '#22303E',
      'grey-50': '#FAFAFA',
      'grey-100': '#F5F5F5',
      'grey-200': '#EEEEEE',
      'grey-300': '#E0E0E0',
      'grey-400': '#BDBDBD',
      'grey-500': '#9E9E9E',
      'grey-600': '#757575',
      'grey-700': '#616161',
      'grey-800': '#424242',
      'grey-900': '#212121',
      'grey-light': '#FAFAFA',
      'perfect-scrollbar-thumb': '#DBDADE',
      'skin-bordered-background': '#fff',
      'skin-bordered-surface': '#fff',
      'sidebar-surface': staticDarkSurfaceColor,
      'on-sidebar-surface': staticDarkOnSurfaceColor,
      'expansion-panel-text-custom-bg': '#fafafa',
    },

    variables: {
      'code-color': '#d400ff',
      'overlay-scrim-background': '#22303E',
      'tooltip-background': '#22303E',
      'overlay-scrim-opacity': 0.5,
      'hover-opacity': 0.06,
      'focus-opacity': 0.1,
      'selected-opacity': 0.08,
      'activated-opacity': 0.16,
      'pressed-opacity': 0.14,
      'dragged-opacity': 0.1,
      'disabled-opacity': 0.4,
      'border-color': '#22303E',
      'border-opacity': 0.12,
      'table-header-color': '#FFFFFF',
      'high-emphasis-opacity': 0.9,
      'medium-emphasis-opacity': 0.7,
      'switch-opacity': 0.2,
      'switch-disabled-track-opacity': 0.3,
      'switch-disabled-thumb-opacity': 0.4,
      'switch-checked-disabled-opacity': 0.3,
      'track-bg': '#EEF1F3',
      'chat-bg': '#F7F8F8',
      // Shadows
      'shadow-key-umbra-color': '#22303E',
      'shadow-xs-opacity': 0.06,
      'shadow-sm-opacity': 0.06,
      'shadow-md-opacity': 0.06,
      'shadow-lg-opacity': 0.06,
      'shadow-xl-opacity': 0.06,
      'table-head-bg': '#fafafa',
      'on-table-head-bg': '#333333',
    },
  },
  dark: {
    dark: true,
    colors: {
      primary: staticPrimaryColor,
      'on-primary': '#fff',
      'primary-darken-1': staticPrimaryDarkenColor,
      'primary-light': '#8789FF',
      secondary: staticSecondaryColor,
      'on-secondary': '#fff',
      'secondary-darken-1': staticSecondaryColor,
      'secondary-light': staticSecondaryColor,
      success: '#71DD37',
      'on-success': '#fff',
      'success-darken-1': '#66C732',
      'success-light': '#53D28C',
      info: '#03C3EC',
      'on-info': '#fff',
      'info-darken-1': '#03AFD4',
      'info-light': '#35CFF0',
      warning: '#FFAB00',
      'on-warning': '#fff',
      'warning-darken-1': '#E69A00',
      'warning-light': '#FFBC33',
      error: '#FF3E1D',
      'on-error': '#fff',
      'error-darken-1': '#E6381A',
      'error-light': '#FF654A',
      background: '#232333',
      'on-background': staticDarkOnSurfaceColor,
      surface: staticDarkSurfaceColor,
      'on-surface': staticDarkOnSurfaceColor,
      'grey-50': '#26293A',
      'grey-100': '#2F3349',
      'grey-200': '#26293A',
      'grey-300': '#4A5072',
      'grey-400': '#5E6692',
      'grey-500': '#7983BB',
      'grey-600': '#AAB3DE',
      'grey-700': '#B6BEE3',
      'grey-800': '#CFD3EC',
      'grey-900': '#E7E9F6',
      'grey-light': '#313246',
      'perfect-scrollbar-thumb': '#4A5072',
      'skin-bordered-background': staticDarkSurfaceColor,
      'skin-bordered-surface': staticDarkSurfaceColor,
      'sidebar-surface': staticDarkSurfaceColor,
      'on-sidebar-surface': staticDarkOnSurfaceColor,
    },
    variables: {
      'code-color': '#d400ff',
      'overlay-scrim-background': '#1D1D2A',
      'tooltip-background': '#E6E6F1',
      'overlay-scrim-opacity': 0.6,
      'hover-opacity': 0.06,
      'focus-opacity': 0.1,
      'selected-opacity': 0.08,
      'activated-opacity': 0.16,
      'pressed-opacity': 0.14,
      'dragged-opacity': 0.1,
      'disabled-opacity': 0.4,
      'border-color': '#E6E6F1',
      'border-opacity': 0.12,
      'table-header-color': '#2B2C40',
      'high-emphasis-opacity': 0.9,
      'medium-emphasis-opacity': 0.7,
      'switch-opacity': 0.4,
      'switch-disabled-track-opacity': 0.4,
      'switch-disabled-thumb-opacity': 0.8,
      'switch-checked-disabled-opacity': 0.3,
      'track-bg': '#41415F',
      'chat-bg': '#20202E',

      // Shadows
      'shadow-key-umbra-color': '#14141D',
      'shadow-xs-opacity': 0.18,
      'shadow-sm-opacity': 0.2,
      'shadow-md-opacity': 0.22,
      'shadow-lg-opacity': 0.24,
      'shadow-xl-opacity': 0.26,

      'table-head-bg': '#1e1e1e',
      'on-table-head-bg': '#eeeeee',
    },
  },
}

export function getSystemThemeMode(): 'light' | 'dark' {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
    return 'light'
  }

  return window.matchMedia(SYSTEM_THEME_MEDIA_QUERY).matches ? 'dark' : 'light'
}

export function resolveThemeMode(mode: ThemeMode): 'light' | 'dark' {
  return mode === 'system' ? getSystemThemeMode() : mode
}

export function watchSystemThemeMode(
  callback: (mode: 'light' | 'dark') => void,
): (() => void) | undefined {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
    return undefined
  }

  const mediaQuery = window.matchMedia(SYSTEM_THEME_MEDIA_QUERY)
  const listener = () => callback(mediaQuery.matches ? 'dark' : 'light')

  if (typeof mediaQuery.addEventListener === 'function') {
    mediaQuery.addEventListener('change', listener)

    return () => mediaQuery.removeEventListener('change', listener)
  }

  mediaQuery.addListener(listener)

  return () => mediaQuery.removeListener(listener)
}

export function applyThemeSettings(
  theme: ThemeController,
  settings: AppSettings,
  mode: ThemeMode = settings.default_theme_mode,
): void {
  applyAppearanceColors(theme.themes.value.light?.colors, {
    primary: settings.theme_primary_color,
    secondary: settings.theme_secondary_color,
    success: settings.theme_success_color,
    info: settings.theme_info_color,
    warning: settings.theme_warning_color,
    error: settings.theme_error_color,
  })

  applyAppearanceColors(theme.themes.value.dark?.colors, {
    primary: settings.theme_dark_primary_color || settings.theme_primary_color,
    secondary: settings.theme_dark_secondary_color || settings.theme_secondary_color,
    success: settings.theme_dark_success_color || settings.theme_success_color,
    info: settings.theme_dark_info_color || settings.theme_info_color,
    warning: settings.theme_dark_warning_color || settings.theme_warning_color,
    error: settings.theme_dark_error_color || settings.theme_error_color,
  })

  theme.change(resolveThemeMode(mode))
}

export default themes

function applyAppearanceColors(
  colors: Record<string, string> | undefined,
  appearanceColors: Record<
    'primary' | 'secondary' | 'success' | 'info' | 'warning' | 'error',
    string
  >,
): void {
  if (!colors) {
    return
  }

  colors.primary = appearanceColors.primary
  colors['primary-darken-1'] = appearanceColors.primary
  colors.secondary = appearanceColors.secondary
  colors['secondary-darken-1'] = appearanceColors.secondary
  colors['secondary-light'] = appearanceColors.secondary
  colors.success = appearanceColors.success
  colors['success-darken-1'] = appearanceColors.success
  colors.info = appearanceColors.info
  colors['info-darken-1'] = appearanceColors.info
  colors.warning = appearanceColors.warning
  colors['warning-darken-1'] = appearanceColors.warning
  colors.error = appearanceColors.error
  colors['error-darken-1'] = appearanceColors.error
}

type ThemeController = {
  themes: {
    value: Record<string, { colors: Record<string, string> }>
  }
  change: (name: string) => void
}
