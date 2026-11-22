import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ClosingPreview } from '@/modules/pos/contracts/sessionReconciliation'
import CloseDialog from './CloseDialog.vue'

const { closingPreview, close } = vi.hoisted(() => ({
  closingPreview: vi.fn(),
  close: vi.fn(),
}))
vi.mock('@/modules/pos/api/session.api', () => ({ closingPreview, close }))
vi.mock('vue-toastification', () => ({ useToast: () => ({ success: vi.fn() }) }))

const money = (amount: number) => ({
  amount,
  currency: 'USD',
  precision: 2,
  formatted: `$${amount.toFixed(2)}`,
})

function response(count?: string): { data: { body: ClosingPreview } } {
  return {
    data: {
      body: {
        id: 1,
        pos_register: { id: 1, name: 'Main register' },
        branch: { id: 1, name: 'Downtown' },
        opened_by: { id: 1, name: 'Alex' },
        closed_by: { id: null, name: null },
        status: { id: 'open', name: 'Open', color: 'success', icon: '' },
        opened_at: '2026-10-05 09:00',
        closed_at: null,
        opening_float: money(100),
        declared_cash: count ? money(Number(count)) : null,
        cash_over_short: count ? money(Number(count) - 500) : null,
        total_sales: money(400),
        total_collections: money(400),
        total_refunds: money(0),
        orders_count: 2,
        notes: null,
        cash_reconciliation: {
          cash_in: money(400),
          cash_out: money(0),
          expected_cash: money(500),
        },
        payment_reconciliation: null,
        review: {
          generated_at: '12:00',
          declared_cash_decimal: count ? Number(count).toFixed(2) : null,
          cash_difference: count ? money(Number(count) - 500) : null,
          allowed_difference: money(10),
          can_close: !!count,
          token: count ? 'a'.repeat(64) : null,
        },
      },
    },
  }
}

function pending<T>() {
  let resolve!: (value: T) => void
  let reject!: (reason: unknown) => void
  const promise = new Promise<T>((done, fail) => {
    resolve = done
    reject = fail
  })

  return { promise, resolve, reject }
}

const wrappers: VueWrapper[] = []

async function render() {
  const wrapper = mount(CloseDialog, {
    props: { modelValue: true, item: { id: 1 } },
    global: {
      plugins: [
        createI18n({
          legacy: false,
          locale: 'en',
          missingWarn: false,
          fallbackWarn: false,
          messages: {},
        }),
      ],
      stubs: {
        VDialog: { template: '<div role="dialog"><slot /></div>' },
        VCard: { template: '<div><slot /></div>' },
        VForm: { template: '<form><slot /></form>' },
        VAlert: { template: '<div role="alert"><slot /></div>' },
        VIcon: true,
        VBtn: {
          props: ['disabled', 'loading'],
          template:
            '<button :disabled="disabled || loading"><span v-if="loading" role="progressbar" /><slot /></button>',
        },
        VProgressCircular: { template: '<span role="progressbar" />' },
        VCheckbox: {
          props: ['modelValue', 'disabled'],
          emits: ['update:modelValue'],
          template:
            '<input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', $event.target.checked)" />',
        },
        PaymentBreakdown: true,
      },
    },
  })
  wrappers.push(wrapper)
  await flushPromises()

  return wrapper
}

function expectOnlyActionLoading(wrapper: VueWrapper, action: 'refresh' | 'retry' | 'submit') {
  const selector =
    action === 'refresh'
      ? '.closing-freshness button'
      : action === 'retry'
        ? '.closing-error button'
        : 'button[type="submit"]'
  expect(wrapper.findAll('[role="progressbar"]')).toHaveLength(1)
  expect(wrapper.get(selector).find('[role="progressbar"]').exists()).toBe(true)
  for (const button of wrapper.findAll('button')) {
    expect(button.attributes('disabled')).toBeDefined()
  }
}

async function reviewCash(wrapper: VueWrapper) {
  await wrapper.get('.cash-field input').setValue('500')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
}

