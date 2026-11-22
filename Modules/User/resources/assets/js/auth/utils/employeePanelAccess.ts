import type { User } from '@/modules/auth/contracts/Auth.ts'

export function hasEmployeePanelAccess(user?: User | null): boolean {
  if (!user?.employee?.id || !user.employee.is_active) {
    return false
  }

  if (!user.employee.termination_date) {
    return true
  }

  const today = new Date()
  today.setHours(0, 0, 0, 0)

  const terminationDate = new Date(user.employee.termination_date)

  if (Number.isNaN(terminationDate.getTime())) {
    return false
  }

  terminationDate.setHours(0, 0, 0, 0)

  return terminationDate > today
}
