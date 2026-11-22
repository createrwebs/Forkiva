import type { RouteRecordRaw } from 'vue-router'

const adminRoutes: RouteRecordRaw[] = [
  {
    path: 'reservations',
    name: 'admin.reservations',
    meta: {
      title: 'reservation::sidebar.reservations',
      icon: 'tabler-calendar-event',
    },
    children: [
      {
        path: '',
        name: 'admin.reservations.index',
        component: () => import('@/modules/reservation/pages/admin/reservation/Index.vue'),
        meta: { permission: 'admin.reservations.index' },
      },
      {
        path: 'create',
        name: 'admin.reservations.create',
        component: () => import('@/modules/reservation/pages/admin/reservation/Create.vue'),
        meta: {
          title: 'admin::resource.create',
          transParam: { resource: 'reservation::reservations.reservation' },
          permission: 'admin.reservations.create',
        },
      },
      {
        path: 'calendar',
        name: 'admin.reservations.calendar',
        component: () => import('@/modules/reservation/pages/admin/calendar/Index.vue'),
        meta: {
          title: 'reservation::sidebar.reservation_calendar',
          icon: 'tabler-calendar',
          permission: 'admin.reservations.index',
        },
      },
      {
        path: ':id',
        name: 'admin.reservations.show',
        component: () => import('@/modules/reservation/pages/admin/reservation/Show.vue'),
        meta: {
          title: 'admin::resource.show',
          transParam: { resource: 'reservation::reservations.reservation' },
          permission: 'admin.reservations.show',
        },
      },
      {
        path: ':id/edit',
        name: 'admin.reservations.edit',
        component: () => import('@/modules/reservation/pages/admin/reservation/Edit.vue'),
        meta: {
          title: 'admin::resource.edit',
          transParam: { resource: 'reservation::reservations.reservation' },
          permission: 'admin.reservations.edit',
        },
      },
    ],
  },
  {
    path: 'reservation-analytics',
    name: 'admin.reservation_analytics.index',
    component: () => import('@/modules/reservation/pages/admin/analytics/Index.vue'),
    meta: {
      title: 'reservation::sidebar.analytics',
      icon: 'tabler-chart-pie',
      pageHeader: false,
      permission: 'admin.reservations.analytics',
    },
  },
  {
    path: 'reservation-settings',
    name: 'admin.reservation_settings',
    meta: {
      title: 'reservation::sidebar.reservation_settings',
      icon: 'tabler-settings',
      workspace: 'settings',
    },
    children: [
      {
        path: '',
        name: 'admin.reservation_settings.index',
        component: () => import('@/modules/reservation/pages/admin/setting/Index.vue'),
        meta: { permission: 'admin.reservation_settings.index' },
      },
      {
        path: 'create',
        name: 'admin.reservation_settings.create',
        component: () => import('@/modules/reservation/pages/admin/setting/Create.vue'),
        meta: {
          title: 'admin::resource.create',
          transParam: {
            resource: 'reservation::reservation_settings.reservation_setting',
          },
          permission: 'admin.reservation_settings.create',
        },
      },
      {
        path: ':id/edit',
        name: 'admin.reservation_settings.edit',
        component: () => import('@/modules/reservation/pages/admin/setting/Edit.vue'),
        meta: {
          title: 'admin::resource.edit',
          transParam: {
            resource: 'reservation::reservation_settings.reservation_setting',
          },
          permission: 'admin.reservation_settings.edit',
        },
      },
    ],
  },
  {
    path: 'reservation-waitlists',
    name: 'admin.reservation_waitlists',
    meta: {
      title: 'reservation::sidebar.reservation_waitlists',
      icon: 'tabler-hourglass',
    },
    children: [
      {
        path: '',
        name: 'admin.reservation_waitlists.index',
        component: () => import('@/modules/reservation/pages/admin/waitlist/Index.vue'),
        meta: { permission: 'admin.reservation_waitlists.index' },
      },
      {
        path: 'create',
        name: 'admin.reservation_waitlists.create',
        component: () => import('@/modules/reservation/pages/admin/waitlist/Create.vue'),
        meta: {
          title: 'admin::resource.create',
          transParam: {
            resource: 'reservation::reservation_waitlists.reservation_waitlist',
          },
          permission: 'admin.reservation_waitlists.create',
        },
      },
      {
        path: ':id',
        name: 'admin.reservation_waitlists.show',
        component: () => import('@/modules/reservation/pages/admin/waitlist/Show.vue'),
        meta: {
          title: 'admin::resource.show',
          transParam: {
            resource: 'reservation::reservation_waitlists.reservation_waitlist',
          },
          permission: 'admin.reservation_waitlists.show',
        },
      },
      {
        path: ':id/edit',
        name: 'admin.reservation_waitlists.edit',
        component: () => import('@/modules/reservation/pages/admin/waitlist/Edit.vue'),
        meta: {
          title: 'admin::resource.edit',
          transParam: {
            resource: 'reservation::reservation_waitlists.reservation_waitlist',
          },
          permission: 'admin.reservation_waitlists.edit',
        },
      },
    ],
  },
  {
    path: 'reservation-table-blocks',
    name: 'admin.reservation_table_blocks',
    meta: {
      title: 'reservation::sidebar.reservation_table_blocks',
      icon: 'tabler-calendar-off',
    },
    children: [
      {
        path: '',
        name: 'admin.reservation_table_blocks.index',
        component: () => import('@/modules/reservation/pages/admin/tableBlock/Index.vue'),
        meta: { permission: 'admin.reservation_table_blocks.index' },
      },
      {
        path: 'create',
        name: 'admin.reservation_table_blocks.create',
        component: () => import('@/modules/reservation/pages/admin/tableBlock/Create.vue'),
        meta: {
          title: 'admin::resource.create',
          transParam: {
            resource: 'reservation::reservation_table_blocks.reservation_table_block',
          },
          permission: 'admin.reservation_table_blocks.create',
        },
      },
      {
        path: ':id/edit',
        name: 'admin.reservation_table_blocks.edit',
        component: () => import('@/modules/reservation/pages/admin/tableBlock/Edit.vue'),
        meta: {
          title: 'admin::resource.edit',
          transParam: {
            resource: 'reservation::reservation_table_blocks.reservation_table_block',
          },
          permission: 'admin.reservation_table_blocks.edit',
        },
      },
    ],
  },
]

export default adminRoutes
