import type { RouteRecordRaw } from 'vue-router'

const adminRoutes: RouteRecordRaw[] = [
  {
    path: 'payments',
    name: 'admin.payments',
    meta: {
      title: 'admin::sidebar.payments',
      icon: 'tabler-receipt-dollar',
    },
    children: [
      {
        path: '',
        name: 'admin.payments.index',
        component: () => import('@/modules/payment/pages/admin/payment/Index.vue'),
        meta: {
          permission: 'admin.payments.index',
        },
      },
    ],
  },
]

export default adminRoutes
