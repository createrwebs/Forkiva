import type { ModuleManifest } from '@/modules/core/contracts/ModuleManifest.ts'
import type { RouteRecordRaw } from 'vue-router'
import { describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { adminSidebar as coreSidebar } from '@/modules/core/sidebars/admin.sidebar.ts'

const modules = import.meta.glob<{ default: ModuleManifest }>(
  '/Modules/{Admin,Category,Dashboard,Discount,Invoice,Menu,Option,Order,Payment,Product,Voucher}/resources/assets/js/manifest.ts',
  { eager: true },
)
const manifests = Object.values(modules).map((module) => module.default)
const registrations = manifests.flatMap((module) => module.routes ?? [])
const routes: RouteRecordRaw[] = [
  {
    path: '/admin',
    children: registrations
      .filter(({ target }) => target === 'admin')
      .flatMap(({ routes }) => routes),
  },
  ...registrations.filter(({ target }) => target === 'public').flatMap(({ routes }) => routes),
]

function createTestRouter() {
  return createRouter({ history: createMemoryHistory(), routes })
}

describe('module navigation compatibility', () => {
  it('registers each separated feature without the former aggregate modules', () => {
    expect(manifests.map(({ name }) => name).sort()).toEqual([
      'admin',
      'category',
      'dashboard',
      'discount',
      'invoice',
      'menu',
      'option',
      'order',
      'payment',
      'product',
      'voucher',
    ])

    const names: string[] = []
    const collectNames = (records: RouteRecordRaw[]) => {
      for (const record of records) {
        if (record.name) names.push(String(record.name))
        if (record.children) collectNames(record.children)
      }
    }
    collectNames(routes)
    expect(new Set(names).size).toBe(names.length)
  })

  it('preserves existing route names, URLs and permissions', () => {
    const actual = createTestRouter()
      .getRoutes()
      .filter(({ name }) => name)
      .sort((a, b) => String(a.name).localeCompare(String(b.name)))
      .map(({ name, path, meta }) => [name, path, meta.permission])

    expect(actual).toEqual([
      ['admin.categories', '/admin/categories', undefined],
      ['admin.categories.index', '/admin/categories', 'admin.categories.index'],
      ['admin.dashboard', '/admin', undefined],
      ['admin.discounts', '/admin/discounts', undefined],
      ['admin.discounts.create', '/admin/discounts/create', 'admin.discounts.create'],
      ['admin.discounts.edit', '/admin/discounts/:id/edit', 'admin.discounts.edit'],
      ['admin.discounts.index', '/admin/discounts', 'admin.discounts.index'],
      ['admin.invoices', '/admin/invoices', undefined],
      ['admin.invoices.index', '/admin/invoices', 'admin.invoices.index'],
      ['admin.invoices.show', '/admin/invoices/:id/show', 'admin.invoices.show'],
      ['admin.menus', '/admin/menus', undefined],
      ['admin.menus.categories', '/admin/menus/:id/categories', 'admin.categories.index'],
      ['admin.menus.create', '/admin/menus/create', 'admin.menus.create'],
      ['admin.menus.edit', '/admin/menus/:id/edit', 'admin.menus.edit'],
      ['admin.menus.index', '/admin/menus', 'admin.menus.index'],
      ['admin.menus.products', '/admin/menus/:menuId/products', undefined],
      [
        'admin.menus.products.create',
        '/admin/menus/:menuId/products/create',
        'admin.products.create',
      ],
      [
        'admin.menus.products.edit',
        '/admin/menus/:menuId/products/:id/edit',
        'admin.products.edit',
      ],
      ['admin.menus.products.index', '/admin/menus/:menuId/products', 'admin.products.index'],
      ['admin.online_menus', '/admin/online-menus', undefined],
      ['admin.online_menus.create', '/admin/online-menus/create', 'admin.online_menus.create'],
      ['admin.online_menus.edit', '/admin/online-menus/:id/edit', 'admin.online_menus.edit'],
      ['admin.online_menus.index', '/admin/online-menus', 'admin.online_menus.index'],
      ['admin.options', '/admin/options', undefined],
      ['admin.options.create', '/admin/options/create', 'admin.options.create'],
      ['admin.options.edit', '/admin/options/:id/edit', 'admin.options.edit'],
      ['admin.options.index', '/admin/options', 'admin.options.index'],
      ['admin.orders', '/admin/orders', undefined],
      ['admin.orders.index', '/admin/orders', 'admin.orders.index'],
      ['admin.orders.show', '/admin/orders/:id/show', 'admin.orders.show'],
      ['admin.payments', '/admin/payments', undefined],
      ['admin.payments.index', '/admin/payments', 'admin.payments.index'],
      ['admin.products', '/admin/products', undefined],
      ['admin.products.create', '/admin/products/create', 'admin.products.create'],
      ['admin.products.edit', '/admin/products/:id/edit', 'admin.products.edit'],
      ['admin.products.index', '/admin/products', 'admin.products.index'],
      ['admin.reasons', '/admin/reasons', undefined],
      ['admin.reasons.create', '/admin/reasons/create', 'admin.reasons.create'],
      ['admin.reasons.edit', '/admin/reasons/:id/edit', 'admin.reasons.edit'],
      ['admin.reasons.index', '/admin/reasons', 'admin.reasons.index'],
      ['admin.vouchers', '/admin/vouchers', undefined],
      ['admin.vouchers.create', '/admin/vouchers/create', 'admin.vouchers.create'],
      ['admin.vouchers.edit', '/admin/vouchers/:id/edit', 'admin.vouchers.edit'],
      ['admin.vouchers.index', '/admin/vouchers', 'admin.vouchers.index'],
      ['public.online_menu.index', '/online-menu/:slug', undefined],
    ])
  })

  it('keeps menu parameters and breadcrumbs on the feature-owned routes', () => {
    const router = createTestRouter()
    const product = router.resolve({
      name: 'admin.menus.products.edit',
      params: { menuId: '7', id: '12' },
    })
    expect(product.path).toBe('/admin/menus/7/products/12/edit')
    expect(product.params).toEqual({ menuId: '7', id: '12' })
    expect(product.matched.map(({ name }) => name).filter(Boolean)).toEqual([
      'admin.menus',
      'admin.menus.products',
      'admin.menus.products.edit',
    ])
    expect(product.meta).toMatchObject({
      permission: 'admin.products.edit',
      transParam: { resource: 'product::products.product' },
    })

    const category = router.resolve({ name: 'admin.menus.categories', params: { id: '7' } })
    expect(category.path).toBe('/admin/menus/7/categories')
    expect(category.params).toEqual({ id: '7' })
    expect(category.matched.map(({ name }) => name).filter(Boolean)).toEqual([
      'admin.menus',
      'admin.menus.categories',
    ])
  })

  it('assembles sales and promotions in their existing order with permissions from each feature', async () => {
    vi.resetModules()
    const { sidebarRegistry } = await import('@/modules/core/services/SidebarService.ts')
    sidebarRegistry.register('admin', coreSidebar.items)
    for (const manifest of manifests) {
      for (const list of manifest.sidebars ?? []) {
        sidebarRegistry.register(list.target, list.items, list.workspace)
      }
    }
    const sidebar = sidebarRegistry.build('admin', true)
    const operations = sidebar.find(({ key }) => key === 'admin.heading.operations')
    const growth = sidebar.find(({ key }) => key === 'admin.heading.customers_growth')
    expect(operations?.children?.map(({ key }) => key)).toEqual(['admin.dashboard', 'admin.sales'])

    const sales = operations?.children?.find(({ key }) => key === 'admin.sales')
    const promotions = growth?.children?.find(({ key }) => key === 'admin.promotions')
    const salesPermissions = [
      'admin.orders.index',
      'admin.invoices.index',
      'admin.payments.index',
      'admin.reasons.index',
    ]
    const promotionPermissions = ['admin.discounts.index', 'admin.vouchers.index']
    expect(sales?.children?.map(({ key }) => key)).toEqual(salesPermissions)
    expect(promotions?.children?.map(({ key }) => key)).toEqual(promotionPermissions)
    expect([...(sales?.permission ?? [])].sort()).toEqual([...salesPermissions].sort())
    expect([...(operations?.permission ?? [])].sort()).toEqual([...salesPermissions].sort())
    expect([...(promotions?.permission ?? [])].sort()).toEqual([...promotionPermissions].sort())
    expect([...(growth?.permission ?? [])].sort()).toEqual([...promotionPermissions].sort())
  })
})
