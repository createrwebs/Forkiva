import type { RouteRecordRaw } from 'vue-router'
import { reportAccessMiddleware } from '@/modules/report/middleware/reportAccess.ts'

const adminRoutes: RouteRecordRaw[] = [
  {
    path: 'reports',
    name: 'admin.reports',
    beforeEnter: reportAccessMiddleware,
    meta: {
      title: 'admin::sidebar.reports',
      icon: 'tabler-chart-histogram',
      workspace: 'reports',
    },
    children: [
      {
        path: ':key',
        name: 'admin.reports.show',
        component: () => import('@/modules/report/pages/admin/report/Show.vue'),
        meta: {
          permission: 'admin.reports.index',
        },
      },
    ],
  },
]

export default adminRoutes
