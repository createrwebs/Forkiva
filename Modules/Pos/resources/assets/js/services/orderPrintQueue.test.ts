import { expect, it, vi } from 'vitest'
import { enqueueOrderPrint, pendingOrderPrints } from './orderPrintQueue'

it('keeps printing ordered and continues after a failed job', async () => {
  let reject!: (error: Error) => void
  const printer = new Promise<void>((_, fail) => {
    reject = fail
  })
  const onError = vi.fn()
  const next = vi.fn(async () => {})
  const first = enqueueOrderPrint(() => printer, onError)
  const second = enqueueOrderPrint(next, onError)
  expect(pendingOrderPrints.value).toBe(2)
  expect(next).not.toHaveBeenCalled()
  reject(new Error('offline'))
  await Promise.all([first, second])
  expect(onError).toHaveBeenCalledTimes(1)
  expect(next).toHaveBeenCalledTimes(1)
  expect(pendingOrderPrints.value).toBe(0)
})
