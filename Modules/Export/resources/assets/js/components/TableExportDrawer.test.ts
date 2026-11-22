import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { defineComponent, ref } from 'vue'
import TableExportDrawer from './TableExportDrawer.vue'

vi.mock('pinia', () => ({
  storeToRefs: () => ({ isRtl: ref(false) }),
}))

vi.mock('vue-i18n', () => ({
  useI18n: () => ({ t: (key: string) => key }),
}))

vi.mock('@/modules/core/stores/appStore.ts', () => ({
  useAppStore: () => ({}),
}))

const NavigationDrawerStub = defineComponent({
  inheritAttrs: false,
  template: '<aside v-bind="$attrs"><slot /></aside>',
})

describe('TableExportDrawer', () => {
  it('marks the temporary drawer so its scrim can be fixed to the viewport', () => {
    const wrapper = shallowMount(TableExportDrawer, {
      props: {
        modelValue: true,
        exports: [],
        exportMethods: [],
        filters: {
          format: null,
          is_downloaded: null,
        },
        loading: false,
        pagination: {
          current_page: 1,
          from: 0,
          last_page: 1,
          per_page: 10,
          to: 0,
          total: 0,
        },
        perPage: 10,
        downloadingById: {},
      },
      global: {
        stubs: {
          VAlert: true,
          VBadge: true,
          VBtn: true,
          VCard: true,
          VChip: true,
          VIcon: true,
          VMenu: true,
          VNavigationDrawer: NavigationDrawerStub,
          VProgressCircular: true,
          VSelect: true,
        },
      },
    })

    expect(wrapper.find('.export-history-drawer').exists()).toBe(true)
  })
})
