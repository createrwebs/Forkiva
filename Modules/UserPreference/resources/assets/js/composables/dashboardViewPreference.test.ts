import { defineComponent, nextTick, onMounted, reactive, ref } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useDashboardViewPreference } from './dashboardViewPreference.ts'

const mocks = vi.hoisted(() => ({
  user: { id: 7 },
  serverLoaded: true,
  values: {} as Record<string, unknown>,
  save: vi.fn().mockResolvedValue(true),
  toastError: vi.fn(),
}))

vi.mock('@/modules/auth/stores/authStore.ts', () => ({
  useAuthStore: () => ({ getUser: mocks.user }),
}))
vi.mock('../stores/userPreferenceStore.ts', () => ({
  useUserPreferenceStore: () => mocks,
}))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('vue-toastification', () => ({
  useToast: () => ({ error: mocks.toastError }),
}))

function mountView() {
  const layout = ref([{ key: 'sales', visible: true, x: 0, y: 0 }])
  const filters = reactive({
    branch_id: null as number | null,
    from: '2026-09-01',
    to: '2026-09-27',
  })
  const host = defineComponent({
    setup() {
      const preference = useDashboardViewPreference('giftCardAnalytics')
      Object.assign(filters, preference.restoreFilters({ ...filters }))
      onMounted(() => preference.track(layout, () => ({ ...filters })))
      return { remoteLayout: preference.remoteLayout }
    },
    template: '<div />',
  })
  return { wrapper: mount(host), layout, filters }
}

describe('dashboard view preferences', () => {
  beforeEach(() => {
    mocks.user.id = 7
    mocks.serverLoaded = true
    mocks.values = {}
    mocks.save.mockClear()
    mocks.toastError.mockClear()
  })

  it('restores the server filter scope and saves later layout and filter changes', async () => {
    const remote = {
      layout: [{ key: 'sales', visible: true, x: 0, y: 0 }],
      filters: { branch_id: 4, from: '2026-09-01', to: '2026-09-27' },
    }
    mocks.values.giftCardAnalytics = remote
    const { wrapper, layout, filters } = mountView()
    await flushPromises()

    expect(wrapper.vm.remoteLayout).toEqual(remote.layout)
    expect(filters.branch_id).toBe(4)
    expect(mocks.save).not.toHaveBeenCalled()

    layout.value = [{ key: 'sales', visible: true, x: 3, y: 0 }]
    await nextTick()
    await flushPromises()
    expect(mocks.save).toHaveBeenLastCalledWith('giftCardAnalytics', {
      layout: layout.value,
      filters: { ...filters },
    })

    filters.from = '2026-09-10'
    await nextTick()
    await flushPromises()
    expect(mocks.save).toHaveBeenCalledTimes(2)
    wrapper.unmount()
  })

  it('migrates a local layout when no server preference exists', async () => {
    const { wrapper, layout } = mountView()
    await flushPromises()

    expect(mocks.save).toHaveBeenCalledWith('giftCardAnalytics', {
      layout: layout.value,
      filters: { branch_id: null, from: '2026-09-01', to: '2026-09-27' },
    })
    wrapper.unmount()
  })

  it('restores only known widget options with compatible values', () => {
    mocks.values.giftCardAnalytics = {
      layout: null,
      filters: { widget_options: { top: 10, table: { limit: 20 }, unknown: 50 } },
    }
    const host = defineComponent({
      setup() {
        const preference = useDashboardViewPreference('giftCardAnalytics')
        return {
          options: preference.restoreWidgetOptions({ top: 5, table: { limit: 5 }, other: 3 }),
        }
      },
      template: '<div />',
    })
    const wrapper = mount(host)
    expect(wrapper.vm.options).toEqual({ top: 10, table: { limit: 20 }, other: 3 })
    wrapper.unmount()
  })

  it('does not save an old account view after the active user changes', async () => {
    const { wrapper, layout } = mountView()
    await flushPromises()
    mocks.save.mockClear()
    mocks.user.id = 8
    layout.value = [{ key: 'sales', visible: false, x: 0, y: 0 }]
    await nextTick()
    await flushPromises()
    expect(mocks.save).not.toHaveBeenCalled()
    wrapper.unmount()
  })
})
