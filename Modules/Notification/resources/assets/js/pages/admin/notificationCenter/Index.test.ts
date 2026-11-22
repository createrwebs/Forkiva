import { flushPromises, shallowMount } from '@vue/test-utils'
import { createPinia, defineStore, setActivePinia } from 'pinia'
import * as Vue from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import NotificationCenter from './Index.vue'

const mocks = vi.hoisted(() => ({
  summary: vi.fn(),
  list: vi.fn(),
  detail: vi.fn(),
  error: vi.fn(),
}))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('vue-toastification', () => ({ useToast: () => ({ error: mocks.error }) }))
vi.mock('vuetify', () => ({ useDisplay: () => ({ mdAndDown: Vue.ref(false) }) }))
vi.mock('@/modules/notification/stores/notificationCenterStore.ts', () => ({
  useNotificationCenterStore: defineStore('notification-test', () => ({
    actionLoading: Vue.ref(false),
    hasMore: Vue.ref(false),
    hasUnread: Vue.ref(false),
    items: Vue.ref([]),
    listLoading: Vue.ref(false),
    filters: Vue.ref({ page: 4 }),
    selectedItem: Vue.ref(null),
    unreadCount: Vue.ref(0),
    fetchSummary: mocks.summary,
    fetchNotifications: mocks.list,
    fetchNotification: mocks.detail,
  })),
}))

const route = Vue.reactive({ query: {} as Record<string, string> })
const Container = { template: '<div><slot /></div>' }

function mountCenter() {
  return shallowMount(NotificationCenter, {
    global: {
      mocks: { $t: (key: string) => key },
      stubs: {
        VCard: Container,
        VBtn: true,
        VDivider: true,
        VIcon: true,
        VList: true,
        VListItem: true,
        VListItemTitle: true,
        VMenu: true,
        VProgressCircular: true,
        VTab: true,
        VTabs: Container,
        VTextField: true,
      },
    },
  })
}

describe('notification request scheduling', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
    vi.useFakeTimers()
    route.query = {}
    mocks.summary.mockResolvedValue(undefined)
    mocks.list.mockResolvedValue(undefined)
    for (const name of [
      'ref',
      'computed',
      'watch',
      'nextTick',
      'onMounted',
      'onBeforeUnmount',
    ] as const) {
      vi.stubGlobal(name, Vue[name])
    }
    vi.stubGlobal('useRoute', () => route)
    vi.stubGlobal('useRouter', () => ({ replace: vi.fn() }))
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  it('loads the list while the summary request is still pending', async () => {
    let finishSummary!: () => void
    mocks.summary.mockReturnValue(
      new Promise<void>((resolve) => {
        finishSummary = resolve
      }),
    )
    const wrapper = mountCenter()
    await flushPromises()
    expect(mocks.summary).toHaveBeenCalledOnce()
    expect(mocks.list).toHaveBeenCalledOnce()
    finishSummary()
    await flushPromises()
    wrapper.unmount()
  })

  it('loads the list even when the summary fails', async () => {
    mocks.summary.mockRejectedValue(new Error('Unavailable'))
    const wrapper = mountCenter()
    await flushPromises()
    expect(mocks.list).toHaveBeenCalledOnce()
    expect(mocks.error).toHaveBeenCalledOnce()
    wrapper.unmount()
  })

  it('updates status, search, and selection without refetching unrelated data', async () => {
    const wrapper = mountCenter()
    await flushPromises()
    route.query = { status: 'read' }
    await flushPromises()
    expect(mocks.list).toHaveBeenLastCalledWith(
      expect.objectContaining({ page: 1, status: 'read' }),
      false,
    )
    expect(mocks.list).toHaveBeenCalledTimes(2)

    route.query = { status: 'read', search: 'invoice' }
    await Vue.nextTick()
    await vi.advanceTimersByTimeAsync(350)
    expect(mocks.list).toHaveBeenCalledTimes(3)
    expect(mocks.list).toHaveBeenLastCalledWith(
      expect.objectContaining({ search: 'invoice', page: 1 }),
      false,
    )

    mocks.detail.mockResolvedValue({ id: 'one', read_at: '2026-09-03' })
    route.query = { ...route.query, selected: 'one' }
    await flushPromises()
    expect(mocks.detail).toHaveBeenCalledExactlyOnceWith('one')
    expect(mocks.list).toHaveBeenCalledTimes(3)
    expect(mocks.summary).toHaveBeenCalledOnce()
    wrapper.unmount()
  })

  it('does not request an unindexed search shorter than three characters', async () => {
    const wrapper = mountCenter()
    await flushPromises()

    route.query = { search: 'em' }
    await Vue.nextTick()
    await vi.advanceTimersByTimeAsync(350)
    expect(mocks.list).toHaveBeenCalledOnce()

    route.query = { search: 'email' }
    await Vue.nextTick()
    await vi.advanceTimersByTimeAsync(350)
    expect(mocks.list).toHaveBeenCalledTimes(2)
    expect(mocks.list).toHaveBeenLastCalledWith(
      expect.objectContaining({ search: 'email', page: 1 }),
      false,
    )

    wrapper.unmount()
  })
})
