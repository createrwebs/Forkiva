import type { RouteRecordRaw } from 'vue-router'
import menuCategoryRoutes from '@/modules/category/routes/menu.routes.ts'
import menuProductRoutes from '@/modules/product/routes/menu.routes.ts'

const adminRoutes: RouteRecordRaw[] = [
  {
    path: 'menus',
    name: 'admin.menus',
    meta: {
      title: 'menu::menus.menus',
      icon: 'tabler-list-details',
    },
    children: [
      {
        path: '',
        name: 'admin.menus.index',
        component: () => import('@/modules/menu/pages/admin/menu/Index.vue'),
        meta: {
          permission: 'admin.menus.index',
        },
      },
      {
        path: 'create',
        name: 'admin.menus.create',
        component: () => import('@/modules/menu/pages/admin/menu/Create.vue'),
        meta: {
          title: 'admin::resource.create',
          transParam: { resource: 'menu::menus.menu' },
          permission: 'admin.menus.create',
        },
      },
      {
        path: ':id/edit',
        name: 'admin.menus.edit',
        component: () => import('@/modules/menu/pages/admin/menu/Edit.vue'),
        meta: {
          title: 'admin::resource.edit',
          transParam: { resource: 'menu::menus.menu' },
          permission: 'admin.menus.edit',
        },
      },
      ...menuCategoryRoutes,
      ...menuProductRoutes,
    ],
  },
  {
    path: 'online-menus',
    name: 'admin.online_menus',
    meta: {
      title: 'menu::online_menus.online_menus',
      icon: 'tabler-link',
    },
    children: [
      {
        path: '',
        name: 'admin.online_menus.index',
        component: () => import('@/modules/menu/pages/admin/onlineMenu/Index.vue'),
        meta: {
          permission: 'admin.online_menus.index',
        },
      },
      {
        path: 'create',
        name: 'admin.online_menus.create',
        component: () => import('@/modules/menu/pages/admin/onlineMenu/Create.vue'),
        meta: {
          title: 'admin::resource.create',
          transParam: { resource: 'menu::online_menus.online_menu' },
          permission: 'admin.online_menus.create',
        },
      },
      {
        path: ':id/edit',
        name: 'admin.online_menus.edit',
        component: () => import('@/modules/menu/pages/admin/onlineMenu/Edit.vue'),
        meta: {
          title: 'admin::resource.edit',
          transParam: { resource: 'menu::online_menus.online_menu' },
          permission: 'admin.online_menus.edit',
        },
      },
    ],
  },
]

export default adminRoutes
