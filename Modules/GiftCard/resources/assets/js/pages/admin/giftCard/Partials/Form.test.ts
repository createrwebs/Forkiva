import { flushPromises, shallowMount } from '@vue/test-utils'
import { beforeEach, expect, it, vi } from 'vitest'
import Form from '@/modules/giftcard/pages/admin/giftCard/Partials/Form.vue'

const api = vi.hoisted(() => ({
  store: vi.fn().mockResolvedValue({}),
  update: vi.fn().mockResolvedValue({}),
  getFormMeta: vi.fn().mockResolvedValue({
    data: { body: { branches: [{ id: 1, currency: 'USD' }], default_currency: 'USD' } },
  }),
  push: vi.fn(),
}))

vi.mock('@/modules/giftcard/composables/giftCard.ts', () => ({ useGiftCard: () => api }))
vi.mock('@/modules/auth/composables/auth.ts', () => ({ useAuth: () => ({ user: {} }) }))
vi.mock('vue-router', () => ({ useRouter: () => ({ push: api.push }) }))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('vue-toastification', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }))

beforeEach(() => vi.clearAllMocks())

function mountForm(action: 'create' | 'update') {
  return shallowMount(Form, {
    props: {
      action,
      item: {
        id: 7,
        code: 'TEST-CARD',
        branch: { id: 1 },
        initial_balance: { amount: 10 },
        currency: 'JOD',
        notes: 'Existing notes',
      },
    },
    global: {
      renderStubDefaultSlot: true,
      directives: { 'decimal-en': {} },
      stubs: {
        VIcon: true,
        VCardTitle: true,
        VCol: true,
        VRow: true,
        VCardText: true,
        VCard: true,
        VTextarea: true,
        VSelect: true,
        VTextField: {
          name: 'VTextField',
          props: ['modelValue', 'readonly', 'label', 'prefix'],
          template: '<input :value="modelValue" :readonly="readonly" :aria-label="label" />',
        },
      },
    },
  })
}

it('updates editable details without sending the protected balance or issuance fields', async () => {
  const wrapper = mountForm('update')
  await flushPromises()
  const balance = wrapper.get('input[aria-label="giftcard::attributes.gift_cards.initial_balance"]')
  expect(balance.attributes('readonly')).toBeDefined()
  expect(wrapper.findAllComponents({ name: 'VTextField' })[1]?.props('prefix')).toBe('JOD')
  wrapper.getComponent({ name: 'BaseForm' }).vm.$emit('submit')
  await flushPromises()
  expect(api.update).toHaveBeenCalledWith(7, {
    code: 'TEST-CARD',
    branch_id: 1,
    customer_id: null,
    status: 'active',
    expiry_date: null,
    notes: 'Existing notes',
  })
  expect(api.store).not.toHaveBeenCalled()
  expect(api.push).toHaveBeenCalledWith({ name: 'admin.gift_cards.index' })
  wrapper.unmount()
})

it('keeps the initial balance and funding fields in the create request', async () => {
  const wrapper = mountForm('create')
  await flushPromises()
  expect(
    wrapper
      .get('input[aria-label="giftcard::attributes.gift_cards.initial_balance"]')
      .attributes('readonly'),
  ).toBeUndefined()
  wrapper.getComponent({ name: 'BaseForm' }).vm.$emit('submit')
  await flushPromises()
  expect(api.store).toHaveBeenCalledWith(
    expect.objectContaining({
      initial_balance: 10,
      currency: 'USD',
      funding_type: 'promotional',
      idempotency_key: expect.any(String),
    }),
  )
  expect(api.update).not.toHaveBeenCalled()
  wrapper.unmount()
})
