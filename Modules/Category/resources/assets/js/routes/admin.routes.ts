import type { RouteRecordRaw } from 'vue-router'

const adminRoutes: RouteRecordRaw[] = [
  {
    path: 'categories',
    name: 'admin.categories',
    meta: {
      title: 'admin::sidebar.categories',
      icon: 'tabler-folders',
    },
    children: [
      {
        path: '',
        name: 'admin.categories.index',
        component: () => import('@/modules/category/pages/admin/category/Index.vue'),
        meta: {
          permission: 'admin.categories.index',
        },
      },
    ],
  },
]

export default adminRoutes
