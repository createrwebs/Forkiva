import type { DataImport, ImportRow } from '@/modules/import/contracts/import.ts'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import ImportSheet from './ImportSheet.vue'

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))

const VMenuStub = defineComponent({
  props: { modelValue: Boolean },
  emits: ['update:modelValue'],
  setup(props, { slots, emit }) {
    return () =>
      h('div', [
        slots.activator?.({
          props: { onClick: () => emit('update:modelValue', !props.modelValue) },
        }),
        props.modelValue ? slots.default?.() : null,
      ])
  },
})

const VDatePickerStub = defineComponent({
  props: { modelValue: String },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    return () =>
      h('div', { 'data-calendar-value': props.modelValue ?? '' }, [
        h(
          'button',
          { type: 'button', onClick: () => emit('update:modelValue', '1995-11-02') },
          'Pick date',
        ),
      ])
  },
})

function renderSheet(overrides: Partial<ImportRow> = {}, showOperations = true) {
  const row: ImportRow = {
    id: 1,
    row_number: 2,
    values: ['Original', '1'],
    data: { name: null, active: '1' },
    errors: null,
    operation: { id: 'create', name: 'Create record' },
    status: { id: 'valid', name: 'valid' },
    ...overrides,
  }
  return mount(ImportSheet, {
    props: {
      current: {
        headers: ['name', 'active'],
        status: { id: 'ready', name: 'ready' },
      } as DataImport,
      fields: [
        { key: 'name', label: 'Name', type: 'text', required: true },
        { key: 'active', label: 'Active', type: 'boolean', required: false },
      ],
      mapping: { name: 0, active: 1 },
      rows: [row],
      loading: false,
      error: null,
      editable: true,
      saving: false,
      showOperations,
      page: 1,
      lastPage: 1,
    },
    global: {
      stubs: {
        VIcon: true,
        VBtn: true,
        VProgressCircular: true,
        VTooltip: true,
        VMenu: VMenuStub,
        VDatePicker: VDatePickerStub,
      },
    },
  })
}

