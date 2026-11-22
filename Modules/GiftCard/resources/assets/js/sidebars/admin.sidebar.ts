import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'

export const adminSidebar: SidebarList = {
  target: 'admin',
  items: [
    {
      key: 'admin.giftcard',
      label: 'admin::sidebar.gift_cards',
      icon: 'tabler-gift-card',
      parentKey: 'admin.heading.customers_growth',
      permission: [
        'admin.gift_cards.analytics',
        'admin.gift_cards.index',
        'admin.gift_card_transactions.index',
        'admin.gift_card_batches.index',
        'admin.gift_card_breakage_batches.index',
      ],
      sort: 304,
      children: [
        {
          key: 'admin.gift_cards_analytics.index',
          label: 'admin::sidebar.analytics',
          to: { name: 'admin.gift_cards_analytics.index' },
          permission: 'admin.gift_cards.analytics',
        },
        {
          key: 'admin.gift_cards.index',
          label: 'admin::sidebar.gift_cards_catalog',
          to: { name: 'admin.gift_cards.index' },
          permission: 'admin.gift_cards.index',
        },
        {
          key: 'admin.gift_card_transactions.index',
          label: 'admin::sidebar.gift_card_transactions',
          to: { name: 'admin.gift_card_transactions.index' },
          permission: 'admin.gift_card_transactions.index',
        },
        {
          key: 'admin.gift_card_breakage_batches.index',
          label: 'giftcard::gift_card_breakage_batches.gift_card_breakage_batches',
          to: { name: 'admin.gift_card_breakage_batches.index' },
          permission: 'admin.gift_card_breakage_batches.index',
        },
        {
          key: 'admin.gift_card_batches.index',
          label: 'admin::sidebar.gift_card_batches',
          to: { name: 'admin.gift_card_batches.index' },
          permission: 'admin.gift_card_batches.index',
        },
      ],
    },
  ],
}
