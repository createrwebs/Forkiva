import type { User } from '@/modules/auth/contracts/Auth.ts'

export function hasDriverPanelAccess(user?: User | null): boolean {
  return !!(user?.delivery_driver?.id && user.delivery_driver.is_active)
}
