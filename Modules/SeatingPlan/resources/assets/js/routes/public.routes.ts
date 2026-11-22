import type { RouteRecordRaw } from 'vue-router'

const publicRoutes: RouteRecordRaw[] = [
  {
    path: '/table-order/:uuid',
    name: 'public.table_order.index',
    component: () => import('@/modules/seatingPlan/pages/public/tableOrder/Index.vue'),
    meta: {
      target: 'public',
    },
  },
]

export default publicRoutes
