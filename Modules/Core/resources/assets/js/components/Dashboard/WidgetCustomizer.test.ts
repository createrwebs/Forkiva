import { ref } from 'vue'
import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import WidgetCustomizer from './WidgetCustomizer.vue'

vi.mock('vuetify', () => ({
  useDisplay: () => ({ smAndDown: ref(true), width: ref(390) }),
}))

vi.mock('vuedraggable', () => ({
  default: { name: 'Draggable', template: '<div><slot /></div>' },
}))

const navigationDrawerStub = {
  name: 'VNavigationDrawer',
  props: ['modelValue', 'width'],
  template: '<aside><slot /></aside>',
}
const buttonStub = {
  name: 'VBtn',
  template: '<button type="button" @click="$emit(\'click\')"><slot /></button>',
}
const textFieldStub = {
  name: 'VTextField',
  props: ['modelValue'],
  emits: ['update:modelValue'],
  template:
    '<input :value="modelValue ?? \'\'" @input="$emit(\'update:modelValue\', $event.target.value || null)" />',
}
const labels = {
  title: 'Customize widgets',
  subtitle: 'Choose and arrange widgets.',
  search: 'Search widgets',
  visible: 'Visible',
  hidden: 'Hidden',
  dragHint: 'Drag widgets to reorder.',
  empty: 'No widgets match your search.',
  reset: 'Reset to default',
  done: 'Done',
  minimize: 'Minimize widget',
  expand: 'Expand widget',
}
const global = {
  stubs: {
    VNavigationDrawer: navigationDrawerStub,
    VBtn: buttonStub,
    VChip: true,
    VIcon: true,
    VSwitch: true,
    VTab: true,
    VTabs: true,
    VTextField: textFieldStub,
  },
}

describe('WidgetCustomizer', () => {
  it('uses the mobile width, shows Reset to default, and closes from Done', async () => {
    const wrapper = shallowMount(WidgetCustomizer, {
      props: {
        modelValue: true,
        definitions: [],
        layout: [],
        labels,
        widgetTitle: () => '',
        categoryTitle: () => '',
        dragLabel: (title: string) => `Move ${title}`,
      },
      global,
    })

    expect(wrapper.findComponent(navigationDrawerStub).props('width')).toBe(390)
    expect(wrapper.text()).toContain('Reset to default')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Done')!
      .trigger('click')
    expect(wrapper.emitted('update:modelValue')).toContainEqual([false])
  })

  it('restores all rows after clearing a search', async () => {
    const wrapper = shallowMount(WidgetCustomizer, {
      props: {
        modelValue: true,
        definitions: [
          {
            key: 'total_sales',
            category: 'sales',
            kind: 'metric',
            icon: 'tabler-cash',
            color: '#F57C00',
          },
        ],
        layout: [{ key: 'total_sales', visible: true, minimized: false, order: 0 }],
        labels,
        widgetTitle: () => 'Total sales',
        categoryTitle: () => 'Sales',
        dragLabel: (title: string) => `Move ${title}`,
      },
      global,
    })

    const search = wrapper.findComponent(textFieldStub)
    await search.setValue('sales')
    search.vm.$emit('update:modelValue', null)
    await wrapper.vm.$nextTick()

    expect(search.props('modelValue')).toBeNull()
    expect(wrapper.text()).not.toContain(labels.empty)
  })
})
