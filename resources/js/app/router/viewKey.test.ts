import { flushPromises, mount } from '@vue/test-utils'
import { computed, defineComponent, h, nextTick, onMounted } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter, RouterView, useRoute } from 'vue-router'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import AdminLayout from '@/app/layouts/AdminLayout.vue'
import { useAuthStore } from '@/modules/auth/stores/authStore.ts'

vi.mock('@/app/layouts/components/DefaultLayoutWithVerticalNav.vue', () => ({
  default: { template: '<main><slot /></main>' },
}))

describe('admin route component reuse', () => {
  beforeEach(() => {
    vi.stubGlobal('useRoute', useRoute)
    vi.stubGlobal('computed', computed)
  })
  afterEach(() => vi.unstubAllGlobals())

  it('reuses explicitly handled query changes, but remounts for other queries, records, and pages', async () => {
    const mounted = vi.fn()
    const Page = defineComponent({
      setup() {
        onMounted(mounted)
        return () => h('div', 'Page content')
      },
    })
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        {
          path: '/admin',
          component: AdminLayout,
          children: [
            {
              path: 'notifications',
              name: 'notifications',
              component: Page,
              meta: { reuseOnQueryChange: ['selected', 'status', 'search'] },
            },
            { path: 'records/:id', name: 'records', component: Page },
          ],
        },
      ],
    })
    await router.push('/admin/notifications')
    await router.isReady()
    const pinia = createPinia()
    setActivePinia(pinia)
    const wrapper = mount(defineComponent({ render: () => h(RouterView) }), {
      global: { plugins: [router, pinia] },
    })
    await flushPromises()
    expect(mounted).toHaveBeenCalledTimes(1)

    for (const query of [{ selected: 'one' }, { status: 'read' }, { search: 'invoice' }]) {
      await router.replace({ query })
      await flushPromises()
      expect(mounted).toHaveBeenCalledTimes(1)
    }

    await router.replace({ query: { page: '2' } })
    await flushPromises()
    expect(mounted).toHaveBeenCalledTimes(2)
    await router.push('/admin/records/1')
    await flushPromises()
    expect(mounted).toHaveBeenCalledTimes(3)
    await router.push('/admin/records/2')
    await flushPromises()
    expect(mounted).toHaveBeenCalledTimes(4)
    await router.replace({ query: { tab: 'details' } })
    await flushPromises()
    expect(mounted).toHaveBeenCalledTimes(5)

    const authStore = useAuthStore()
    authStore.activeAccountId = 42
    await nextTick()
    expect(mounted).toHaveBeenCalledTimes(6)
    wrapper.unmount()
  })
})
