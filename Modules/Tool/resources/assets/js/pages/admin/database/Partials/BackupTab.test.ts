import { defineComponent } from 'vue'
import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import type { DatabaseBackup, DatabaseCapabilities } from '@/modules/tool/contracts/DatabaseTool.ts'
import BackupTab from './BackupTab.vue'

vi.mock('vue-i18n', () => ({
  useI18n: () => ({ t: (key: string) => key }),
}))

vi.mock('@/modules/auth/composables/auth.ts', () => ({
  useAuth: () => ({ can: () => true }),
}))

const database: DatabaseCapabilities = {
  driver: 'mysql',
  supported: true,
  backup_available: true,
  restore_available: true,
  missing_tools: [],
  format: 'sql',
  max_upload_mb: 100,
  safety_backup_required: true,
  maintenance_guard: true,
  queue_guard: true,
  lock_scope: 'single_node',
  retention_count: 10,
  preflight_ready: true,
  readiness_blockers: [],
}

const backup: DatabaseBackup = {
  name: 'backup.sql',
  size: 10,
  size_human: '10 B',
  created_at: '2026-08-29 16:00:00',
  driver: 'mysql',
  format: 'sql',
  checksum_sha256: '1234567890abcdef',
  engine: 'mysql',
}

const buttonStub = defineComponent({
  name: 'VBtn',
  props: {
    disabled: Boolean,
    loading: Boolean,
  },
  template: '<button :disabled="disabled"><slot /></button>',
})

describe('database backup action lock', () => {
  it('disables every competing action while an action is running', () => {
    const wrapper = shallowMount(BackupTab, {
      props: {
        backups: [backup],
        database,
        loading: false,
        isAnyActionRunning: true,
        downloadLoadingByFile: {},
        restoreRowLoadingByFile: {},
      },
      global: {
        stubs: {
          VAlert: { template: '<div><slot /></div>' },
          VBtn: buttonStub,
          VCardText: { template: '<div><slot /></div>' },
          VChip: { template: '<span><slot /></span>' },
          VIcon: true,
          VTable: { template: '<table><slot /></table>' },
        },
      },
    })

    const buttons = wrapper.findAll('button')

    expect(buttons).toHaveLength(3)
    expect(buttons.every((button) => button.attributes('disabled') !== undefined)).toBe(true)
  })
})
