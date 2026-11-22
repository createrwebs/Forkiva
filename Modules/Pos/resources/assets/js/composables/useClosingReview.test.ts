import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, reactive } from 'vue'
import { createI18n } from 'vue-i18n'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ClosingPreview } from '@/modules/pos/contracts/sessionReconciliation'
import { useClosingReview } from './useClosingReview'

const { closingPreview, close, success } = vi.hoisted(() => ({
  closingPreview: vi.fn(),
  close: vi.fn(),
  success: vi.fn(),
}))
vi.mock('@/modules/pos/api/session.api', () => ({ closingPreview, close }))
vi.mock('vue-toastification', () => ({ useToast: () => ({ success }) }))
const money = (amount: number) => ({
  amount,
  currency: 'USD',
  precision: 2,
  formatted: `$${amount.toFixed(2)}`,
})
function response(count?: string, eligible = true) {
  return {
    data: {
      body: {
        id: 1,
        declared_cash: count ? money(Number(count)) : null,
        review: {
          generated_at: '12:00',
          declared_cash_decimal: count ? Number(count).toFixed(2) : null,
          cash_difference: count ? money(Number(count) - 500) : null,
          allowed_difference: money(10),
          can_close: !!count && eligible,
          token: count && eligible ? 'a'.repeat(64) : null,
        },
      } as ClosingPreview,
    },
  }
}
function pending<T>() {
  let resolve!: (value: T) => void
  const promise = new Promise<T>((done) => {
    resolve = done
  })
  return { promise, resolve }
}
async function setup() {
  const props = reactive({ id: 1, open: true })
  const onSaved = vi.fn()
  let flow!: ReturnType<typeof useClosingReview>
  const wrapper = mount(
    defineComponent({
      setup() {
        flow = useClosingReview(
          () => props.id,
          () => props.open,
          onSaved,
        )
        return () => null
      },
    }),
    {
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
      },
    },
  )
  await flushPromises()
  return { flow, props, wrapper, onSaved }
}
async function review(flow: ReturnType<typeof useClosingReview>, count = '500') {
  flow.declaredCash.value = count
  await flow.reviewCount()
}

