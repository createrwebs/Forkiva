import { defineComponent } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useCashHandovers } from './useCashHandovers'

const api = vi.hoisted(() => ({
  handovers: vi.fn(),
  createHandover: vi.fn(),
  receiveHandover: vi.fn(),
  cancelHandover: vi.fn(),
}))
vi.mock('../api/cashHandover.api', () => api)
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
function render() {
  let state!: ReturnType<typeof useCashHandovers>
  const wrapper = mount(
    defineComponent({
      props: { id: { type: Number, required: true } },
      setup(props) {
        state = useCashHandovers(() => props.id)
        return () => null
      },
    }),
    { props: { id: 7 } },
  )

  return {
    wrapper,
    get state() {
      return state
    },
  }
}

beforeEach(() => {
  vi.clearAllMocks()
  api.handovers.mockResolvedValue({
    data: { body: { currency: 'JOD', data: [], can_create: true } },
  })
})

describe('cash handover requests', () => {
  it('retries an uncertain creation with exactly the original payload and key', async () => {
    const { state, wrapper } = render()
    await flushPromises()
    api.createHandover.mockRejectedValueOnce(new Error('Connection lost'))
    expect(await state.create({ amount: '1.234', receiver_id: 3, notes: 'Till custody' })).toBe(
      false,
    )
    expect(state.uncertain.value).toBe(true)
    const original = api.createHandover.mock.calls[0]?.[1]
    expect(original.request_key).toMatch(/^[0-9a-f-]{36}$/)
    api.createHandover.mockResolvedValueOnce({ data: { body: { id: 1 } } })
    expect(await state.create({ amount: '9.999', receiver_id: 9 })).toBe(true)
    expect(api.createHandover.mock.calls[1]?.[1]).toEqual(original)
    expect(state.uncertain.value).toBe(false)
    expect(api.handovers).toHaveBeenCalledTimes(2)
    wrapper.unmount()
  })

  it('blocks concurrent submissions and preserves server field errors', async () => {
    const { state, wrapper } = render()
    await flushPromises()
    let reject!: (reason: unknown) => void
    api.createHandover.mockReturnValueOnce(
      new Promise((_resolve, fail) => {
        reject = fail
      }),
    )
    const saving = state.create({ amount: '2.000', receiver_id: 3 })
    expect(await state.create({ amount: '2.000', receiver_id: 3 })).toBe(false)
    expect(api.createHandover).toHaveBeenCalledTimes(1)
    reject({
      response: {
        status: 422,
        data: { message: 'No remaining cash', errors: { amount: ['Not available'] } },
      },
    })
    await saving
    expect(state.errors.value.amount).toEqual(['Not available'])
    expect(state.error.value).toBe('No remaining cash')
    expect(state.uncertain.value).toBe(false)
    wrapper.unmount()
  })

  it('clears stale session history and ignores a response arriving after session change', async () => {
    let resolveOld!: (value: unknown) => void
    api.handovers.mockReturnValueOnce(
      new Promise((resolve) => {
        resolveOld = resolve
      }),
    )
    const view = render()
    await view.wrapper.setProps({ id: 8 })
    await flushPromises()
    resolveOld({ data: { body: { currency: 'USD' } } })
    await flushPromises()
    expect(view.state.summary.value?.currency).toBe('JOD')
    expect(api.handovers.mock.calls[1]?.[0]).toBe(8)
    view.wrapper.unmount()
  })
})
