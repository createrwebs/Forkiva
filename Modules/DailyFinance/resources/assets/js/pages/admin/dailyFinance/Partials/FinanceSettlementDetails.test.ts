import { flushPromises, shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import FinanceSettlementDetails from './FinanceSettlementDetails.vue'
import type { PaymentSettlement } from '@/modules/dailyfinance/contracts/paymentSettlement'

const api = vi.hoisted(() => ({ settlementDetails: vi.fn() }))
vi.mock('@/modules/dailyfinance/api/paymentSettlement.api', () => api)
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))

const money = (amount: number) => ({
  amount,
  formatted: `$${amount.toFixed(2)}`,
  currency: 'USD',
  precision: 2,
})
const record: PaymentSettlement = {
  id: 6,
  reference: 'PS-0006',
  branch_id: 1,
  status: { id: 'pending', name: 'Awaiting settlement' },
  method: { id: 'card', name: 'Card', icon: 'tabler-credit-card', color: 'success' },
  currency: 'USD',
  today: '2026-10-13',
  account_key: null,
  expected_decimal: '515.10',
  actual_decimal: '0.00',
  remaining_decimal: '515.10',
  destination_name: 'Main bank',
  batch_reference: 'GATEWAY-6',
  expected: money(515.1),
  actual: money(0),
  remaining: money(515.1),
  gross: money(615.1),
  fees: money(100),
  variance: null,
  notes: 'Card collections',
  created_by: { id: 1, name: 'Manager' },
  created_at: '2026-10-13 12:18 PM',
  cancelled_at: null,
  cancelled_by: null,
  cancellation_reason: null,
  receipts: [],
  items_count: 1,
  items: [{ id: 12, reference: 'ORD-12', collected_at: '2026-10-12', amount: money(615.1) }],
  abilities: { receive: true, cancel: true },
}

function render() {
  return shallowMount(FinanceSettlementDetails, {
    props: { id: 6, branchId: 1, branchName: 'Main' },
    global: {
      renderStubDefaultSlot: true,
      stubs: {
        FinanceSettlementActivity: false,
        FinanceSettlementCreditForm: true,
        TableStatus: true,
        ...Object.fromEntries(
          [
            'VDialog',
            'VCard',
            'VCardText',
            'VAlert',
            'VBtn',
            'VIcon',
            'VTabs',
            'VTab',
            'VChip',
          ].map((name) => [name, true]),
        ),
      },
    },
  })
}

function action(wrapper: ReturnType<typeof render>, key: string) {
  return wrapper
    .findAllComponents({ name: 'VBtn' })
    .find((button) => button.text().endsWith('.' + key))
}

beforeEach(() => {
  vi.clearAllMocks()
  api.settlementDetails.mockResolvedValue({ data: { body: record } })
})

describe('settlement details dialog', () => {
  it('opens bank credit from the footer and updates actions and history after confirmation', async () => {
    const wrapper = render()
    await flushPromises()
    expect(wrapper.get('.settlement-detail__title').text()).toContain('PS-0006')
    expect(wrapper.get('.settlement-detail__empty').text()).toContain(
      'payment_settlements.no_receipts',
    )
    expect(wrapper.get('.settlement-detail__actions').text()).toContain(
      'payment_settlements.receive',
    )
    action(wrapper, 'receive')!.vm.$emit('click')
    await flushPromises()
    const credit = wrapper.findComponent({ name: 'FinanceSettlementCreditForm' })
    expect(credit.attributes('mode')).toBe('receive')
    expect(wrapper.findComponent({ name: 'VDialog' }).attributes('persistent')).toBe('true')
    credit.vm.$emit('saved', {
      ...record,
      status: { id: 'settled', name: 'Settled' },
      abilities: { receive: false, cancel: false },
      receipts: [
        {
          id: 1,
          reference: 'BANK-1',
          amount: { formatted: '$515.10' },
          received_date_formatted: '2026-10-13',
          created_by: { name: 'Manager' },
          is_final: true,
        },
      ],
    })
    await flushPromises()
    expect(wrapper.emitted('changed')).toHaveLength(1)
    expect(wrapper.find('.settlement-detail__actions').exists()).toBe(false)
    expect(wrapper.text()).toContain('BANK-1')
    expect(wrapper.findComponent({ name: 'VDialog' }).attributes('persistent')).toBe('false')
    wrapper.unmount()
  })

  it('switches between source evidence and bank credits and respects cancellation availability', async () => {
    const wrapper = render()
    await flushPromises()
    wrapper.findComponent({ name: 'VTabs' }).vm.$emit('update:modelValue', 'sources')
    await flushPromises()
    expect(wrapper.get('section').text()).toContain('ORD-12')
    expect(wrapper.get('section').attributes('tabindex')).toBe('0')
    action(wrapper, 'cancel')!.vm.$emit('click')
    await flushPromises()
    const cancel = wrapper.findComponent({ name: 'FinanceSettlementCreditForm' })
    expect(cancel.attributes('mode')).toBe('cancel')
    cancel.vm.$emit('close')
    await flushPromises()
    expect(wrapper.findComponent({ name: 'FinanceSettlementCreditForm' }).exists()).toBe(false)
    expect(wrapper.get('section').text()).toContain('ORD-12')
    wrapper.unmount()
  })

  it('shows a matching loader without mutation controls and offers retry on read failure', async () => {
    let reject!: (error: Error) => void
    api.settlementDetails.mockReturnValueOnce(
      new Promise((_resolve, fail) => {
        reject = fail
      }),
    )
    const wrapper = render()
    expect(wrapper.find('[role="status"]').exists()).toBe(true)
    expect(action(wrapper, 'receive')).toBeUndefined()
    reject(new Error('Disconnected'))
    await flushPromises()
    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    action(wrapper, 'retry')!.vm.$emit('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Main bank')
    expect(wrapper.find('[role="status"]').exists()).toBe(false)
    wrapper.unmount()
    expect(api.settlementDetails.mock.calls[1]![2].aborted).toBe(true)
  })

  it('prioritizes released collections and cancellation evidence without suggesting another bank credit', async () => {
    api.settlementDetails.mockResolvedValueOnce({
      data: {
        body: {
          ...record,
          status: { id: 'cancelled', name: 'Cancelled' },
          cancelled_by: { id: 2, name: 'Supervisor' },
          cancelled_at: '2026-10-13 01:33 PM',
          cancellation_reason: 'Wrong payment batch',
          abilities: { receive: false, cancel: false },
        },
      },
    })
    const wrapper = render()
    await flushPromises()
    const evidence = wrapper.get('.settlement-detail__cancellation')
    expect(evidence.text()).toContain('Wrong payment batch')
    expect(evidence.text()).toContain('Supervisor')
    expect(evidence.text()).toContain('2026-10-13 01:33 PM')
    expect(wrapper.findAll('dt')).toHaveLength(2)
    expect(wrapper.text()).not.toContain('payment_settlements.remaining')
    expect(wrapper.get('section.settlement-detail__list').text()).toContain('ORD-12')
    expect(wrapper.find('.settlement-detail__actions').exists()).toBe(false)
    wrapper.findComponent({ name: 'VTabs' }).vm.$emit('update:modelValue', 'receipts')
    await flushPromises()
    expect(wrapper.text()).toContain('payment_settlements.cancelled_no_receipts')
    expect(wrapper.text()).not.toContain('payment_settlements.no_receipts_hint')
    wrapper.unmount()
  })
})