describe('cashier closing review', () => {
  it('exposes server explanation errors without closing or discarding the entered explanation', async () => {
    const { flow, wrapper, onSaved } = await setup()
    const result = response('499')
    result.data.body.review.requires_variance_reason = true
    closingPreview.mockResolvedValueOnce(result)
    await review(flow, '499')
    flow.varianceReason.value = 'Verified shortage.'
    flow.acknowledged.value = true
    close.mockRejectedValueOnce({
      response: {
        status: 422,
        data: { errors: { variance_reason: ['Provide a detailed explanation.'] } },
      },
    })
    await flow.confirmClose()
    expect(flow.reasonErrors.value.variance_reason).toBe('Provide a detailed explanation.')
    expect(flow.varianceReason.value).toBe('Verified shortage.')
    expect(onSaved).not.toHaveBeenCalled()
    expect(flow.step.value).toBe('count')
    wrapper.unmount()
  })
  beforeEach(() => {
    vi.clearAllMocks()
    closingPreview.mockImplementation((_id: number, count?: string) =>
      Promise.resolve(response(count)),
    )
    close.mockResolvedValue({ data: { message: 'Session closed' } })
  })
  it('requires a server review and explicit acknowledgement before closing', async () => {
    const { flow, wrapper, onSaved } = await setup()
    expect(flow.declaredCash.value).toBe('')
    expect(flow.canConfirm.value).toBe(false)
    await flow.confirmClose()
    expect(close).not.toHaveBeenCalled()
    await review(flow, '495')
    expect(flow.step.value).toBe('confirm')
    await flow.confirmClose()
    expect(close).not.toHaveBeenCalled()
    flow.acknowledged.value = true
    await flow.confirmClose()
    expect(close).toHaveBeenCalledWith(1, { declared_cash: '495.00', review_token: 'a'.repeat(64) })
    expect(onSaved).toHaveBeenCalledOnce()
    wrapper.unmount()
  })
  it('does not accept empty, negative or malformed cash counts', async () => {
    const { flow, wrapper } = await setup()
    for (const count of ['', '-1', '1.2.3', 'abc']) {
      flow.declaredCash.value = count
      await flow.reviewCount()
      expect(flow.canReview.value).toBe(false)
      expect(flow.countError.value).toBeTruthy()
    }
    expect(closingPreview).toHaveBeenCalledTimes(1)
    wrapper.unmount()
  })
  it('keeps over-limit counts in the count step without a close token', async () => {
    const { flow, wrapper } = await setup()
    closingPreview.mockResolvedValueOnce(response('450', false))
    await review(flow, '450')
    flow.acknowledged.value = true
    expect(flow.step.value).toBe('count')
    expect(flow.canConfirm.value).toBe(false)
    await flow.confirmClose()
    expect(close).not.toHaveBeenCalled()
    wrapper.unmount()
  })
  it('cash edits clear the old comparison and invalidate confirmation', async () => {
    const { flow, wrapper } = await setup()
    await review(flow)
    flow.acknowledged.value = true
    expect(flow.canConfirm.value).toBe(true)
    flow.declaredCash.value = '499'
    expect(flow.canConfirm.value).toBe(false)
    expect(flow.acknowledged.value).toBe(false)
    expect(flow.preview.value?.review.cash_difference).toBeNull()
    expect(flow.preview.value?.review.token).toBeNull()
    wrapper.unmount()
  })
  it('ignores a delayed review response after the count changes', async () => {
    const { flow, wrapper } = await setup()
    const delayed = pending<ReturnType<typeof response>>()
    closingPreview.mockReturnValueOnce(delayed.promise)
    flow.declaredCash.value = '500'
    const request = flow.reviewCount()
    flow.declaredCash.value = '499'
    delayed.resolve(response('500'))
    await request
    expect(flow.step.value).toBe('count')
    expect(flow.preview.value?.review.token).toBeNull()
    wrapper.unmount()
  })
  it('refresh invalidates an already acknowledged review', async () => {
    const { flow, wrapper } = await setup()
    await review(flow)
    flow.acknowledged.value = true
    await flow.refresh()
    expect(flow.canConfirm.value).toBe(false)
    expect(flow.step.value).toBe('count')
    expect(flow.declaredCash.value).toBe('500')
    wrapper.unmount()
  })
  it('prevents duplicate close requests while saving', async () => {
    const { flow, wrapper } = await setup()
    await review(flow)
    flow.acknowledged.value = true
    const delayed = pending<{ data: { message: string } }>()
    close.mockReturnValueOnce(delayed.promise)
    const request = flow.confirmClose()
    await flow.confirmClose()
    expect(close).toHaveBeenCalledOnce()
    delayed.resolve({ data: { message: 'Closed' } })
    await request
    wrapper.unmount()
  })
  it('changed totals require a fresh review after a conflict', async () => {
    const { flow, wrapper, onSaved } = await setup()
    await review(flow)
    flow.acknowledged.value = true
    close.mockRejectedValueOnce({ response: { status: 409 } })
    await flow.confirmClose()
    expect(onSaved).not.toHaveBeenCalled()
    expect(flow.error.value).toContain('review_changed')
    expect(flow.step.value).toBe('count')
    expect(flow.canConfirm.value).toBe(false)
    expect(closingPreview).toHaveBeenLastCalledWith(1, undefined)
    wrapper.unmount()
  })
  it('shows precision errors beside the counted cash and allows correction', async () => {
    const { flow, wrapper } = await setup()
    closingPreview.mockRejectedValueOnce({
      response: { status: 422, data: { errors: { declared_cash: ['Use two decimal places'] } } },
    })
    await review(flow, '500.001')
    expect(flow.countError.value).toBe('Use two decimal places')
    expect(flow.step.value).toBe('count')
    await review(flow, '500')
    expect(flow.countError.value).toBe('')
    expect(flow.step.value).toBe('confirm')
    wrapper.unmount()
  })
  it('handles failed initial loading and allows retry', async () => {
    closingPreview.mockRejectedValueOnce(new Error('Offline'))
    const { flow, wrapper } = await setup()
    expect(flow.preview.value).toBeNull()
    expect(flow.canReview.value).toBe(false)
    expect(flow.error.value).toBeTruthy()
    await flow.refresh()
    expect(flow.preview.value).not.toBeNull()
    expect(flow.error.value).toBe('')
    wrapper.unmount()
  })
  it('unavailable sessions cannot be closed or refreshed', async () => {
    closingPreview.mockRejectedValueOnce({ response: { status: 403 } })
    const { flow, wrapper } = await setup()
    expect(flow.unavailable.value).toBe(true)
    await flow.refresh()
    expect(closingPreview).toHaveBeenCalledOnce()
    expect(flow.canConfirm.value).toBe(false)
    wrapper.unmount()
  })
  it('ignores results from an earlier session', async () => {
    const delayed = pending<ReturnType<typeof response>>()
    closingPreview.mockReturnValueOnce(delayed.promise)
    const { flow, props, wrapper } = await setup()
    props.id = 2
    await flushPromises()
    delayed.resolve(response('495'))
    await flushPromises()
    expect(flow.preview.value?.review.declared_cash_decimal).toBeNull()
    expect(flow.step.value).toBe('count')
    expect(flow.declaredCash.value).toBe('')
    wrapper.unmount()
  })
})
