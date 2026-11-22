import { ref } from 'vue'
import { generateUuid } from '@/modules/core/utils/uuid.ts'

export type GiftCardFinancialAction =
  'recharge' | 'cash_out' | 'issue_void' | 'adjustment' | 'adjustment_decrease'

export const giftCardFinancialActionDefinitions: Array<{
  key: GiftCardFinancialAction
  icon: string
  permission: string
  color: string
}> = [
  {
    key: 'recharge',
    icon: 'tabler-plus',
    permission: 'admin.gift_card_transactions.create',
    color: 'success',
  },
  {
    key: 'cash_out',
    icon: 'tabler-cash-off',
    permission: 'admin.gift_card_transactions.create',
    color: 'warning',
  },
  {
    key: 'issue_void',
    icon: 'tabler-receipt-off',
    permission: 'admin.gift_card_transactions.create',
    color: 'error',
  },
  {
    key: 'adjustment',
    icon: 'tabler-adjustments-plus',
    permission: 'admin.gift_card_transactions.manual_adjust',
    color: 'primary',
  },
  {
    key: 'adjustment_decrease',
    icon: 'tabler-adjustments-minus',
    permission: 'admin.gift_card_transactions.manual_adjust',
    color: 'primary',
  },
]

export function giftCardActionGeneralError(
  errors: Record<string, unknown> | null | undefined,
  message: string | null | undefined,
  fallback: string,
): string {
  return Object.keys(errors || {}).length === 0 ? message || fallback : ''
}

const permissionByAction = Object.fromEntries(
  giftCardFinancialActionDefinitions.map((action) => [action.key, action.permission]),
) as Record<GiftCardFinancialAction, string>

function rawStatus(card: Record<string, any>): string {
  return String(card.status?.id || card.status || '')
}

function rawBalance(card: Record<string, any>): number {
  return Number(
    card.current_balance?.original?.amount ??
      card.current_balance?.amount ??
      card.current_balance ??
      0,
  )
}

function isExpiredForFinancialActions(card: Record<string, any>): boolean {
  if (card.is_expired === true) {
    return true
  }

  if (!card.expiry_date) {
    return false
  }

  return new Date(`${card.expiry_date}T00:00:00`).getTime() < Date.now()
}

export function availableGiftCardFinancialActions(
  card: Record<string, any>,
  can: (permission: string) => boolean,
): GiftCardFinancialAction[] {
  const status = rawStatus(card)
  const positiveBalance = rawBalance(card) > 0
  const expired = status === 'expired' || isExpiredForFinancialActions(card)
  const operational = !expired && ['inactive', 'active', 'used'].includes(status)
  const spendable = ['active', 'used'].includes(status) && positiveBalance

  return (Object.keys(permissionByAction) as GiftCardFinancialAction[]).filter((action) => {
    if (!can(permissionByAction[action])) {
      return false
    }
    if (expired) {
      return false
    }
    if (action === 'recharge') {
      return operational
    }
    if (action === 'cash_out' || action === 'issue_void') {
      return spendable
    }
    return true
  })
}

export function financialActionFieldVisibility(
  action: GiftCardFinancialAction,
  fundingType: string,
  accountingAvailable: boolean,
) {
  const recharge = action === 'recharge'
  const cashOut = action === 'cash_out'
  const issueVoid = action === 'issue_void'

  return {
    amount: !issueVoid,
    fundingType: recharge,
    tenderMethod: recharge || cashOut,
    financialAccount: accountingAvailable && (cashOut || (recharge && fundingType === 'paid')),
    originalIssue: issueVoid,
  }
}

export function useRetainedIdempotencyKey(generate: () => string = generateUuid) {
  const key = ref(generate())

  return {
    key,
    retainAfterFailure: () => key.value,
    regenerateAfterSuccess: () => {
      key.value = generate()
    },
  }
}

export function availableBreakageActions(
  status: string,
  can: (permission: string) => boolean,
): string[] {
  if (status === 'draft' && can('admin.gift_card_breakage_batches.review')) {
    return ['review']
  }
  if (status === 'reviewed' && can('admin.gift_card_breakage_batches.post')) {
    return ['post']
  }
  if (status === 'posted' && can('admin.gift_card_breakage_batches.reverse')) {
    return ['reverse']
  }

  return []
}

export function canReverseGiftCardTransaction(
  transaction: Record<string, any>,
  can: (permission: string) => boolean,
): boolean {
  return (
    can('admin.gift_card_transactions.reverse') &&
    !transaction.reversed_at &&
    !['purchase', 'redeem', 'refund', 'breakage', 'expire', 'reversal'].includes(
      transaction.type?.id,
    )
  )
}

export function giftCardTransactionAuditRows(item: Record<string, any>) {
  return [
    ['currency_rate', item.currency_rate],
    ['currency_rate_source', item.currency_rate_source],
    ['currency_rate_reviewed_by', item.currency_rate_reviewed_by],
    ['currency_rate_reviewed_at', item.currency_rate_reviewed_at],
    ['currency_rate_review_note', item.currency_rate_review_note],
    ['economic_date', item.economic_date],
    ['funding_type', item.funding_type],
    ['tender_method', item.tender_method],
    ['financial_account', item.financial_account_snapshot?.label || item.financial_account_key],
    [
      'liability_branch',
      item.liability_branch?.name || item.liability_branch?.id || item.liability_branch_id,
    ],
    ['payment', item.payment?.transaction_id || item.payment?.id],
    ['original_transaction', item.original_transaction?.uuid || item.original_transaction?.id],
    [
      'reversed_by_transaction',
      item.reversed_by_transaction?.uuid || item.reversed_by_transaction?.id,
    ],
    ['reversed_at', item.reversed_at],
  ].map(([key, value]) => ({ key, value: value || '-' }))
}
