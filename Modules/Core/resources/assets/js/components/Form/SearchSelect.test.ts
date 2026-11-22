import { mount } from '@vue/test-utils'
import { defineComponent, nextTick } from 'vue'
import { afterEach, describe, expect, it, vi } from 'vitest'
import SearchSelect from './SearchSelect.vue'

const ContainerStub = defineComponent({ template: '<div><slot /></div>' })
const TextFieldStub = defineComponent({
  inheritAttrs: false,
  props: {
    loading: Boolean,
    modelValue: { type: [String, Number], default: '' },
    placeholder: { type: String, default: '' },
    prefix: { type: String, default: undefined },
    readonly: Boolean,
    suffix: { type: String, default: undefined },
  },
  emits: ['click', 'click:clear', 'update:modelValue'],
  template: `
    <div>
      <input
        v-bind="$attrs"
        :class="readonly ? 'select-activator' : 'select-search'"
        :data-loading="String(Boolean(loading))"
        :placeholder="placeholder"
        :readonly="readonly"
        :value="modelValue"
        @click="$emit('click')"
        @input="$emit('update:modelValue', $event.target.value)"
      >
      <button class="select-clear" @click.stop="$emit('click:clear')">clear</button>
    </div>
  `,
})
const ListItemStub = defineComponent({
  emits: ['click', 'mouseenter'],
  template: '<button class="select-item" @click="$emit(\'click\')"><slot /></button>',
})
const wrappers: ReturnType<typeof mount>[] = []

function mountSelect(props: Record<string, unknown> = {}) {
  const wrapper = mount(SearchSelect, {
    props: {
      modelValue: null,
      items: [
        { id: 1, name: 'Alice' },
        { id: 2, name: 'Bob' },
      ],
      ...props,
    },
    global: {
      stubs: {
        teleport: true,
        transition: false,
        VCard: ContainerStub,
        VCardText: ContainerStub,
        VCheckboxBtn: true,
        VDivider: true,
        VList: ContainerStub,
        VListItem: ListItemStub,
        VListItemTitle: ContainerStub,
        VTextField: TextFieldStub,
      },
    },
  })
  wrappers.push(wrapper)
  return wrapper
}

afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
  vi.restoreAllMocks()
})

describe('SearchSelect', () => {
  it('opens the custom search, filters items, and selects the keyboard-active result', async () => {
    const wrapper = mountSelect()

    wrapper.vm.open()
    await nextTick()

    const search = wrapper.get('input.select-search')
    await search.setValue('Bob')

    expect(wrapper.text()).toContain('Bob')
    expect(wrapper.text()).not.toContain('Alice')
    expect(wrapper.emitted('search')).toEqual([['Bob']])

    await search.trigger('keydown.enter')
    await nextTick()

    expect(wrapper.emitted('update:modelValue')).toEqual([[2]])
    expect(wrapper.emitted('search')).toEqual([['Bob'], ['']])
  })

  it('uses a dedicated search value without changing the selected title', async () => {
    const item = {
      id: 7,
      label: 'Home',
      searchable: 'Home Jane 0790000000 Amman',
    }
    const wrapper = mountSelect({
      itemSearch: 'searchable',
      itemTitle: 'label',
      items: [item],
      modelValue: 7,
    })

    expect((wrapper.get('input.select-activator').element as HTMLInputElement).value).toBe('Home')

    wrapper.vm.open()
    await nextTick()
    await wrapper.get('input.select-search').setValue('0790000000')

    expect(wrapper.findAll('.select-item')).toHaveLength(1)
    expect(wrapper.text()).toContain('Home')
  })

  it('selects a filtered item with a pointer click', async () => {
    const wrapper = mountSelect()

    wrapper.vm.open()
    await nextTick()
    await wrapper.get('input.select-search').setValue('Alice')
    await wrapper.get('button.select-item').trigger('click')
    await nextTick()

    expect(wrapper.emitted('update:modelValue')).toEqual([[1]])
    expect(wrapper.emitted('search')).toEqual([['Alice'], ['']])
  })

  it('shows external loading state and emits an empty query when search is cleared', async () => {
    const wrapper = mountSelect({ loading: true })

    wrapper.vm.open()
    await nextTick()

    const search = wrapper.get('input.select-search')
    expect(search.attributes('data-loading')).toBe('true')

    await search.setValue('Alice')
    const clearButtons = wrapper.findAll('button.select-clear')
    await clearButtons[clearButtons.length - 1]!.trigger('click')

    expect(wrapper.emitted('search')).toEqual([['Alice'], ['']])
  })

  it('uses the configured minimum panel width without shrinking the activator', async () => {
    const wrapper = mountSelect({ panelMinWidth: 440 })
    const root = wrapper.get('.search-select').element as HTMLElement

    vi.spyOn(document.documentElement, 'clientWidth', 'get').mockReturnValue(1200)
    vi.spyOn(document.documentElement, 'clientHeight', 'get').mockReturnValue(800)
    vi.spyOn(root, 'getBoundingClientRect').mockReturnValue({
      bottom: 150,
      height: 50,
      left: 100,
      right: 324,
      top: 100,
      width: 224,
      x: 100,
      y: 100,
      toJSON: () => ({}),
    })

    wrapper.vm.open()
    await nextTick()

    expect(wrapper.get('.search-select__panel').attributes('style')).toContain('width: 440px')
    expect(root.style.width).toBe('')
  })
})
