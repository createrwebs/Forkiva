import type { RouteRecordRaw } from 'vue-router'

const adminRoutes: RouteRecordRaw[] = [
  {
    path: 'invoices',
    name: 'admin.invoices',
    meta: {
      title: 'admin::sidebar.invoices',
      icon: 'tabler-file-invoice',
    },
    children: [
      {
        path: '',
        name: 'admin.invoices.index',
        component: () => import('@/modules/invoice/pages/admin/invoice/Index.vue'),
        meta: {
          permission: 'admin.invoices.index',
        },
      },
      {
        path: ':id/show',
        name: 'admin.invoices.show',
        component: () => import('@/modules/invoice/pages/admin/invoice/Show.vue'),
        meta: {
          title: 'admin::resource.show',
          transParam: { resource: 'invoice::invoices.invoice' },
          permission: 'admin.invoices.show',
        },
      },
    ],
  },
]

export default adminRoutes
