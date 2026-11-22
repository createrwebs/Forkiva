import { ref } from 'vue'
import { flushPromises, shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import FinanceSettlementForm from './FinanceSettlementForm.vue'
import FinanceSettlementCreditForm from './FinanceSettlementCreditForm.vue'
import DatePicker from '@/modules/core/components/Form/DatePicker.vue'
import type {
  PaymentSettlement,
  SettlementMeta,
} from '@/modules/dailyfinance/contracts/paymentSettlement'

const api = vi.hoisted(() => ({
  settlementCandidates: vi.fn(),
  createSettlement: vi.fn(),
  receiveSettlement: vi.fn(),
  cancelSettlement: vi.fn(),
}))
vi.mock('@/modules/dailyfinance/api/paymentSettlement.api', () => api)
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key, locale: ref('en-US') }) }))

const meta: SettlementMeta = {
  branch_id: 1,
  currency: 'JOD',
  precision: 3,
  today: '2026-10-12',
  methods: [{ id: 'card', name: 'Card', icon: 'tabler-credit-card', color: 'success' }],
  accounts: [],
  linked_accounts_available: false,
}
const global = {
  renderStubDefaultSlot: true,
  stubs: {
    FinanceSettlementCollections: false,
    ...Object.fromEntries(
      [
        'VDialog',
        'VCard',
        'VCardText',
        'VAlert',
        'VBtn',
        'VIcon',
        'VSelect',
        'VTextField',
        'VTextarea',
        'VCheckbox',
        'VCheckboxBtn',
        'VPagination',
        'VAvatar',
      ].map((name) => [name, true]),
    ),
  },
}
const money = { amount: 10.3, currency: 'JOD', precision: 3, formatted: 'JOD 10.300' }
const record = {
  id: 1,
  reference: 'PS-0001',
  branch_id: 1,
  destination_name: 'Bank',
  today: meta.today,
  currency: 'JOD',
  expected: money,
  remaining: money,
  actual: { ...money, amount: 0, formatted: 'JOD 0.000' },
  expected_decimal: '10.300',
  actual_decimal: '0.000',
  remaining_decimal: '10.300',
} as PaymentSettlement

function button(wrapper: ReturnType<typeof shallowMount>, key: string) {
  return wrapper
    .findAllComponents({ name: 'VBtn' })
    .find((entry) => entry.text() === `dailyfinance::payment_settlements.${key}`)!
}

beforeEach(() => {
  vi.clearAllMocks()
  api.settlementCandidates.mockResolvedValue({
    data: {
      body: {
        data: [
          {
            id: 10,
            reference: 'ORD-10',
            amount: { ...money, amount: 10.345, formatted: 'JOD 10.345' },
            amount_decimal: '10.345',
            selectable: true,
            source_hash: 'a'.repeat(64),
            collected_at: '2026-10-12',
            transaction_id: null,
          },
        ],
        pagination: { current_page: 1, last_page: 1, total: 1 },
      },
    },
  })
})

