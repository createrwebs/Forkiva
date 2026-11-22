import type { RouteRecordRaw } from 'vue-router'

const adminRoutes: RouteRecordRaw[] = [
  {
    path: 'products',
    name: 'admin.products',
    meta: {
      title: 'admin::sidebar.products',
      icon: 'tabler-package',
    },
    children: [
      {
        path: '',
        name: 'admin.products.index',
        component: () => import('@/modules/product/pages/admin/product/Index.vue'),
        meta: {
          permission: 'admin.products.index',
        },
      },
      {
        path: 'create',
        name: 'admin.products.create',
        component: () => import('@/modules/product/pages/admin/product/Create.vue'),
        meta: {
          title: 'admin::resource.create',
          transParam: { resource: 'product::products.product' },
          permission: 'admin.products.create',
        },
      },
      {
        path: ':id/edit',
        name: 'admin.products.edit',
        component: () => import('@/modules/product/pages/admin/product/Edit.vue'),
        meta: {
          title: 'admin::resource.edit',
          transParam: { resource: 'product::products.product' },
          permission: 'admin.products.edit',
        },
      },
    ],
  },
]

export default adminRoutes
