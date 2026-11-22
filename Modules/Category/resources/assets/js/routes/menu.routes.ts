import type { RouteRecordRaw } from 'vue-router'

const menuCategoryRoutes: RouteRecordRaw[] = [
  {
    path: ':id/categories',
    name: 'admin.menus.categories',
    component: () => import('@/modules/category/pages/admin/category/Index.vue'),
    meta: {
      title: 'admin::sidebar.categories',
      icon: 'tabler-folders',
      permission: 'admin.categories.index',
    },
  },
]

export default menuCategoryRoutes
