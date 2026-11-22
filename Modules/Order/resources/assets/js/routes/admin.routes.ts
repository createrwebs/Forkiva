import type { RouteRecordRaw } from 'vue-router'

const adminRoutes: RouteRecordRaw[] = [
  {
    path: 'orders',
    name: 'admin.orders',
    meta: {
      title: 'admin::sidebar.orders',
      icon: 'tabler-salad',
    },
    children: [
      {
        path: '',
        name: 'admin.orders.index',
        component: () => import('@/modules/order/pages/admin/order/Index.vue'),
        meta: {
          permission: 'admin.orders.index',
        },
      },
      {
        path: ':id/show',
        name: 'admin.orders.show',
        component: () => import('@/modules/order/pages/admin/order/Show.vue'),
        meta: {
          title: 'admin::resource.show',
          transParam: { resource: 'order::orders.order' },
          permission: 'admin.orders.show',
        },
      },
    ],
  },
  {
    path: 'reasons',
    name: 'admin.reasons',
    meta: {
      title: 'admin::sidebar.reasons',
      icon: 'tabler-ban',
    },
    children: [
      {
        path: '',
        name: 'admin.reasons.index',
        component: () => import('@/modules/order/pages/admin/reason/Index.vue'),
        meta: {
          permission: 'admin.reasons.index',
        },
      },
      {
        path: 'create',
        name: 'admin.reasons.create',
        component: () => import('@/modules/order/pages/admin/reason/Create.vue'),
        meta: {
          title: 'admin::resource.create',
          transParam: { resource: 'order::reasons.reason' },
          permission: 'admin.reasons.create',
        },
      },
      {
        path: ':id/edit',
        name: 'admin.reasons.edit',
        component: () => import('@/modules/order/pages/admin/reason/Edit.vue'),
        meta: {
          title: 'admin::resource.edit',
          transParam: { resource: 'order::reasons.reason' },
          permission: 'admin.reasons.edit',
        },
      },
    ],
  },
]

export default adminRoutes
