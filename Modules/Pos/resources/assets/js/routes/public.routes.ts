import type { RouteRecordRaw } from 'vue-router'

export default [
  {
    path: '/customer-display',
    name: 'public.pos.customer_display',
    component: () => import('@/modules/pos/pages/admin/Customer/Index.vue'),
    meta: { target: 'blank', title: 'pos::pos_viewer.customer_viewer' },
  },
] satisfies RouteRecordRaw[]
