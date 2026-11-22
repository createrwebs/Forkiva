import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'

export const adminSidebar: SidebarList = {
  target: 'admin',
  items: [
    {
      key: 'admin.inventories.analytics',
      label: 'admin::sidebar.overview',
      to: { name: 'admin.inventories.analytics' },
      icon: 'tabler-layout-dashboard',
      parentKey: 'admin.heading.inventory',
      permission: 'admin.inventories.analytics',
      sort: 201,
    },
    {
      key: 'admin.ingredients.index',
      label: 'admin::sidebar.ingredients',
      to: { name: 'admin.ingredients.index' },
      icon: 'ic-outline-fastfood',
      parentKey: 'admin.heading.inventory',
      permission: 'admin.ingredients.index',
      sort: 202,
    },
    {
      key: 'admin.inventory.stock',
      label: 'admin::sidebar.stock',
      icon: 'ic-outline-inventory-2',
      parentKey: 'admin.heading.inventory',
      permission: [
        'admin.stock_movements.index',
        'admin.stock_transfers.index',
        'admin.inventory_counts.index',
      ],
      sort: 203,
      children: [
        {
          key: 'admin.stock_movements.index',
          label: 'admin::sidebar.stock_movements',
          to: { name: 'admin.stock_movements.index' },
          permission: 'admin.stock_movements.index',
        },
        {
          key: 'admin.stock_transfers.index',
          label: 'admin::sidebar.stock_transfers',
          to: { name: 'admin.stock_transfers.index' },
          permission: 'admin.stock_transfers.index',
        },
        {
          key: 'admin.inventory_counts.index',
          label: 'admin::sidebar.inventory_counts',
          to: { name: 'admin.inventory_counts.index' },
          permission: 'admin.inventory_counts.index',
        },
      ],
    },
    {
      key: 'admin.inventory.purchasing',
      label: 'admin::sidebar.purchasing',
      icon: 'tabler-shopping-cart',
      parentKey: 'admin.heading.inventory',
      permission: ['admin.purchases.index', 'admin.suppliers.index'],
      sort: 204,
      children: [
        {
          key: 'admin.purchases.index',
          label: 'admin::sidebar.purchases',
          to: { name: 'admin.purchases.index' },
          permission: 'admin.purchases.index',
        },
        {
          key: 'admin.suppliers.index',
          label: 'admin::sidebar.suppliers',
          to: { name: 'admin.suppliers.index' },
          permission: 'admin.suppliers.index',
        },
      ],
    },
    {
      key: 'admin.units.index',
      label: 'admin::sidebar.units',
      to: { name: 'admin.units.index' },
      icon: 'tabler-ruler',
      parentKey: 'admin.heading.inventory',
      permission: 'admin.units.index',
      sort: 205,
    },
  ],
}
