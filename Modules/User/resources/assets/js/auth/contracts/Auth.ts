import type { Money } from '@/modules/core/contracts/Money'

interface UserRole {
  name: string
  display_name: string
  permissions: string[]
}

interface EmployeeSummaryUnit {
  id: number | null
  name: string
  currency?: string | null
}

interface DriverStatusSummary {
  id: string
  name: string
  color?: string
  icon?: string
}

export interface LinkedDeliveryDriverSummary {
  id: number
  status: DriverStatusSummary | null
  vehicle_label: string | null
  vehicle_plate_number: string | null
  supports_cod: boolean
  cash_limit_amount?: Money | null
  branch: EmployeeSummaryUnit | null
  employee: {
    id: number
    name: string
    code: string
    phone: string | null
    user_id: number | null
  } | null
  is_active: boolean
}

export interface LinkedEmployeeSummary {
  id: number
  code: string
  branch: EmployeeSummaryUnit | null
  department: EmployeeSummaryUnit | null
  designation: EmployeeSummaryUnit | null
  manager_id: number | null
  is_manager: boolean
  is_active: boolean
  termination_date: string | null
}

export interface User {
  id: number
  name: string
  profile_photo_url: string
  username: string
  email: string
  branch_id: null | number
  role: UserRole
  assigned_to_branch: boolean
  employee?: LinkedEmployeeSummary | null
  delivery_driver?: LinkedDeliveryDriverSummary | null
}

export interface Auth {
  user: User | null
  token: string | null
}

export interface AuthAccount {
  user: User
  token: string
}

export interface AuthState extends Auth {
  accounts: AuthAccount[]
  activeAccountId: number | null
}
