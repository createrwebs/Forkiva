import type { RouteRecordRaw } from 'vue-router'

const adminRoutes: RouteRecordRaw[] = [
  {
    path: 'vouchers',
    name: 'admin.vouchers',
    meta: {
      title: 'admin::sidebar.vouchers',
      icon: 'tabler-ticket',
    },
    children: [
      {
        path: '',
        name: 'admin.vouchers.index',
        component: () => import('@/modules/voucher/pages/admin/voucher/Index.vue'),
        meta: {
          permission: 'admin.vouchers.index',
        },
      },
      {
        path: 'create',
        name: 'admin.vouchers.create',
        component: () => import('@/modules/voucher/pages/admin/voucher/Create.vue'),
        meta: {
          title: 'admin::resource.create',
          transParam: { resource: 'voucher::vouchers.voucher' },
          permission: 'admin.vouchers.create',
        },
      },
      {
        path: ':id/edit',
        name: 'admin.vouchers.edit',
        component: () => import('@/modules/voucher/pages/admin/voucher/Edit.vue'),
        meta: {
          title: 'admin::resource.edit',
          transParam: { resource: 'voucher::vouchers.voucher' },
          permission: 'admin.vouchers.edit',
        },
      },
    ],
  },
]

export default adminRoutes