describe('close session action feedback', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    closingPreview.mockImplementation((_id: number, count?: string) =>
      Promise.resolve(response(count)),
    )
    close.mockResolvedValue({ data: { message: 'Session closed' } })
  })

  afterEach(() => {
    for (const wrapper of wrappers.splice(0)) wrapper.unmount()
  })

  it('shows responsibility and requires delegated and variance explanations before confirmation', async () => {
    closingPreview.mockImplementation((_id: number, count?: string) => {
      const result = response(count)
      result.data.body.responsible_cashier = { id: 1, name: 'Responsible cashier' }
      Object.assign(result.data.body.review, {
        actor: { id: 2, name: 'Manager' },
        requires_closing_reason: true,
        requires_variance_reason: !!count,
      })
      return Promise.resolve(result)
    })
    const wrapper = await render()
    expect(wrapper.get('.drawer-custody').text()).toContain('Responsible cashier')
    await wrapper.get('.closing-reasons textarea').setValue('  Cashier is unavailable.  ')
    await reviewCash(wrapper)
    expect(wrapper.findAll('.closing-reasons textarea')).toHaveLength(2)
    await wrapper.get('input[type="checkbox"]').setValue(true)
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined()
    await wrapper.findAll('.closing-reasons textarea')[1]!.setValue('  Drawer shortage verified.  ')
    expect((wrapper.get('input[type="checkbox"]').element as HTMLInputElement).checked).toBe(false)
    await wrapper.get('input[type="checkbox"]').setValue(true)
    const request = pending<{ data: { message: string } }>()
    close.mockReturnValueOnce(request.promise)
    await wrapper.get('form').trigger('submit')
    expect(close).toHaveBeenCalledWith(1, {
      declared_cash: '500.00',
      review_token: 'a'.repeat(64),
      closing_reason: 'Cashier is unavailable.',
      variance_reason: 'Drawer shortage verified.',
    })
    for (const textarea of wrapper.findAll('textarea'))
      expect(textarea.attributes('disabled')).toBeDefined()
    expectOnlyActionLoading(wrapper, 'submit')
    request.resolve({ data: { message: 'Session closed' } })
    await flushPromises()
    expect(wrapper.emitted('saved')).toHaveLength(1)
  })

  it('clears an old variance explanation when the cash count changes', async () => {
    closingPreview.mockImplementation((_id: number, count?: string) => {
      const result = response(count)
      result.data.body.review.requires_variance_reason = !!count && count !== '500'
      return Promise.resolve(result)
    })
    const wrapper = await render()
    await wrapper.get('.cash-field input').setValue('499')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    await wrapper.get('.closing-reasons textarea').setValue('Recounted shortage.')
    await wrapper.get('.closing-actions button').trigger('click')
    await wrapper.get('.cash-field input').setValue('500')
    expect(wrapper.find('.closing-reasons textarea').exists()).toBe(false)
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.find('.closing-reasons textarea').exists()).toBe(false)
  })

  it('uses only the initial page loader and disables actions before data arrives', async () => {
    const request = pending<ReturnType<typeof response>>()
    closingPreview.mockReturnValueOnce(request.promise)
    const wrapper = await render()
    expect(wrapper.findAll('[role="progressbar"]')).toHaveLength(1)
    expect(wrapper.get('.closing-loading').find('[role="progressbar"]').exists()).toBe(true)
    for (const button of wrapper.findAll('button')) {
      expect(button.attributes('disabled')).toBeDefined()
      expect(button.find('[role="progressbar"]').exists()).toBe(false)
    }

    request.resolve(response())
    await flushPromises()
    expect(wrapper.find('[role="progressbar"]').exists()).toBe(false)
    expect(wrapper.get('.closing-dismiss').attributes('disabled')).toBeUndefined()
  })

  it('shows a loader only on refresh and blocks competing submissions', async () => {
    const wrapper = await render()
    await wrapper.get('.cash-field input').setValue('500')
    const request = pending<ReturnType<typeof response>>()
    closingPreview.mockReturnValueOnce(request.promise)
    await wrapper.get('.closing-freshness button').trigger('click')
    expectOnlyActionLoading(wrapper, 'refresh')
    expect(wrapper.get('.cash-field input').attributes('disabled')).toBeDefined()
    await wrapper.get('form').trigger('submit')
    expect(closingPreview).toHaveBeenCalledTimes(2)
    expect(close).not.toHaveBeenCalled()

    request.resolve(response())
    await flushPromises()
    expect(wrapper.find('[role="progressbar"]').exists()).toBe(false)
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeUndefined()
  })

  it('shows a loader only on review and clears it when the request fails', async () => {
    const wrapper = await render()
    const request = pending<ReturnType<typeof response>>()
    closingPreview.mockReturnValueOnce(request.promise)
    await reviewCash(wrapper)
    expectOnlyActionLoading(wrapper, 'submit')

    request.reject({ response: { status: 500, data: { message: 'Try again' } } })
    await flushPromises()
    expect(wrapper.find('[role="progressbar"]').exists()).toBe(false)
    expect(wrapper.get('.closing-error').text()).toContain('Try again')
    expect(wrapper.get('.closing-freshness button').attributes('disabled')).toBeUndefined()
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeUndefined()
  })

  it('shows a loader only on close and locks editing, refresh and dismissal', async () => {
    const wrapper = await render()
    await reviewCash(wrapper)
    await wrapper.get('input[type="checkbox"]').setValue(true)
    const request = pending<{ data: { message: string } }>()
    close.mockReturnValueOnce(request.promise)
    await wrapper.get('form').trigger('submit')
    expectOnlyActionLoading(wrapper, 'submit')
    expect(wrapper.get('input[type="checkbox"]').attributes('disabled')).toBeDefined()
    await wrapper.get('form').trigger('submit')
    expect(close).toHaveBeenCalledOnce()

    request.resolve({ data: { message: 'Session closed' } })
    await flushPromises()
    expect(wrapper.find('[role="progressbar"]').exists()).toBe(false)
    expect(wrapper.emitted('saved')).toHaveLength(1)
    expect(wrapper.emitted('update:modelValue')).toEqual([[false]])
  })

  it('keeps retry visible with its own loader without a second page loader', async () => {
    closingPreview.mockRejectedValueOnce(new Error('Offline'))
    const wrapper = await render()
    const message = wrapper.get('.closing-error').text()
    const request = pending<ReturnType<typeof response>>()
    closingPreview.mockReturnValueOnce(request.promise)
    await wrapper.get('.closing-error button').trigger('click')
    expectOnlyActionLoading(wrapper, 'retry')
    expect(wrapper.get('.closing-error').text()).toBe(message)
    expect(wrapper.find('.closing-loading').exists()).toBe(false)

    request.resolve(response())
    await flushPromises()
    expect(wrapper.find('.closing-error').exists()).toBe(false)
    expect(wrapper.find('[role="progressbar"]').exists()).toBe(false)
    expect(wrapper.get('.closing-freshness button').attributes('disabled')).toBeUndefined()
  })

  it('keeps the close action loading during automatic refresh after a conflict', async () => {
    const wrapper = await render()
    await reviewCash(wrapper)
    await wrapper.get('input[type="checkbox"]').setValue(true)
    const refreshRequest = pending<ReturnType<typeof response>>()
    closingPreview.mockReturnValueOnce(refreshRequest.promise)
    close.mockRejectedValueOnce({ response: { status: 409 } })
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expectOnlyActionLoading(wrapper, 'submit')
    expect(wrapper.get('.closing-error').text()).toContain('review_changed')

    refreshRequest.resolve(response())
    await flushPromises()
    expect(wrapper.find('[role="progressbar"]').exists()).toBe(false)
    expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false)
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeUndefined()
    expect(wrapper.emitted('saved')).toBeUndefined()
  })
})