describe('ImportSheet', () => {
  it('keeps a cleared mapped value blank instead of showing the original value', () => {
    const wrapper = renderSheet()
    expect(wrapper.get('input').element.value).toBe('')
    wrapper.unmount()
  })

  it('exposes row errors on a focusable colored result badge', () => {
    const wrapper = renderSheet({
      errors: { _row: ['Duplicate record in this file.'] },
      status: { id: 'invalid', name: 'invalid' },
      operation: { id: 'invalid', name: 'invalid' },
    })
    const badge = wrapper.get('.import-result-badge')
    expect(badge.attributes('aria-label')).toContain('Duplicate record in this file.')
    expect(badge.attributes('tabindex')).toBe('0')
    expect(badge.classes()).toContain('import-result-error')
    wrapper.unmount()
  })

  it('hides stale planned operations until validation is current', () => {
    const wrapper = renderSheet({}, false)
    expect(wrapper.get('td.import-sheet-result').text()).toBe(
      'import::imports.row_statuses.pending',
    )
    wrapper.unmount()
  })

  it('displays translated operation and terminal status names from the API', () => {
    const planned = renderSheet()
    expect(planned.get('td.import-sheet-result').text()).toBe('Create record')
    planned.unmount()

    const completed = renderSheet({ status: { id: 'processed', name: 'Imported record' } }, false)
    expect(completed.get('td.import-sheet-result').text()).toBe('Imported record')
    completed.unmount()
  })

  it('shows an invalid choice in the cell but only valid choices in the menu', async () => {
    const wrapper = renderSheet({ data: { name: 'Unknown branch', active: '1' } })
    await wrapper.setProps({
      fields: [
        {
          key: 'name',
          label: 'Branch',
          type: 'integer',
          required: true,
          options: [
            { value: '12', label: 'Downtown' },
            { value: '19', label: 'Airport' },
          ],
        },
        { key: 'active', label: 'Active', type: 'boolean', required: true },
      ],
    })
    const trigger = wrapper.get('button[data-cell-column="0"]')
    expect(trigger.text()).toContain('Unknown branch')
    await trigger.trigger('click')
    expect(wrapper.get('[role="listbox"].import-choice-options').attributes('aria-label')).toBe(
      'Branch',
    )
    expect(wrapper.findAll('[role="option"]').map((option) => option.text())).toEqual([
      'Downtown',
      'Airport',
    ])
    expect(wrapper.get('[role="listbox"]').text()).not.toContain('Unknown branch')
    expect(wrapper.find('.import-choice-clear').exists()).toBe(false)
    await wrapper.findAll('[role="option"]')[1]!.trigger('click')
    expect(wrapper.emitted('saveCell')?.[0]?.[0]).toMatchObject({ data: { name: '19' } })
    expect(wrapper.emitted('saveCell')?.[0]?.[1]).toBe('name')
    wrapper.unmount()
  })

  it('limits row choices to matching branch and floor while showing the current choice label', async () => {
    const wrapper = renderSheet({
      data: { name: '6', active: '1', branch_id: '2', floor_id: '10' },
    })
    await wrapper.setProps({
      fields: [
        {
          key: 'name',
          label: 'Printer',
          type: 'integer',
          required: false,
          options: [
            { value: '6', label: 'Main printer', matches: { branch_id: '1' } },
            { value: '7', label: 'Other floor', matches: { branch_id: '2', floor_id: '11' } },
            { value: '8', label: 'Valid choice', matches: { branch_id: '2', floor_id: '10' } },
          ],
        },
        { key: 'active', label: 'Active', type: 'boolean', required: true },
      ],
    })

    const trigger = wrapper.get('button[data-cell-column="0"]')
    expect(trigger.text()).toContain('Main printer')
    await trigger.trigger('click')
    expect(wrapper.findAll('[role="option"]').map((option) => option.text())).toEqual([
      'Valid choice',
    ])
    await wrapper.get('[role="option"]').trigger('click')
    expect(wrapper.emitted('saveCell')?.[0]?.[0]).toMatchObject({ data: { name: '8' } })
    wrapper.unmount()
  })

  it('filters longer choice lists and supports keyboard navigation with a clear action', async () => {
    const wrapper = renderSheet({ data: { name: '12', active: '1' } })
    document.body.appendChild(wrapper.element)
    await wrapper.setProps({
      fields: [
        {
          key: 'name',
          label: 'Branch',
          type: 'integer',
          required: false,
          options: Array.from({ length: 10 }, (_, index) => ({
            value: String(index + 10),
            label: `Branch ${index + 10}`,
          })),
        },
        { key: 'active', label: 'Active', type: 'boolean', required: true },
      ],
    })
    await wrapper.get('button[data-cell-column="0"]').trigger('click')
    expect(wrapper.get('[role="option"][aria-selected="true"]').text()).toBe('Branch 12')
    expect(wrapper.find('.import-choice-clear').exists()).toBe(true)
    const search = wrapper.get('input[type="search"]')
    await search.setValue('Branch 19')
    expect(wrapper.findAll('[role="option"]').map((option) => option.text())).toEqual(['Branch 19'])
    await search.setValue('missing')
    expect(wrapper.findAll('[role="option"]')).toHaveLength(0)
    expect(wrapper.get('.import-choice-empty').text()).toBe('import::imports.choice_no_matches')
    await search.trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('saveCell')).toBeUndefined()
    expect(wrapper.find('[role="listbox"]').exists()).toBe(true)
    await search.setValue('Branch 18')
    await search.trigger('keydown', { key: 'ArrowDown' })
    expect(document.activeElement).toBe(wrapper.get('[role="option"]').element)
    await wrapper.get('input[type="search"]').setValue('Branch 19')
    await wrapper.get('input[type="search"]').trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('saveCell')?.[0]?.[0]).toMatchObject({ data: { name: '19' } })
    await wrapper.get('button[data-cell-column="0"]').trigger('click')
    await wrapper.get('.import-choice-clear').trigger('click')
    expect(wrapper.emitted('saveCell')?.[1]?.[0]).toMatchObject({ data: { name: '' } })
    wrapper.unmount()
  })

  it('selects multiple choices and removes invalid imported values', async () => {
    const wrapper = renderSheet({ data: { name: 'unknown, drinks', active: '1' } })
    await wrapper.setProps({
      fields: [
        {
          key: 'name',
          label: 'Categories',
          type: 'text',
          required: false,
          multiple: true,
          options: [
            { value: 'drinks', label: 'Drinks' },
            { value: 'desserts', label: 'Desserts' },
          ],
        },
        { key: 'active', label: 'Active', type: 'boolean', required: true },
      ],
    })

    expect(wrapper.get('button[data-cell-column="0"]').text()).toContain('unknown, Drinks')
    await wrapper.get('button[data-cell-column="0"]').trigger('click')
    expect(wrapper.get('[role="listbox"]').attributes('aria-multiselectable')).toBe('true')
    await wrapper.findAll('[role="option"]')[1]!.trigger('click')
    expect(wrapper.find('[role="listbox"]').exists()).toBe(true)
    expect(wrapper.emitted('saveCell')?.[0]?.[0]).toMatchObject({
      data: { name: 'drinks|desserts' },
    })
    expect(wrapper.get('button[data-cell-column="0"]').text()).toContain('Drinks, Desserts')
    await wrapper.findAll('[role="option"]')[0]!.trigger('click')
    expect(wrapper.emitted('saveCell')?.[1]?.[0]).toMatchObject({
      data: { name: 'desserts' },
    })
    await wrapper.get('.import-choice-clear').trigger('click')
    expect(wrapper.emitted('saveCell')?.[2]?.[0]).toMatchObject({ data: { name: '[]' } })
    expect(wrapper.get('button[data-cell-column="0"]').text()).toContain('—')
    await wrapper.get('button[data-cell-column="0"]').trigger('click')
    expect(wrapper.find('[aria-selected="true"]').exists()).toBe(false)
    expect(wrapper.find('.import-choice-clear').exists()).toBe(false)
    await wrapper.findAll('[role="option"]')[0]!.trigger('click')
    await wrapper.findAll('[role="option"]')[0]!.trigger('click')
    expect(wrapper.emitted('saveCell')?.[4]?.[0]).toMatchObject({ data: { name: '[]' } })
    wrapper.unmount()
  })

  it('can explicitly clear a blank optional multi-select during an upsert', async () => {
    const wrapper = renderSheet({ data: { name: null, active: '1' } })
    await wrapper.setProps({
      fields: [
        {
          key: 'name',
          label: 'Categories',
          type: 'text',
          required: false,
          multiple: true,
          options: [{ value: 'drinks', label: 'Drinks' }],
        },
        { key: 'active', label: 'Active', type: 'boolean', required: true },
      ],
    })
    await wrapper.get('button[data-cell-column="0"]').trigger('click')
    await wrapper.get('.import-choice-clear').trigger('click')
    expect(wrapper.emitted('saveCell')?.[0]?.[0]).toMatchObject({ data: { name: '[]' } })
    wrapper.unmount()
  })

  it('clears optional text and integer cells while keeping required cells without a clear action', async () => {
    const wrapper = renderSheet({ data: { name: 'Product', active: '7' } })
    await wrapper.setProps({
      fields: [
        { key: 'name', label: 'Name', type: 'text', required: true },
        { key: 'active', label: 'Quantity', type: 'integer', required: false },
      ],
    })
    expect(wrapper.findAll('.import-text-clear')).toHaveLength(1)
    expect(wrapper.get('.import-text-clear').attributes('aria-label')).toContain('Quantity')
    await wrapper.get('.import-text-clear').trigger('click')
    expect(wrapper.emitted('saveCell')?.[0]?.[0]).toMatchObject({ data: { active: '' } })
    expect((wrapper.get('input[data-cell-column="1"]').element as HTMLInputElement).value).toBe('')
    expect(wrapper.find('.import-text-clear').exists()).toBe(false)
    wrapper.unmount()
  })

  it('shows the selected cell address and its validation message', async () => {
    const wrapper = renderSheet({ errors: { name: ['Name already exists.'] } })
    await wrapper.get('input').trigger('focus')
    expect(wrapper.get('[role="status"]').text()).toContain('A2')
    expect(wrapper.get('[role="status"]').text()).toContain('Name already exists.')
    wrapper.unmount()
  })

  it('emits only changed cell values for saving', async () => {
    const wrapper = renderSheet()
    await wrapper.get('input').setValue('Corrected')
    expect(wrapper.emitted('saveCell')).toHaveLength(1)
    expect(wrapper.emitted('saveCell')?.[0]?.[1]).toBe('name')
    await wrapper.get('input').trigger('change')
    expect(wrapper.emitted('saveCell')).toHaveLength(1)
    wrapper.unmount()
  })

  it('opens a custom date picker with no selection for an invalid raw date', async () => {
    const wrapper = renderSheet({ data: { name: '35005', active: '1' } })
    await wrapper.setProps({
      fields: [
        { key: 'name', label: 'Birthdate', type: 'date', required: false },
        { key: 'active', label: 'Active', type: 'boolean', required: false },
      ],
    })
    const trigger = wrapper.get('button[data-cell-column="0"]')
    expect(trigger.text()).toContain('35005')
    await trigger.trigger('click')
    expect(wrapper.get('[data-calendar-value]').attributes('data-calendar-value')).toBe('')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Pick date')!
      .trigger('click')
    expect(wrapper.emitted('saveCell')?.[0]?.[0]).toMatchObject({
      data: { name: '1995-11-02' },
    })
    expect(wrapper.emitted('saveCell')?.[0]?.[1]).toBe('name')
    await wrapper.get('button[data-cell-column="0"]').trigger('click')
    await wrapper.get('.import-menu-clear').trigger('click')
    expect(wrapper.emitted('saveCell')?.[1]?.[0]).toMatchObject({ data: { name: '' } })
    wrapper.unmount()
  })
})
