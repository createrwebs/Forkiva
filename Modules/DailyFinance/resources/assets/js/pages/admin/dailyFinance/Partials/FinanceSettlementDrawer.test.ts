import { defineComponent, ref } from 'vue'
import { flushPromises, shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import FinanceSettlementDrawer from './FinanceSettlementDrawer.vue'
import type {
  ElectronicMethod,
  PaymentSettlement,
  SettlementMeta,
  SettlementPage,
  SettlementStatus,
} from '@/modules/dailyfinance/contracts/paymentSettlement'

const load = vi.fn()
const meta = ref<SettlementMeta | null>(null)
const history = ref<SettlementPage<PaymentSettlement> | null>(null)
const status = ref<SettlementStatus | null>(null)
const method = ref<ElectronicMethod | null>(null)
const loading = ref(false)
const error = ref('')
const canCreate = ref(true)
vi.mock('@/modules/dailyfinance/composables/usePaymentSettlements', () => ({
  usePaymentSettlements: () => ({ meta, history, status, method, loading, error, load }),
}))
vi.mock('@/modules/auth/composables/auth', () => ({
  useAuth: () => ({ can: () => canCreate.value }),
}))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))

const record = {
  id: 12,
  reference: 'PS-0012',
  status: { id: 'partial', name: 'Partially received' },
  method: { id: 'card', name: 'Card', icon: 'tabler-credit-card' },
  destination_name: 'Main bank',
  batch_reference: 'GATEWAY-12',
  created_at: '2026-10-13 10:00 AM',
  expected: { formatted: '$200.00' },
  actual: { formatted: '$50.00' },
  remaining: { formatted: '$150.00' },
} as PaymentSettlement

function render() {
  return shallowMount(FinanceSettlementDrawer, {
    props: { branchId: 1, branchName: 'Main', date: '2026-10-13' },
    global: {
      renderStubDefaultSlot: true,
      stubs: {
        FinanceDrawer: defineComponent({
          name: 'FinanceDrawer',
          template: '<section><slot name="context" /><slot name="header-end" /><slot /></section>',
        }),
        FinanceSettlementHistoryItem: false,
        FinanceSettlementDetails: true,
        FinanceSettlementForm: true,
        TableStatus: true,
        ...Object.fromEntries(
          ['VBtn', 'VIcon', 'VAvatar', 'VTabs', 'VTab', 'VSelect', 'VPagination'].map((name) => [
            name,
            true,
          ]),
        ),
      },
    },
  })
}

function button(wrapper: ReturnType<typeof render>, key: string) {
  return wrapper
    .findAllComponents({ name: 'VBtn' })
    .find((entry) => entry.text().endsWith('.' + key))!
}

beforeEach(() => {
  vi.clearAllMocks()
  meta.value = { branch_id: 1, methods: [], accounts: [] } as unknown as SettlementMeta
  history.value = {
    data: [record],
    pagination: { current_page: 2, last_page: 3, total: 21 },
  }
  status.value = null
  method.value = null
  loading.value = false
  error.value = ''
  canCreate.value = true
})

describe('online settlement drawer', () => {
  it('opens a record from its row and returns to the same filtered history page', async () => {
    const wrapper = render()
    const row = wrapper.get('button.settlement-row')
    expect(row.text()).toContain('PS-0012')
    expect(row.text()).toContain('Main bank')
    expect(row.text()).toContain('$150.00')
    expect(row.text()).toContain('$50.00')
    expect(row.text()).toContain('$200.00')
    await row.trigger('click')
    await flushPromises()
    const details = wrapper.findComponent({ name: 'FinanceSettlementDetails' })
    expect(details.attributes('id')).toBe('12')
    expect(wrapper.findComponent({ name: 'FinanceDrawer' }).attributes('persistent')).toBe('true')
    details.vm.$emit('changed')
    expect(load).toHaveBeenCalledWith(2)
    details.vm.$emit('close')
    await flushPromises()
    expect(wrapper.findComponent({ name: 'FinanceSettlementDetails' }).exists()).toBe(false)
    expect(history.value?.pagination.current_page).toBe(2)
    wrapper.findComponent({ name: 'VPagination' }).vm.$emit('update:modelValue', 3)
    expect(load).toHaveBeenCalledWith(3)
    wrapper.unmount()
  })

  it('keeps the create action disabled during loading and makes skeleton rows inert', async () => {
    loading.value = true
    meta.value = null
    history.value = null
    const wrapper = render()
    expect(button(wrapper, 'new').attributes('disabled')).toBe('true')
    expect(wrapper.find('[role="status"]').exists()).toBe(true)
    expect(wrapper.findAll('button.settlement-row')).toHaveLength(5)
    expect(
      wrapper
        .findAll('button.settlement-row')
        .every((row) => row.attributes('disabled') !== undefined),
    ).toBe(true)
    expect(wrapper.findComponent({ name: 'FinanceSettlementDetails' }).exists()).toBe(false)
    canCreate.value = false
    await flushPromises()
    expect(button(wrapper, 'new')).toBeUndefined()
    wrapper.unmount()
  })

  it('shows the refresh spinner only for a request started by the refresh button', async () => {
    loading.value = true
    const wrapper = render()
    const refresh = wrapper
      .findAllComponents({ name: 'VBtn' })
      .find(
        (entry) => entry.attributes('aria-label') === 'dailyfinance::payment_settlements.refresh',
      )!
    expect(refresh.attributes('loading')).toBe('false')

    loading.value = false
    await flushPromises()
    let complete!: () => void
    load.mockReturnValueOnce(
      new Promise<void>((resolve) => {
        complete = resolve
      }),
    )
    refresh.vm.$emit('click')
    await flushPromises()
    expect(refresh.attributes('loading')).toBe('true')
    expect(load).toHaveBeenCalledWith(2)

    complete()
    await flushPromises()
    expect(refresh.attributes('loading')).toBe('false')
    wrapper.unmount()
  })

  it('resets both filters from the empty state and offers retry for a failed request', async () => {
    history.value = { data: [], pagination: { current_page: 1, last_page: 1, total: 0 } }
    status.value = 'settled'
    method.value = 'card'
    const wrapper = render()
    expect(wrapper.text()).toContain('payment_settlements.no_match')
    button(wrapper, 'reset_filters').vm.$emit('click')
    await flushPromises()
    expect(status.value).toBeNull()
    expect(method.value).toBeNull()
    expect(wrapper.text()).toContain('payment_settlements.empty_hint')
    error.value = 'Connection interrupted'
    history.value = null
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain('Connection interrupted')
    button(wrapper, 'retry').vm.$emit('click')
    expect(load).toHaveBeenCalledWith(1)
    wrapper.unmount()
  })
})
