import type { RouteRecordRaw } from 'vue-router'

const adminRoutes: RouteRecordRaw[] = [
  {
    path: 'inventories/analytics',
    name: 'admin.inventories.analytics',
    component: () => import('@/modules/inventory/pages/admin/analytics/Index.vue'),
    meta: {
      title: 'admin::sidebar.inventory_analytics',
      icon: 'tabler-brand-google-analytics',
      permission: 'admin.inventories.analytics',
      pageHeader: false,
    },
  },
  {
    path: 'units',
    name: 'admin.units',
    meta: {
      title: 'admin::sidebar.units',
      icon: 'tabler-ruler',
    },
    children: [
      {
        path: '',
        name: 'admin.units.index',
        component: () => import('@/modules/inventory/pages/admin/unit/Index.vue'),
        meta: {
          permission: 'admin.units.index',
        },
      },
      {
        path: 'create',
        name: 'admin.units.create',
        component: () => import('@/modules/inventory/pages/admin/unit/Create.vue'),
        meta: {
          title: 'admin::resource.create',
          transParam: { resource: 'inventory::units.unit' },
          permission: 'admin.units.create',
        },
      },
      {
        path: ':id/edit',
        name: 'admin.units.edit',
        component: () => import('@/modules/inventory/pages/admin/unit/Edit.vue'),
        meta: {
          title: 'admin::resource.edit',
          transParam: { resource: 'inventory::units.unit' },
          permission: 'admin.units.edit',
        },
      },
    ],
  },
  {
    path: 'suppliers',
    name: 'admin.suppliers',
    meta: {
      title: 'admin::sidebar.suppliers',
      icon: 'tabler-truck-loading',
    },
    children: [
      {
        path: '',
        name: 'admin.suppliers.index',
        component: () => import('@/modules/inventory/pages/admin/supplier/Index.vue'),
        meta: {
          permission: 'admin.suppliers.index',
        },
      },
      {
        path: 'create',
        name: 'admin.suppliers.create',
        component: () => import('@/modules/inventory/pages/admin/supplier/Create.vue'),
        meta: {
          title: 'admin::resource.create',
          transParam: { resource: 'inventory::suppliers.supplier' },
          permission: 'admin.suppliers.create',
        },
      },
      {
        path: ':id/edit',
        name: 'admin.suppliers.edit',
        component: () => import('@/modules/inventory/pages/admin/supplier/Edit.vue'),
        meta: {
          title: 'admin::resource.edit',
          transParam: { resource: 'inventory::suppliers.supplier' },
          permission: 'admin.suppliers.edit',
        },
      },
    ],
  },
  {
    path: 'ingredients',
    name: 'admin.ingredients',
    meta: {
      title: 'admin::sidebar.ingredients',
      icon: 'ic-outline-fastfood',
    },
    children: [
      {
        path: '',
        name: 'admin.ingredients.index',
        component: () => import('@/modules/inventory/pages/admin/ingredient/Index.vue'),
        meta: {
          permission: 'admin.ingredients.index',
        },
      },
      {
        path: 'create',
        name: 'admin.ingredients.create',
        component: () => import('@/modules/inventory/pages/admin/ingredient/Create.vue'),
        meta: {
          title: 'admin::resource.create',
          transParam: { resource: 'inventory::ingredients.ingredient' },
          permission: 'admin.ingredients.create',
        },
      },
      {
        path: ':id/edit',
        name: 'admin.ingredients.edit',
        component: () => import('@/modules/inventory/pages/admin/ingredient/Edit.vue'),
        meta: {
          title: 'admin::resource.edit',
          transParam: { resource: 'inventory::ingredients.ingredient' },
          permission: 'admin.ingredients.edit',
        },
      },
    ],
  },
  {
    path: 'stock-movements',
    name: 'admin.stock_movements',
    meta: {
      title: 'admin::sidebar.stock_movements',
      icon: 'tabler-chart-histogram',
    },
    children: [
      {
        path: '',
        name: 'admin.stock_movements.index',
        component: () => import('@/modules/inventory/pages/admin/stockMovement/Index.vue'),
        meta: {
          permission: 'admin.stock_movements.index',
        },
      },
      {
        path: 'create',
        name: 'admin.stock_movements.create',
        component: () => import('@/modules/inventory/pages/admin/stockMovement/Create.vue'),
        meta: {
          title: 'admin::resource.create',
          transParam: { resource: 'inventory::stock_movements.stock_movement' },
          permission: 'admin.stock_movements.create',
        },
      },
      {
        path: ':id/show',
        name: 'admin.stock_movements.show',
        component: () => import('@/modules/inventory/pages/admin/stockMovement/Show.vue'),
        meta: {
          title: 'admin::resource.show',
          transParam: { resource: 'inventory::stock_movements.stock_movement' },
          permission: 'admin.stock_movements.show',
        },
      },
    ],
  },
  {
    path: 'stock-transfers',
    name: 'admin.stock_transfers',
    meta: {
      title: 'inventory::stock_transfers.stock_transfers',
      icon: 'tabler-transfer',
    },
    children: [
      {
        path: '',
        name: 'admin.stock_transfers.index',
        component: () => import('@/modules/inventory/pages/admin/stockTransfer/Index.vue'),
        meta: {
          permission: 'admin.stock_transfers.index',
        },
      },
      {
        path: 'create',
        name: 'admin.stock_transfers.create',
        component: () => import('@/modules/inventory/pages/admin/stockTransfer/Create.vue'),
        meta: {
          title: 'admin::resource.create',
          transParam: { resource: 'inventory::stock_transfers.stock_transfer' },
          permission: 'admin.stock_transfers.create',
        },
      },
      {
        path: ':id/edit',
        name: 'admin.stock_transfers.edit',
        component: () => import('@/modules/inventory/pages/admin/stockTransfer/Edit.vue'),
        meta: {
          title: 'admin::resource.edit',
          transParam: { resource: 'inventory::stock_transfers.stock_transfer' },
          permission: 'admin.stock_transfers.edit',
        },
      },
      {
        path: ':id/show',
        name: 'admin.stock_transfers.show',
        component: () => import('@/modules/inventory/pages/admin/stockTransfer/Show.vue'),
        meta: {
          title: 'admin::resource.show',
          transParam: { resource: 'inventory::stock_transfers.stock_transfer' },
          permission: 'admin.stock_transfers.show',
        },
      },
    ],
  },
  {
    path: 'inventory-counts',
    name: 'admin.inventory_counts',
    meta: {
      title: 'inventory::inventory_counts.inventory_counts',
      icon: 'tabler-clipboard-list',
    },
    children: [
      {
        path: '',
        name: 'admin.inventory_counts.index',
        component: () => import('@/modules/inventory/pages/admin/inventoryCount/Index.vue'),
        meta: {
          permission: 'admin.inventory_counts.index',
        },
      },
      {
        path: 'create',
        name: 'admin.inventory_counts.create',
        component: () => import('@/modules/inventory/pages/admin/inventoryCount/Create.vue'),
        meta: {
          title: 'admin::resource.create',
          transParam: { resource: 'inventory::inventory_counts.inventory_count' },
          permission: 'admin.inventory_counts.create',
        },
      },
      {
        path: ':id/edit',
        name: 'admin.inventory_counts.edit',
        component: () => import('@/modules/inventory/pages/admin/inventoryCount/Edit.vue'),
        meta: {
          title: 'admin::resource.edit',
          transParam: { resource: 'inventory::inventory_counts.inventory_count' },
          permission: 'admin.inventory_counts.edit',
        },
      },
      {
        path: ':id/show',
        name: 'admin.inventory_counts.show',
        component: () => import('@/modules/inventory/pages/admin/inventoryCount/Show.vue'),
        meta: {
          title: 'admin::resource.show',
          transParam: { resource: 'inventory::inventory_counts.inventory_count' },
          permission: 'admin.inventory_counts.show',
        },
      },
    ],
  },
  {
    path: 'purchases',
    name: 'admin.purchases',
    meta: {
      title: 'admin::sidebar.purchases',
      icon: 'tabler-report-money',
    },
    children: [
      {
        path: '',
        name: 'admin.purchases.index',
        component: () => import('@/modules/inventory/pages/admin/purchase/Index.vue'),
        meta: {
          permission: 'admin.purchases.index',
        },
      },
      {
        path: 'create',
        name: 'admin.purchases.create',
        component: () => import('@/modules/inventory/pages/admin/purchase/Create.vue'),
        meta: {
          title: 'admin::resource.create',
          transParam: { resource: 'inventory::purchases.purchase' },
          permission: 'admin.purchases.create',
        },
      },
      {
        path: ':id/edit',
        name: 'admin.purchases.edit',
        component: () => import('@/modules/inventory/pages/admin/purchase/Edit.vue'),
        meta: {
          title: 'admin::resource.edit',
          transParam: { resource: 'inventory::purchases.purchase' },
          permission: 'admin.purchases.edit',
        },
      },
      {
        path: ':id/show',
        name: 'admin.purchases.show',
        component: () => import('@/modules/inventory/pages/admin/purchase/Show.vue'),
        meta: {
          title: 'admin::resource.show',
          transParam: { resource: 'inventory::purchases.purchase' },
          permission: 'admin.purchases.show',
        },
      },
    ],
  },
]

export default adminRoutes
