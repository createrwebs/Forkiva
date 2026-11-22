import type { RouteRecordRaw } from 'vue-router'

const adminRoutes: RouteRecordRaw[] = [
  {
    path: 'services',
    name: 'admin.services.index',
    component: () => import('@/modules/service/pages/admin/service/Index.vue'),
    meta: {
      title: 'service::services.services',
      icon: 'tabler-plug-connected',
      permission: 'admin.services.index',
    },
  },
]

export default adminRoutes
