import type { RouteRecordRaw } from 'vue-router'

const adminRoutes: RouteRecordRaw[] = [
  {
    path: 'settings',
    name: 'admin.settings.edit',
    redirect: { name: 'admin.settings.general' },
    component: () => import('@/modules/setting/pages/admin/Layout.vue'),
    meta: { pageHeader: false },
    children: [
      {
        path: 'password-recovery',
        name: 'admin.settings.password_recovery',
        component: () => import('@/modules/setting/pages/admin/setting/PasswordRecovery.vue'),
        meta: {
          title: 'setting::settings.sections.password_recovery',
          permission: 'admin.settings.edit',
        },
      },
      {
        path: 'general',
        name: 'admin.settings.general',
        component: () => import('@/modules/setting/pages/admin/setting/General.vue'),
        meta: {
          title: 'setting::settings.sections.general',
          permission: 'admin.settings.edit',
        },
      },
      {
        path: 'table-qr-ordering',
        name: 'admin.settings.table_qr_ordering',
        component: () => import('@/modules/setting/pages/admin/setting/TableQrOrdering.vue'),
        meta: {
          title: 'setting::settings.sections.table_qr_ordering',
          permission: 'admin.settings.edit',
        },
      },
      {
        path: 'application',
        name: 'admin.settings.application',
        component: () => import('@/modules/setting/pages/admin/setting/Application.vue'),
        meta: {
          title: 'setting::settings.sections.application',
          permission: 'admin.settings.edit',
        },
      },
      {
        path: 'logo',
        name: 'admin.settings.logo',
        component: () => import('@/modules/setting/pages/admin/setting/Logo.vue'),
        meta: {
          title: 'setting::settings.sections.logo',
          permission: 'admin.settings.edit',
        },
      },
      {
        path: 'appearance',
        name: 'admin.settings.appearance',
        component: () => import('@/modules/setting/pages/admin/setting/Appearance.vue'),
        meta: {
          title: 'setting::settings.sections.appearance',
          permission: 'admin.settings.edit',
        },
      },
      {
        path: 'pwa',
        name: 'admin.settings.pwa',
        component: () => import('@/modules/setting/pages/admin/setting/Pwa.vue'),
        meta: {
          title: 'setting::settings.sections.pwa',
          permission: 'admin.settings.edit',
        },
      },
      {
        path: 'passkeys',
        name: 'admin.settings.passkeys',
        component: () => import('@/modules/setting/pages/admin/setting/Passkeys.vue'),
        meta: {
          title: 'setting::settings.sections.passkeys',
          permission: 'admin.settings.edit',
        },
      },
      {
        path: 'two-factor',
        name: 'admin.settings.two_factor',
        component: () => import('@/modules/setting/pages/admin/setting/TwoFactor.vue'),
        meta: {
          title: 'setting::settings.sections.two_factor',
          permission: 'admin.settings.edit',
        },
      },
      {
        path: 'pos',
        name: 'admin.settings.pos',
        component: () => import('@/modules/setting/pages/admin/setting/Pos.vue'),
        meta: {
          title: 'setting::settings.sections.pos',
          permission: 'admin.settings.edit',
        },
      },
      {
        path: 'kitchen',
        name: 'admin.settings.kitchen',
        component: () => import('@/modules/setting/pages/admin/setting/Kitchen.vue'),
        meta: {
          title: 'setting::settings.sections.kitchen',
          permission: 'admin.settings.edit',
        },
      },
      {
        path: 'currency',
        name: 'admin.settings.currency',
        component: () => import('@/modules/setting/pages/admin/setting/Currency.vue'),
        meta: {
          title: 'setting::settings.sections.currency',
          permission: 'admin.settings.edit',
        },
      },
      {
        path: 'filesystem',
        name: 'admin.settings.filesystem',
        component: () => import('@/modules/setting/pages/admin/setting/Filesystem.vue'),
        meta: {
          title: 'setting::settings.sections.filesystem',
          permission: 'admin.settings.edit',
        },
      },
      {
        path: 'pdf',
        name: 'admin.settings.pdf',
        component: () => import('@/modules/pdf/pages/admin/PdfSettings.vue'),
        meta: {
          title: 'setting::settings.sections.pdf',
          permission: 'admin.settings.edit',
        },
      },
    ],
  },
]

export default adminRoutes
