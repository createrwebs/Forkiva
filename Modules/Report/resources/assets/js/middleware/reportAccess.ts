import type {
  NavigationGuardNext,
  RouteLocationNormalized,
  RouteLocationNormalizedLoaded,
} from 'vue-router'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import { reports } from '@/modules/report/pages/admin/report/reports.ts'
import { useReport } from '../composables/report.ts'

export function reportAccessMiddleware(
  to: RouteLocationNormalized,
  from: RouteLocationNormalizedLoaded,
  next: NavigationGuardNext,
) {
  const { can } = useAuth()
  const { getReportIcon, reportExists } = useReport()

  const reportKey: string = (to.params as Record<string, any>).key
  const firstAccessibleReport = Object.values(reports)
    .flat()
    .find(({ key }) => can(`admin.reports.${key}`))

  if (reportExists(reportKey) && can(`admin.reports.${reportKey}`)) {
    to.meta.title = `report::reports.definitions.${reportKey}.title`
    to.meta.icon = getReportIcon(reportKey)
    next()
    return
  }

  next(
    firstAccessibleReport
      ? { name: 'admin.reports.show', params: { key: firstAccessibleReport.key } }
      : { name: 'admin.dashboard' },
  )
}
