import { flushPromises, shallowMount } from '@vue/test-utils'
import { ref } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import ProductDialog from '@/modules/pos/pages/admin/viewer/Pos/MenuPanel/Products/Dialogs/ProductDialog.vue'
import Options from '@/modules/pos/pages/admin/viewer/Pos/MenuPanel/Products/Dialogs/Options.vue'

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))

function mountEditor() {
  const initialOptions = { 1: [10] }
  const cart = {
    processing: ref(false),
    isProductMutating: vi.fn(() => false),
    updateItemOptions: vi.fn().mockResolvedValue(true),
    storeItem: vi.fn(),
    showError: vi.fn(),
  }
  const container = { template: '<div><slot /></div>' }
  const wrapper = shallowMount(ProductDialog, {
    props: {
      modelValue: true,
      lineId: 'line-one',
      initialOptions,
      cart,
      product: {
        id: 1,
        name: 'Burger',
        price: { amount: 5, formatted: '5.000' },
        selling_price: { amount: 5, formatted: '5.000' },
        options: [{ id: 1, type: { id: 'multiple_select' }, values: [] }],
      },
    } as any,
    global: {
      stubs: {
        VDialog: container,
        VCard: container,
        VCardText: container,
        VCardActions: container,
        VBtn: { template: '<button><slot /></button>' },
        VIcon: true,
      },
    },
  })
  return { wrapper, cart, initialOptions }
}

describe('editing product options', () => {
  it('prefills the saved selections and updates the existing line without adding a product', async () => {
    const { wrapper, cart } = mountEditor()
    expect(wrapper.getComponent(Options).props('modelValue')).toEqual({ 1: [10] })
    wrapper.getComponent(Options).vm.$emit('update:modelValue', { 1: [11] })
    await flushPromises()
    await wrapper.get('.pos-product-dialog-add').trigger('click')
    await flushPromises()
    expect(cart.updateItemOptions).toHaveBeenCalledWith('line-one', { 1: [11] })
    expect(cart.storeItem).not.toHaveBeenCalled()
    expect(wrapper.emitted('update:modelValue')).toEqual([[false]])
  })

  it('discards cancelled selections and restores saved values on reopening', async () => {
    const { wrapper, cart, initialOptions } = mountEditor()
    wrapper.getComponent(Options).vm.$emit('update:modelValue', { 1: [11] })
    await wrapper.get('.pos-product-dialog-cancel').trigger('click')
    expect(cart.updateItemOptions).not.toHaveBeenCalled()
    expect(initialOptions).toEqual({ 1: [10] })
    await wrapper.setProps({ modelValue: false })
    await wrapper.setProps({ modelValue: true })
    expect(wrapper.getComponent(Options).props('modelValue')).toEqual({ 1: [10] })
  })
})
