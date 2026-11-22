import { ref } from 'vue'
import { flushPromises, shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import FinanceCashFollowUpDrawer from './FinanceCashFollowUpDrawer.vue'
import FinanceHandovers from './FinanceHandovers.vue'
import type { CashHandoverFollowUpSessions } from '@/modules/dailyfinance/contracts/cashHandover'

const result = ref<CashHandoverFollowUpSessions | null>(null)
const load = vi.fn()
const loading = ref(false)
const error = ref(false)
vi.mock('@/modules/dailyfinance/composables/useHandoverFollowUpSessions', () => ({
  useHandoverFollowUpSessions: () => ({ result, loading, error, load }),
}))
vi.mock('@/modules/auth/composables/auth', () => ({ useAuth: () => ({ can: () => true }) }))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
function render() {
  return shallowMount(FinanceCashFollowUpDrawer, {
    props: { branchId: 1, branchName: 'Main', kind: 'pending' },
    global: {
      renderStubDefaultSlot: true,
      stubs: {
        FinanceDrawer: {
          name: 'FinanceDrawer',
          props: ['persistent'],
          template: '<div><header><slot name="header-end" /></header><slot /></div>',
        },
        VBtn: {
          name: 'VBtn',
          props: ['disabled'],
          template: '<button @click="$emit(\'click\')"><slot /></button>',
        },
        VIcon: true,
        VAvatar: true,
        FinanceCashFollowUpSession: false,
        VPagination: true,
      },
    },
  })
}
beforeEach(() => {
  vi.clearAllMocks()
  loading.value = false
  error.value = false
  result.value = {
    pagination: { current_page: 2, last_page: 3, total: 21 },
    data: [
      {
        id: 18,
        register: 'Bakery',
        cashier: 'Sara',
        opened_at_formatted: 'Sep 12, 09:00',
        closed_at_formatted: 'Sep 12, 17:00',
        currency: 'JOD',
        unhanded: { amount: 4, formatted: 'JOD 4.000', currency: 'JOD', precision: 3 },
        pending: { amount: 6.345, formatted: 'JOD 6.345', currency: 'JOD', precision: 3 },
        pending_count: 1,
      },
    ],
  }
})
describe('cash follow-up drawer', () => {
  it('shows historical source amounts and opens the existing handover workflow', async () => {
    const wrapper = render()
    expect(wrapper.text()).toContain('Sep 12, 17:00')
    expect(wrapper.text()).toContain('JOD 6.345')
    wrapper.findComponent({ name: 'VPagination' }).vm.$emit('update:modelValue', 3)
    expect(load).toHaveBeenCalledWith(3)
    await wrapper.get('.cash-followup-session').trigger('click')
    const detail = wrapper.findComponent(FinanceHandovers)
    expect(detail.props('sessionId')).toBe(18)
    detail.vm.$emit('saved')
    expect(wrapper.emitted('saved')).toHaveLength(1)
    detail.vm.$emit('busy', true)
    await flushPromises()
    expect(wrapper.findComponent({ name: 'FinanceDrawer' }).props('persistent')).toBe(true)
    await wrapper.get('header button').trigger('click')
    expect(wrapper.findComponent(FinanceHandovers).exists()).toBe(true)
    detail.vm.$emit('busy', false)
    await flushPromises()
    await wrapper.get('header button').trigger('click')
    expect(wrapper.findComponent(FinanceHandovers).exists()).toBe(false)
    expect(load).toHaveBeenCalled()
  })
  it('distinguishes failed reads from a genuinely clear list and disables first-load refresh', async () => {
    const wrapper = render()
    loading.value = true
    result.value = null
    await flushPromises()
    expect(wrapper.find('[role="status"]').exists()).toBe(true)
    expect(wrapper.get('[role="status"]').findAll('button')).toHaveLength(0)
    expect(wrapper.findComponent({ name: 'VBtn' }).props('disabled')).toBe(true)
    loading.value = false
    error.value = true
    await flushPromises()
    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    expect(wrapper.get('[role="alert"]').text()).toContain('cash_handovers.load_error')
    expect(wrapper.get('[role="alert"]').text()).not.toContain('pending_clear')
    error.value = false
    result.value = { data: [], pagination: { current_page: 1, last_page: 1, total: 0 } }
    await flushPromises()
    expect(wrapper.get('.cash-followup__empty h3').text()).toContain('pending_clear')
  })
})
