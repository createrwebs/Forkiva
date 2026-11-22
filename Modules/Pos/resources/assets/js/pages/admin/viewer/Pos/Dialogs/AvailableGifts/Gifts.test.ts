import type { UseCart } from '@/modules/cart/composables/cart.ts'
import { mount, flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import Gifts from './Gifts.vue'
import GiftItem from './GiftItem.vue'

const { confirm, success, error } = vi.hoisted(() => ({
  confirm: vi.fn(),
  success: vi.fn(),
  error: vi.fn(),
}))
vi.mock('@/modules/core/composables/confirmDialog.ts', () => ({ useConfirmDialog: confirm }))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('vue-toastification', () => ({ useToast: () => ({ success, error }) }))

function setup(applyGift = vi.fn()) {
  const wrapper = mount(Gifts, {
    props: {
      customerId: 1,
      gifts: [1, 2].map((id) => ({
        id,
        reward: { name: `Gift ${id}` },
        type: { name: 'Free item' },
      })),
      cart: { applyGift } as unknown as UseCart,
    },
    global: {
      stubs: {
        VIcon: true,
        VImg: true,
        VBtn: {
          name: 'VBtn',
          props: ['disabled', 'loading'],
          template: '<button :disabled="disabled"><slot /></button>',
        },
      },
    },
  })
  return { wrapper, buttons: () => wrapper.findAllComponents({ name: 'VBtn' }) }
}
beforeEach(() => {
  vi.clearAllMocks()
  confirm.mockResolvedValue(true)
})

describe('gift application feedback', () => {
  it('loads only the selected gift, blocks other gifts and duplicate events until completion', async () => {
    let finish!: (value: boolean) => void
    const applyGift = vi.fn(
      () =>
        new Promise<boolean>((resolve) => {
          finish = resolve
        }),
    )
    let confirmGift!: (value: boolean) => void
    confirm.mockImplementation(
      () =>
        new Promise<boolean>((resolve) => {
          confirmGift = resolve
        }),
    )
    const { wrapper, buttons } = setup(applyGift)
    await buttons()[0]!.trigger('click')
    await flushPromises()
    expect(buttons().every((button) => !button.props('loading') && !button.props('disabled'))).toBe(
      true,
    )
    expect(applyGift).not.toHaveBeenCalled()
    wrapper.findAllComponents(GiftItem)[1]!.vm.$emit('apply', { id: 2 })
    await flushPromises()
    expect(confirm).toHaveBeenCalledTimes(1)
    confirmGift(true)
    await flushPromises()
    expect(buttons()[0]!.props()).toMatchObject({ loading: true, disabled: false })
    expect(buttons()[1]!.props()).toMatchObject({ loading: false, disabled: true })
    wrapper.findAllComponents(GiftItem)[1]!.vm.$emit('apply', { id: 2 })
    await flushPromises()
    expect(applyGift).toHaveBeenCalledTimes(1)
    expect(applyGift).toHaveBeenCalledWith(1)
    finish(true)
    await flushPromises()
    expect(wrapper.emitted('applied')).toHaveLength(1)
    expect(buttons().every((button) => !button.props('loading') && !button.props('disabled'))).toBe(
      true,
    )
    wrapper.unmount()
  })
  it('clears loading and unlocks all gifts if application throws', async () => {
    const { wrapper, buttons } = setup(vi.fn().mockRejectedValue(new Error('offline')))
    await buttons()[1]!.trigger('click')
    await flushPromises()
    expect(error).toHaveBeenCalledOnce()
    expect(wrapper.emitted('applied')).toBeUndefined()
    expect(buttons().every((button) => !button.props('loading') && !button.props('disabled'))).toBe(
      true,
    )
    wrapper.unmount()
  })
  it('unlocks the gifts without applying when confirmation is canceled', async () => {
    confirm.mockResolvedValue(false)
    const applyGift = vi.fn()
    const { wrapper, buttons } = setup(applyGift)
    await buttons()[0]!.trigger('click')
    await flushPromises()
    expect(applyGift).not.toHaveBeenCalled()
    expect(buttons().every((button) => !button.props('loading') && !button.props('disabled'))).toBe(
      true,
    )
    wrapper.unmount()
  })
})
