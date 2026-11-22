import type { RouteRecordRaw } from 'vue-router'

const adminRoutes: RouteRecordRaw[] = [
  {
    path: 'notifications',
    name: 'admin.notifications',
    meta: {
      title: 'notification::notifications.notification_center',
      icon: 'tabler-bell',
    },
    children: [
      {
        path: '',
        name: 'admin.notifications.index',
        component: () => import('@/modules/notification/pages/admin/notificationCenter/Index.vue'),
        meta: {
          // The page watches these query values and updates without remounting.
          reuseOnQueryChange: ['selected', 'status', 'search'],
        },
      },
    ],
  },
  {
    path: 'notification-settings',
    name: 'admin.notification_settings',
    meta: {
      title: 'notification::notification_workflows.notification_settings',
      icon: 'tabler-bell-cog',
    },
    children: [
      {
        path: '',
        name: 'admin.notification_settings.index',
        component: () =>
          import('@/modules/notification/pages/admin/notificationWorkflow/Index.vue'),
        meta: {
          permission: 'admin.notification_workflows.index',
        },
      },
      {
        path: ':key/edit',
        name: 'admin.notification_settings.edit',
        component: () => import('@/modules/notification/pages/admin/notificationWorkflow/Edit.vue'),
        meta: {
          title: 'admin::resource.edit',
          transParam: {
            resource: 'notification::notification_workflows.notification_setting',
          },
          permission: 'admin.notification_workflows.edit',
        },
      },
    ],
  },
  {
    path: 'notification-templates',
    name: 'admin.notification_templates',
    meta: {
      title: 'notification::notification_templates.notification_templates',
      icon: 'tabler-message-cog',
    },
    children: [
      {
        path: '',
        name: 'admin.notification_templates.index',
        component: () =>
          import('@/modules/notification/pages/admin/notificationTemplate/Index.vue'),
        meta: {
          permission: 'admin.notification_templates.index',
        },
      },
      {
        path: ':key/edit',
        name: 'admin.notification_templates.edit',
        component: () => import('@/modules/notification/pages/admin/notificationTemplate/Edit.vue'),
        meta: {
          title: 'admin::resource.edit',
          transParam: {
            resource: 'notification::notification_templates.notification_template',
          },
          permission: 'admin.notification_templates.edit',
        },
      },
    ],
  },
]

export default adminRoutes
