import type { DataImport } from '@/modules/import/contracts/import.ts'
import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import ImportColumnMapping from './ImportColumnMapping.vue'
import ImportSummary from './ImportSummary.vue'
import ImportUploadPanel from './ImportUploadPanel.vue'

vi.mock('vue-i18n', () => ({
  useI18n: () => ({ t: (key: string) => key }),
}))

function record(): DataImport {
  return {
    id: 1,
    resource: 'menus',
    status: { id: 'uploaded', name: 'Uploaded' },
    mode: { id: 'create_only', name: 'Create only' },
    original_name: 'menus.xlsx',
    headers: ['Name', 'Description'],
    mapping: { name: 0 },
    context: {},
    total_rows: 10,
    processed_rows: 0,
    valid_rows: 0,
    invalid_rows: 0,
    new_rows: 0,
    update_rows: 0,
    skipped_rows: 0,
    imported_rows: 0,
    failed_rows: 0,
    failure_reason: null,
    has_error_file: false,
    has_original_file: true,
    created_at: '',
    started_at: null,
    finished_at: null,
  }
}

const global = {
  renderStubDefaultSlot: true,
  stubs: {
    VBtn: { template: '<button><slot /></button>' },
    VIcon: true,
    VChip: true,
    VSelect: true,
    VProgressCircular: true,
  },
}

describe('Import panels', () => {
  it('forwards files once and prevents uploads while busy', async () => {
    const wrapper = shallowMount(ImportUploadPanel, {
      props: { busy: false, history: [], historyLoading: false, templateDownloading: false },
      global,
    })
    const file = new File(['Name'], 'menus.csv')
    await wrapper.get('.import-dropzone').trigger('drop', { dataTransfer: { files: [file] } })
    expect(wrapper.emitted('upload')).toEqual([[file]])
    await wrapper.setProps({ busy: true })
    await wrapper.get('.import-dropzone').trigger('drop', { dataTransfer: { files: [file] } })
    expect(wrapper.emitted('upload')).toHaveLength(1)
    wrapper.unmount()
  })

  it('resumes the selected history item and disables history during upload', async () => {
    const item = record()
    const wrapper = shallowMount(ImportUploadPanel, {
      props: { busy: false, history: [item], historyLoading: false, templateDownloading: false },
      global,
    })
    await wrapper.get('.import-history-item').trigger('click')
    expect(wrapper.emitted('resume')).toEqual([[item]])
    await wrapper.setProps({ busy: true })
    expect(wrapper.get('.import-history-item').attributes('disabled')).toBeDefined()
    wrapper.unmount()
  })

  it('opens the file picker from the drop area and button while keeping drops', async () => {
    const wrapper = shallowMount(ImportUploadPanel, {
      props: { busy: false, history: [], historyLoading: false, templateDownloading: false },
      global,
    })
    const input = wrapper.get<HTMLInputElement>('.import-file-input').element
    const click = vi.spyOn(input, 'click')

    await wrapper.get('.import-dropzone').trigger('click')
    await wrapper.get('.import-dropzone button').trigger('click')
    await wrapper.get('.import-dropzone').trigger('keydown.enter')
    expect(click).toHaveBeenCalledTimes(3)

    await wrapper.setProps({ busy: true })
    await wrapper.get('.import-dropzone').trigger('click')
    expect(click).toHaveBeenCalledTimes(3)
    wrapper.unmount()
  })

  it('shows a centered history loader and disables template download while fetching', async () => {
    const wrapper = shallowMount(ImportUploadPanel, {
      props: { busy: false, history: [], historyLoading: true, templateDownloading: true },
      global,
    })
    expect(wrapper.find('.import-history-loading').exists()).toBe(true)
    expect(wrapper.find('.import-history-empty').exists()).toBe(false)
    expect(wrapper.get('.import-upload-options button').attributes('disabled')).toBeDefined()

    await wrapper.setProps({ historyLoading: false, templateDownloading: false })
    expect(wrapper.find('.import-history-loading').exists()).toBe(false)
    expect(wrapper.find('.import-history-empty').exists()).toBe(true)
    expect(wrapper.get('.import-upload-options button').attributes('disabled')).toBeUndefined()
    wrapper.unmount()
  })

  it('emits mapping changes without mutating the parent draft and gates invalid submissions', async () => {
    const mapping = { name: 0 }
    const wrapper = shallowMount(ImportColumnMapping, {
      props: {
        current: record(),
        fields: [{ key: 'name', label: 'Name', type: 'text', required: true }],
        modelValue: mapping,
        busy: false,
        locked: false,
        missingRequiredCount: 0,
        duplicateMapping: false,
      },
      global,
    })
    wrapper.getComponent({ name: 'VSelect' }).vm.$emit('update:modelValue', 1)
    expect(wrapper.emitted('update:modelValue')).toEqual([[{ name: 1 }]])
    expect(mapping).toEqual({ name: 0 })
    const submit = wrapper.findAll('button').find((button) => button.text().includes('stage_rows'))!
    await submit.trigger('click')
    expect(wrapper.emitted('save')).toHaveLength(1)
    await wrapper.setProps({ duplicateMapping: true })
    expect(submit.attributes('disabled')).toBeDefined()
    await wrapper.setProps({ duplicateMapping: false, missingRequiredCount: 1 })
    expect(submit.attributes('disabled')).toBeDefined()
    wrapper.unmount()
  })

  it('preserves failed-validation analytics and error styling', () => {
    const wrapper = shallowMount(ImportSummary, {
      props: {
        current: {
          ...record(),
          status: { id: 'validation_failed', name: 'Validation failed' },
          invalid_rows: 4,
          new_rows: 3,
          update_rows: 2,
          skipped_rows: 1,
        },
        validated: true,
        working: false,
        result: false,
        pollError: null,
      },
      global,
    })
    expect(wrapper.classes()).toContain('import-notice-error')
    expect(wrapper.findAll('.import-counts strong').map((count) => count.text())).toEqual([
      '3',
      '2',
      '1',
    ])
    expect(wrapper.text()).toContain('4 import::imports.invalid_short')
    wrapper.unmount()
  })

  it('removes the success badge when an import behavior change requires revalidation', () => {
    const wrapper = shallowMount(ImportSummary, {
      props: {
        current: { ...record(), status: { id: 'ready', name: 'Ready to import' } },
        validated: false,
        working: false,
        result: false,
        pollError: null,
      },
      global,
    })
    expect(wrapper.findComponent({ name: 'VChip' }).attributes('color')).toBe('primary')
    expect(wrapper.text()).toContain('import::imports.statuses.staged')
    expect(wrapper.find('.import-counts').exists()).toBe(false)
    wrapper.unmount()
  })
})
