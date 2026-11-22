export interface ServiceFieldOption {
  id: string
  name: string
  icon?: string
  color?: string
}

export interface ServiceField {
  key: string
  label: string
  type: 'boolean' | 'string' | 'textarea' | 'integer' | 'dropdown'
  default?: unknown
  value?: unknown
  value_option?: ServiceFieldOption
  validation?: string
  options?: ServiceFieldOption[]
  is_sensitive?: boolean
  conditional_fields?: Record<string, ServiceField[]>
}

export interface ServiceItem {
  key: string
  original_key: string
  category: string
  name: string
  name_key?: string
  description?: string | null
  description_key?: string | null
  icon?: string | null
  color?: string | null
  order: number
  values: Record<string, any>
  settings: ServiceField[]
}

export interface ServiceCategory {
  key: string
  label: string
  label_key?: string
  description?: string | null
  description_key?: string | null
  icon?: string | null
  order: number
  services: ServiceItem[]
}
