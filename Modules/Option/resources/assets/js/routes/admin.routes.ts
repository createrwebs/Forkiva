import type { RouteRecordRaw } from 'vue-router'

const adminRoutes: RouteRecordRaw[] = [
  {
    path: 'options',
    name: 'admin.options',
    meta: {
      title: 'admin::sidebar.options',
      icon: 'tabler-adjustments',
    },
    children: [
      {
        path: '',
        name: 'admin.options.index',
        component: () => import('@/modules/option/pages/admin/option/Index.vue'),
        meta: {
          permission: 'admin.options.index',
        },
      },
      {
        path: 'create',
        name: 'admin.options.create',
        component: () => import('@/modules/option/pages/admin/option/Create.vue'),
        meta: {
          title: 'admin::resource.create',
          transParam: { resource: 'option::options.option' },
          permission: 'admin.options.create',
        },
      },
      {
        path: ':id/edit',
        name: 'admin.options.edit',
        component: () => import('@/modules/option/pages/admin/option/Edit.vue'),
        meta: {
          title: 'admin::resource.edit',
          transParam: { resource: 'option::options.option' },
          permission: 'admin.options.edit',
        },
      },
    ],
  },
]

export default adminRoutes
