import type { RouteRecordRaw } from 'vue-router'

const adminRoutes: RouteRecordRaw[] = [
  {
    path: 'daily-finance',
    name: 'admin.daily_finance.index',
    component: () => import('@/modules/dailyfinance/pages/admin/dailyFinance/Index.vue'),
    meta: {
      title: 'dailyfinance::daily_finance.title',
      pageHeader: false,
      permission: 'admin.daily_finance.index',
    },
  },
]

export default adminRoutes