describe('settlement dialogs', () => {
  it('requires source selection, valid destination and explicit review before creating an exact batch', async () => {
    const wrapper = shallowMount(FinanceSettlementForm, {
      props: { branchId: 1, branchName: 'Main', date: meta.today, meta },
      global,
    })
    await flushPromises()
    expect(button(wrapper, 'continue').attributes('disabled')).toBe('true')
    wrapper.findComponent({ name: 'VCheckboxBtn' }).vm.$emit('update:modelValue', true)
    await flushPromises()
    button(wrapper, 'continue').vm.$emit('click')
    await flushPromises()
    const fields = wrapper.findAllComponents({ name: 'VTextField' })
    fields
      .find((entry) => entry.attributes('label')?.endsWith('.destination'))!
      .vm.$emit('update:modelValue', 'Main bank')
    fields
      .find((entry) => entry.attributes('label')?.endsWith('.batch_reference'))!
      .vm.$emit('update:modelValue', 'GATEWAY-1')
    fields
      .find((entry) => entry.attributes('label')?.endsWith('.fees'))!
      .vm.$emit('update:modelValue', '0.045')
    await flushPromises()
    button(wrapper, 'continue').vm.$emit('click')
    await flushPromises()
    button(wrapper, 'confirm').vm.$emit('click')
    expect(api.createSettlement).not.toHaveBeenCalled()
    wrapper.findComponent({ name: 'VCheckbox' }).vm.$emit('update:modelValue', true)
    await flushPromises()
    api.createSettlement.mockResolvedValueOnce({ data: { body: record } })
    button(wrapper, 'confirm').vm.$emit('click')
    await flushPromises()
    expect(api.createSettlement.mock.calls[0]![0]).toMatchObject({
      payment_ids: [10],
      source_hashes: { 10: 'a'.repeat(64) },
      fees: '0.045',
      account_key: null,
      destination_name: 'Main bank',
    })
    expect(wrapper.emitted('saved')).toBeUndefined()
    wrapper.findComponent({ name: 'VDialog' }).vm.$emit('afterLeave')
    expect(wrapper.emitted('saved')?.[0]).toEqual([record])
    wrapper.unmount()
  })

  it('requires an explanation for final shortage and resets acknowledgement when evidence changes', async () => {
    const wrapper = shallowMount(FinanceSettlementCreditForm, {
      props: { record, mode: 'receive' },
      global,
    })
    const fields = wrapper.findAllComponents({ name: 'VTextField' })
    fields
      .find((entry) => entry.attributes('label')?.endsWith('.amount'))!
      .vm.$emit('update:modelValue', '9.300')
    fields
      .find((entry) => entry.attributes('label')?.endsWith('.bank_reference'))!
      .vm.$emit('update:modelValue', 'BANK-1')
    const datePicker = wrapper.findComponent(DatePicker)
    expect(datePicker.props('max')).toBe(meta.today)
    datePicker.vm.$emit('update:modelValue', '2026-10-11')
    wrapper.findAllComponents({ name: 'VCheckbox' })[0]!.vm.$emit('update:modelValue', true)
    await flushPromises()
    const acknowledge = wrapper.findAllComponents({ name: 'VCheckbox' })[1]!
    acknowledge.vm.$emit('update:modelValue', true)
    await flushPromises()
    button(wrapper, 'confirm_receipt').vm.$emit('click')
    expect(api.receiveSettlement).not.toHaveBeenCalled()
    wrapper
      .findComponent({ name: 'VTextarea' })
      .vm.$emit('update:modelValue', 'Bank statement difference')
    await flushPromises()
    expect(button(wrapper, 'confirm_receipt').attributes('disabled')).toBe('true')
    acknowledge.vm.$emit('update:modelValue', true)
    await flushPromises()
    api.receiveSettlement.mockResolvedValueOnce({ data: { body: record } })
    button(wrapper, 'confirm_receipt').vm.$emit('click')
    await flushPromises()
    expect(api.receiveSettlement.mock.calls[0]![1]).toMatchObject({
      amount: '9.300',
      variance_reason: 'Bank statement difference',
      is_final: true,
      reference: 'BANK-1',
      received_date: '2026-10-11',
    })
    wrapper.unmount()
  })

  it('requires a cancellation reason and acknowledgement and retains the dialog after uncertain response', async () => {
    const wrapper = shallowMount(FinanceSettlementCreditForm, {
      props: { record, mode: 'cancel' },
      global,
    })
    wrapper
      .findComponent({ name: 'VTextarea' })
      .vm.$emit('update:modelValue', 'Wrong batch selected')
    await flushPromises()
    button(wrapper, 'confirm_cancel').vm.$emit('click')
    expect(api.cancelSettlement).not.toHaveBeenCalled()
    wrapper.findComponent({ name: 'VCheckbox' }).vm.$emit('update:modelValue', true)
    await flushPromises()
    api.cancelSettlement.mockRejectedValueOnce(new Error('Disconnected'))
    button(wrapper, 'confirm_cancel').vm.$emit('click')
    await flushPromises()
    expect(wrapper.findComponent({ name: 'VDialog' }).attributes('persistent')).toBe('true')
    expect(button(wrapper, 'dismiss').attributes('disabled')).toBe('true')
    api.cancelSettlement.mockResolvedValueOnce({ data: { body: record } })
    button(wrapper, 'retry').vm.$emit('click')
    await flushPromises()
    expect(api.cancelSettlement.mock.calls[1]![1]).toEqual(api.cancelSettlement.mock.calls[0]![1])
    wrapper.unmount()
  })
})
