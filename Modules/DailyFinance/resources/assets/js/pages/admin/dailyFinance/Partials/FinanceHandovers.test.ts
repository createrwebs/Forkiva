import { flushPromises, shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import FinanceHandovers from './FinanceHandovers.vue'

const api = vi.hoisted(() => ({
  handovers: vi.fn(),
  createHandover: vi.fn(),
  receiveHandover: vi.fn(),
  cancelHandover: vi.fn(),
}))
vi.mock('@/modules/dailyfinance/api/cashHandover.api', () => api)
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
const money = (value: number) => ({
  amount: value,
  formatted: `JOD ${value.toFixed(3)}`,
  currency: 'JOD',
  precision: 3,
})
function summary() {
  return {
    currency: 'JOD',
    precision: 3,
    counted: money(10.345),
    handed_over: money(0),
    received: money(0),
    pending: money(0),
    variance: money(0),
    available: money(10.345),
    available_decimal: '10.345',
    can_create: true,
    receivers: [{ id: 3, name: 'Omar' }],
    data: [],
    pagination: { current_page: 1, last_page: 1, total: 0 },
  }
}
function render() {
  return shallowMount(FinanceHandovers, {
    props: { sessionId: 7, sessionLabel: 'Register · #7' },
    global: {
      renderStubDefaultSlot: true,
      stubs: {
        VDialog: {
          name: 'VDialog',
          props: ['modelValue', 'persistent'],
          template: '<div v-if="modelValue"><slot /></div>',
        },
        VCard: true,
        VCardText: true,
        VAvatar: true,
        VBtn: true,
        VIcon: true,
        VForm: true,
        VAlert: true,
        VTextField: {
          name: 'VTextField',
          props: ['modelValue', 'errorMessages'],
          template: '<div />',
        },
        VAutocomplete: true,
        VTextarea: true,
        VCheckbox: true,
        VPagination: true,
        VSkeletonLoader: true,
      },
    },
  })
}

beforeEach(() => {
  vi.clearAllMocks()
  api.handovers.mockResolvedValue({ data: { body: summary() } })
})

describe('cash custody interface', () => {
  it('keeps a receipt awaiting this user visible and requires counted-cash acknowledgement', async () => {
    api.handovers.mockResolvedValue({
      data: {
        body: {
          ...summary(),
          data: [
            {
              id: 1,
              reference: 'CH-0001',
              amount: money(1.234),
              amount_decimal: '1.234',
              status: { id: 'pending', name: 'Awaiting receipt' },
              cashier: { id: 2, name: 'Sara' },
              created_by: { id: 2, name: 'Sara' },
              receiver: { id: 3, name: 'Omar' },
              created_at: '12/10/2026 10:00',
              can_receive: true,
              can_cancel: false,
              received_amount: null,
              received_at: null,
              cancelled_at: null,
              notes: null,
              variance_reason: null,
              cancellation_reason: null,
            },
          ],
          pagination: { current_page: 1, last_page: 1, total: 1 },
        },
      },
    })
    const wrapper = render()
    await flushPromises()
    expect(wrapper.findComponent({ name: 'VForm' }).exists()).toBe(false)
    const receipt = wrapper
      .findAllComponents({ name: 'VBtn' })
      .find((button) => button.text().includes('cash_handovers.receive'))!
    receipt.vm.$emit('click')
    await flushPromises()
    expect(wrapper.find('.finance-handovers__selected').text()).toContain('CH-0001')
    await wrapper.find('v-form-stub').trigger('submit')
    expect(api.receiveHandover).not.toHaveBeenCalled()
    wrapper.findComponent({ name: 'VCheckbox' }).vm.$emit('update:modelValue', true)
    api.receiveHandover.mockResolvedValueOnce({ data: { body: { id: 1 } } })
    await wrapper.find('v-form-stub').trigger('submit')
    await flushPromises()
    expect(api.receiveHandover).toHaveBeenCalledWith(7, 1, {
      received_amount: '1.234',
      variance_reason: null,
    })
    wrapper.unmount()
  })
  it('opens the form only from the action and fills the exact available amount', async () => {
    const wrapper = render()
    await flushPromises()
    expect(wrapper.findComponent({ name: 'VForm' }).exists()).toBe(false)
    wrapper.findAllComponents({ name: 'VBtn' })[0]!.vm.$emit('click')
    await flushPromises()
    expect(wrapper.findComponent({ name: 'VForm' }).exists()).toBe(true)
    expect(wrapper.emitted('editing')?.slice(-1)[0]).toEqual([true])
    const useFullAmount = wrapper
      .findAllComponents({ name: 'VBtn' })
      .find((button) => button.text().includes('use_full_amount'))!
    useFullAmount.vm.$emit('click')
    await flushPromises()
    expect(wrapper.findComponent({ name: 'VTextField' }).props('modelValue')).toBe('10.345')
    expect(wrapper.emitted('editing')?.slice(-1)[0]).toEqual([true])
    wrapper.unmount()
  })
  it('requires reviewing the receiver and acknowledging before sending exact decimal strings', async () => {
    const wrapper = render()
    await flushPromises()
    wrapper.findAllComponents({ name: 'VBtn' })[0]!.vm.$emit('click')
    await flushPromises()
    wrapper.findComponent({ name: 'VTextField' }).vm.$emit('update:modelValue', '1.234')
    wrapper.findComponent({ name: 'VAutocomplete' }).vm.$emit('update:modelValue', 3)
    await flushPromises()
    await wrapper.find('v-form-stub').trigger('submit')
    expect(api.createHandover).not.toHaveBeenCalled()
    expect(wrapper.find('.finance-handovers__review').text()).toContain('Omar')
    await wrapper.find('v-form-stub').trigger('submit')
    expect(api.createHandover).not.toHaveBeenCalled()
    wrapper.findComponent({ name: 'VCheckbox' }).vm.$emit('update:modelValue', true)
    api.createHandover.mockResolvedValueOnce({ data: { body: { id: 1 } } })
    await wrapper.find('v-form-stub').trigger('submit')
    await flushPromises()
    expect(api.createHandover).toHaveBeenCalledWith(
      7,
      expect.objectContaining({ amount: '1.234', receiver_id: 3 }),
    )
    expect(wrapper.emitted('saved')).toHaveLength(1)
    wrapper.unmount()
  })

  it('rejects excess currency precision and values above the server available amount before review', async () => {
    const wrapper = render()
    await flushPromises()
    wrapper.findAllComponents({ name: 'VBtn' })[0]!.vm.$emit('click')
    await flushPromises()
    wrapper.findComponent({ name: 'VAutocomplete' }).vm.$emit('update:modelValue', 3)
    for (const amount of ['10.346', '1.2345', '-1', '0', '1e2']) {
      wrapper.findComponent({ name: 'VTextField' }).vm.$emit('update:modelValue', amount)
      await wrapper.find('v-form-stub').trigger('submit')
      expect(wrapper.find('.finance-handovers__review').exists()).toBe(false)
      expect(wrapper.findComponent({ name: 'VTextField' }).props('errorMessages')).toBeTruthy()
    }
    expect(api.createHandover).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('returns server field errors to the editable details without losing the draft', async () => {
    const wrapper = render()
    await flushPromises()
    wrapper.findAllComponents({ name: 'VBtn' })[0]!.vm.$emit('click')
    await flushPromises()
    wrapper.findComponent({ name: 'VTextField' }).vm.$emit('update:modelValue', '1.234')
    wrapper.findComponent({ name: 'VAutocomplete' }).vm.$emit('update:modelValue', 3)
    wrapper.findComponent({ name: 'VTextarea' }).vm.$emit('update:modelValue', 'Evening cash')
    await wrapper.find('v-form-stub').trigger('submit')
    wrapper.findComponent({ name: 'VCheckbox' }).vm.$emit('update:modelValue', true)
    api.createHandover.mockRejectedValueOnce({
      response: {
        status: 422,
        data: {
          message: 'Review the amount.',
          errors: { amount: ['The remaining cash changed.'] },
        },
      },
    })
    await wrapper.find('v-form-stub').trigger('submit')
    await flushPromises()
    const field = wrapper.findComponent({ name: 'VTextField' })
    expect(wrapper.find('.finance-handovers__review').exists()).toBe(false)
    expect(field.props('modelValue')).toBe('1.234')
    expect(field.props('errorMessages')).toEqual(['The remaining cash changed.'])
    expect(wrapper.findComponent({ name: 'VAutocomplete' }).attributes('modelvalue')).toBe('3')
    expect(wrapper.findComponent({ name: 'VTextarea' }).attributes('modelvalue')).toBe(
      'Evening cash',
    )
    field.vm.$emit('update:modelValue', '1.000')
    await flushPromises()
    expect(field.props('errorMessages')).toBeUndefined()
    await wrapper.find('v-form-stub').trigger('submit')
    await wrapper.find('v-form-stub').trigger('submit')
    expect(api.createHandover).toHaveBeenCalledTimes(1)
    wrapper.unmount()
  })

  it('keeps an uncertain submission on review and retries the same request', async () => {
    const wrapper = render()
    await flushPromises()
    wrapper.findAllComponents({ name: 'VBtn' })[0]!.vm.$emit('click')
    await flushPromises()
    wrapper.findComponent({ name: 'VTextField' }).vm.$emit('update:modelValue', '1.234')
    wrapper.findComponent({ name: 'VAutocomplete' }).vm.$emit('update:modelValue', 3)
    await wrapper.find('v-form-stub').trigger('submit')
    wrapper.findComponent({ name: 'VCheckbox' }).vm.$emit('update:modelValue', true)
    api.createHandover.mockRejectedValueOnce(new Error('Connection lost'))
    await wrapper.find('v-form-stub').trigger('submit')
    await flushPromises()
    const firstRequest = api.createHandover.mock.calls[0]![1]
    expect(wrapper.find('.finance-handovers__review').exists()).toBe(true)
    expect(wrapper.emitted('busy')?.slice(-1)[0]).toEqual([true])
    expect(
      wrapper
        .findAllComponents({ name: 'VBtn' })
        .some((button) => button.text().includes('cash_handovers.back')),
    ).toBe(false)
    const dismiss = wrapper
      .findAllComponents({ name: 'VBtn' })
      .find((button) => button.text().includes('cash_handovers.dismiss'))!
    dismiss.vm.$emit('click')
    await flushPromises()
    expect(wrapper.find('.finance-handovers__review').exists()).toBe(true)
    api.createHandover.mockResolvedValueOnce({ data: { body: { id: 1 } } })
    await wrapper.find('v-form-stub').trigger('submit')
    await flushPromises()
    expect(api.createHandover.mock.calls[1]![1]).toEqual(firstRequest)
    expect(wrapper.emitted('busy')?.slice(-1)[0]).toEqual([false])
    wrapper.unmount()
  })

  it('matches loading to the summary and does not allow opening a form before data arrives', async () => {
    api.handovers.mockReturnValue(new Promise(() => {}))
    const wrapper = render()
    await flushPromises()
    expect(wrapper.find('[role="status"]').attributes('aria-label')).toContain(
      'cash_handovers.loading',
    )
    expect(wrapper.find('.finance-handovers__cash').exists()).toBe(true)
    expect(wrapper.find('.finance-handovers__status').exists()).toBe(true)
    const action = wrapper.findComponent({ name: 'VBtn' })
    expect(action.attributes('disabled')).toBeDefined()
    action.vm.$emit('click')
    await flushPromises()
    expect(wrapper.findComponent({ name: 'VForm' }).exists()).toBe(false)
    wrapper.unmount()
  })

  it('dismisses only the form dialog and keeps the source summary visible', async () => {
    const wrapper = render()
    await flushPromises()
    wrapper.findAllComponents({ name: 'VBtn' })[0]!.vm.$emit('click')
    await flushPromises()
    expect(wrapper.findComponent({ name: 'VDialog' }).props('modelValue')).toBe(true)
    wrapper.findComponent({ name: 'VDialog' }).vm.$emit('update:modelValue', false)
    await flushPromises()
    expect(wrapper.findComponent({ name: 'VForm' }).exists()).toBe(false)
    expect(wrapper.find('.finance-handovers__cash').text()).toContain('JOD 10.345')
    expect(wrapper.emitted('editing')?.slice(-1)[0]).toEqual([false])
    wrapper.unmount()
  })

  it('keeps unavailable counted cash distinct from zero and offers no new handover', async () => {
    api.handovers.mockResolvedValueOnce({
      data: {
        body: {
          ...summary(),
          counted: null,
          available: null,
          available_decimal: null,
          can_create: false,
        },
      },
    })
    const wrapper = render()
    await flushPromises()
    expect(wrapper.find('.finance-handovers__balances').text()).toContain(
      'pos::pos_sessions.show.not_available',
    )
    expect(wrapper.findAllComponents({ name: 'VBtn' })).toHaveLength(0)
    wrapper.unmount()
  })
})
