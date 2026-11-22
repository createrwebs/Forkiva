import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import DatabaseIndex from './Index.vue'

vi.mock('vue-i18n', () => ({
  useI18n: () => ({ t: (key: string) => key }),
}))

vi.mock('vuetify', async () => {
  const { ref } = await import('vue')

  return {
    useDisplay: () => ({ mdAndUp: ref(true) }),
  }
})

vi.mock('@/modules/tool/composables/databaseTool.ts', async () => {
  const { computed, ref } = await import('vue')
  const loading = ref(true)

  return {
    useDatabaseTool: () => ({
      backups: ref([]),
      database: ref(null),
      downloadLoadingByFile: ref({}),
      file: ref(null),
      isAnyActionRunning: computed(() => loading.value),
      loadData: vi.fn(),
      loading,
      restoreLoading: ref(false),
      restoreRowLoadingByFile: ref({}),
      runBackup: vi.fn(),
      runDownloadBackup: vi.fn(),
      runRestore: vi.fn(),
      runRestoreFromBackup: vi.fn(),
      tab: ref('backup'),
    }),
  }
})

describe('database tools loading state', () => {
  it('renders the database loader before capability data is available', () => {
    const wrapper = shallowMount(DatabaseIndex, {
      global: {
        stubs: {
          VAlert: true,
          VCard: {
            template: '<div><slot /></div>',
          },
          VChip: true,
          VIcon: true,
          VProgressCircular: true,
          VTab: true,
          VTabs: true,
          VWindow: true,
          VWindowItem: true,
        },
      },
    })

    expect(wrapper.find('.database-tool-loading-state').exists()).toBe(true)
    expect(wrapper.find('.database-tool-loader').exists()).toBe(true)
    expect(wrapper.find('.database-tool-shell').exists()).toBe(false)
  })
})
