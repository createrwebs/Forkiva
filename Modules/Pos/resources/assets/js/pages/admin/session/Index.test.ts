import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import { createI18n } from 'vue-i18n'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { TableAction } from '@/modules/core/contracts/Table'
import Index from './Index.vue'

const { can } = vi.hoisted(() => ({ can: vi.fn() }))
vi.mock('@/modules/auth/composables/auth.ts', () => ({ useAuth: () => ({ can, user: {} }) }))
vi.mock('./Dialogs/OpenDialog.vue', () => ({ default: { template: '<div />' } }))
vi.mock('./Dialogs/CloseDialog.vue', () => ({
  default: { template: '<div data-test="close-dialog" />' },
}))

function render() {
  return mount(Index, {
    global: {
      plugins: [
        createI18n({
          legacy: false,
          locale: 'en',
          messages: {},
          missingWarn: false,
          fallbackWarn: false,
        }),
      ],
      stubs: {
        SmartDataTable: { name: 'SmartDataTable', props: ['actions'], template: '<div />' },
      },
    },
  })
}

beforeEach(() => {
  can.mockReturnValue(true)
  vi.stubGlobal('ref', ref)
  vi.stubGlobal('useRouter', () => ({ push: vi.fn() }))
})
afterEach(() => vi.unstubAllGlobals())

describe('session list closing custody', () => {
  it('hides and refuses the close action when the server denies custody', async () => {
    const wrapper = render()
    const action = (
      wrapper.findComponent({ name: 'SmartDataTable' }).props('actions') as TableAction[]
    ).find((action) => action.key === 'close')!
    const session = { id: 1, status: { id: 'open' }, can_close: false }
    expect(typeof action.hidden === 'function' && action.hidden(session)).toBe(true)
    await action.onClick?.(session)
    expect(wrapper.find('[data-test="close-dialog"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('requires the existing close permission and retains compatibility with older list responses', async () => {
    const wrapper = render()
    const action = (
      wrapper.findComponent({ name: 'SmartDataTable' }).props('actions') as TableAction[]
    ).find((action) => action.key === 'close')!
    const session = { id: 1, status: { id: 'open' } }
    can.mockReturnValue(false)
    expect(typeof action.hidden === 'function' && action.hidden(session)).toBe(true)
    await action.onClick?.(session)
    expect(wrapper.find('[data-test="close-dialog"]').exists()).toBe(false)
    can.mockReturnValue(true)
    expect(typeof action.hidden === 'function' && action.hidden(session)).toBe(false)
    expect(
      typeof action.hidden === 'function' &&
        action.hidden({ ...session, status: { id: 'closed' } }),
    ).toBe(true)
    await action.onClick?.(session)
    expect(wrapper.find('[data-test="close-dialog"]').exists()).toBe(true)
    wrapper.unmount()
  })
})
