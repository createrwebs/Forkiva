import { describe, expect, it, vi } from 'vitest'
import { usePaymentSettlementMutation } from './usePaymentSettlementMutation'

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))

describe('settlement mutation retries', () => {
  it('retries the exact original payload after an uncertain response', async () => {
    const submit = vi
      .fn()
      .mockRejectedValueOnce(new Error('Disconnected'))
      .mockResolvedValueOnce({ data: { body: { id: 1 } } })
    const state = usePaymentSettlementMutation(submit)
    const payload = { amount: '10.345', request_key: 'original', nested: { reference: 'BANK-1' } }
    expect(await state.save(payload)).toBeNull()
    expect(state.uncertain.value).toBe(true)
    payload.nested.reference = 'Changed'
    await state.save({ amount: '20.000', request_key: 'changed' })
    expect(submit.mock.calls[1]![0]).toEqual({
      amount: '10.345',
      request_key: 'original',
      nested: { reference: 'BANK-1' },
    })
    expect(state.uncertain.value).toBe(false)
  })

  it('prevents concurrent submissions and permits corrections after a validation rejection', async () => {
    let reject!: (reason: unknown) => void
    const submit = vi
      .fn()
      .mockImplementationOnce(
        () =>
          new Promise((_resolve, fail) => {
            reject = fail
          }),
      )
      .mockResolvedValueOnce({ data: { body: { id: 1 } } })
    const state = usePaymentSettlementMutation(submit)
    const first = state.save({ amount: '0' })
    expect(await state.save({ amount: '1' })).toBeNull()
    expect(submit).toHaveBeenCalledTimes(1)
    reject({
      response: {
        status: 422,
        data: { message: 'Invalid amount', errors: { amount: ['Invalid'] } },
      },
    })
    await first
    expect(state.uncertain.value).toBe(false)
    expect(state.errors.value.amount).toEqual(['Invalid'])
    await state.save({ amount: '2' })
    expect(submit.mock.calls[1]![0]).toEqual({ amount: '2' })
  })
})
