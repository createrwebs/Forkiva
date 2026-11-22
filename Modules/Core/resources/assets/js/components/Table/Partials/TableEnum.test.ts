import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { defineComponent } from 'vue'
import TableEnum from './TableEnum.vue'

const ChipStub = defineComponent({
  props: ['color'],
  template: '<div class="enum-chip" :data-color="color"><slot /></div>',
})

const IconStub = defineComponent({
  props: ['icon'],
  template: '<i class="enum-icon" :data-icon="icon" />',
})

describe('TableEnum tax transaction sides', () => {
  it.each([
    ['output', 'Output tax', 'success', 'tabler-arrow-up-right'],
    ['input', 'Input tax', 'info', 'tabler-arrow-down-left'],
  ])('renders %s with the standard enum treatment', (id, name, color, icon) => {
    const wrapper = mount(TableEnum, {
      props: { item: { side: { id, name } }, column: 'side' },
      global: { stubs: { VChip: ChipStub, VIcon: IconStub } },
    })

    expect(wrapper.get('.enum-chip').attributes('data-color')).toBe(color)
    expect(wrapper.get('.enum-icon').attributes('data-icon')).toBe(icon)
    expect(wrapper.text()).toContain(name)
  })

  it.each([
    ['gross_payment', 'Gross payment', 'primary', 'tabler-cash'],
    ['allocated_amount', 'Allocated supplier bills', 'info', 'tabler-files'],
  ])('renders the %s withholding basis with its enum treatment', (id, name, color, icon) => {
    const wrapper = mount(TableEnum, {
      props: { item: { basis: { id, name } }, column: 'basis' },
      global: { stubs: { VChip: ChipStub, VIcon: IconStub } },
    })

    expect(wrapper.get('.enum-chip').attributes('data-color')).toBe(color)
    expect(wrapper.get('.enum-icon').attributes('data-icon')).toBe(icon)
    expect(wrapper.text()).toContain(name)
  })

  it.each([
    ['issued', 'Issued', 'info', 'tabler-certificate'],
    ['remitted', 'Remitted', 'success', 'tabler-building-bank'],
    ['void', 'Void', 'error', 'tabler-ban'],
    ['posted', 'Posted', 'success', 'tabler-circle-check'],
    ['reversed', 'Reversed', 'warning', 'tabler-arrow-back-up'],
  ])('renders the %s withholding status with its enum treatment', (id, name, color, icon) => {
    const wrapper = mount(TableEnum, {
      props: { item: { status: { id, name } }, column: 'status' },
      global: { stubs: { VChip: ChipStub, VIcon: IconStub } },
    })

    expect(wrapper.get('.enum-chip').attributes('data-color')).toBe(color)
    expect(wrapper.get('.enum-icon').attributes('data-icon')).toBe(icon)
    expect(wrapper.text()).toContain(name)
  })
})

describe('TableEnum inventory costing methods', () => {
  it.each([
    ['moving_average', 'Moving average', 'primary'],
    ['fifo', 'FIFO', 'info'],
    ['standard', 'Standard cost', 'warning'],
  ])('renders %s with a distinct enum color', (id, name, color) => {
    const wrapper = mount(TableEnum, {
      props: { item: { method: { id, name } }, column: 'method' },
      global: { stubs: { VChip: ChipStub, VIcon: IconStub } },
    })

    expect(wrapper.get('.enum-chip').attributes('data-color')).toBe(color)
    expect(wrapper.text()).toContain(name)
  })
})

describe('TableEnum landed-cost allocation bases', () => {
  it.each([
    ['quantity', 'Quantity', 'primary', 'tabler-packages'],
    ['value', 'Inventory value', 'success', 'tabler-currency-dollar'],
    ['custom', 'Custom driver', 'info', 'tabler-adjustments'],
  ])('renders %s with a distinct enum treatment', (id, name, color, icon) => {
    const wrapper = mount(TableEnum, {
      props: { item: { basis: { id, name } }, column: 'basis' },
      global: { stubs: { VChip: ChipStub, VIcon: IconStub } },
    })

    expect(wrapper.get('.enum-chip').attributes('data-color')).toBe(color)
    expect(wrapper.get('.enum-icon').attributes('data-icon')).toBe(icon)
    expect(wrapper.text()).toContain(name)
  })
})
