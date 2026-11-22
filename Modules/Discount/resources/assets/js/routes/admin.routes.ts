import type { RouteRecordRaw } from 'vue-router'

const adminRoutes: RouteRecordRaw[] = [
  {
    path: 'discounts',
    name: 'admin.discounts',
    meta: {
      title: 'admin::sidebar.discounts',
      icon: 'tabler-shopping-bag-discount',
    },
    children: [
      {
        path: '',
        name: 'admin.discounts.index',
        component: () => import('@/modules/discount/pages/admin/discount/Index.vue'),
        meta: {
          permission: 'admin.discounts.index',
        },
      },
      {
        path: 'create',
        name: 'admin.discounts.create',
        component: () => import('@/modules/discount/pages/admin/discount/Create.vue'),
        meta: {
          title: 'admin::resource.create',
          transParam: { resource: 'discount::discounts.discount' },
          permission: 'admin.discounts.create',
        },
      },
      {
        path: ':id/edit',
        name: 'admin.discounts.edit',
        component: () => import('@/modules/discount/pages/admin/discount/Edit.vue'),
        meta: {
          title: 'admin::resource.edit',
          transParam: { resource: 'discount::discounts.discount' },
          permission: 'admin.discounts.edit',
        },
      },
    ],
  },
]

export default adminRoutes
