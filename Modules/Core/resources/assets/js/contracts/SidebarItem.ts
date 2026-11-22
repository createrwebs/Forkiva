import type { RouteLocationRaw } from 'vue-router'
import type { IconValue } from 'vuetify/lib/composables/icons.js'
import type { TargetName } from '@/modules/core/contracts/Target.ts'
import type { ATagRelAttrValues, ATagTargetAttrValues } from '@/modules/core/contracts/Theme.ts'

export interface SidebarItem {
  key: string
  label: string
  workspace?: string
  to?: RouteLocationRaw | string | null
  exact?: boolean
  href?: string
  target?: ATagTargetAttrValues
  rel?: ATagRelAttrValues
  parentKey?: string
  icon?: IconValue | undefined
  isHeading?: boolean
  permission?: string[] | string
  role?: string[] | string
  badgeContent?: string
  badgeClass?: string
  disable?: boolean
  visible?: boolean | (() => boolean)
  sort?: number
  children?: SidebarItem[]
}

export interface SidebarList {
  target: TargetName
  workspace?: string
  items: SidebarItem[]
}
