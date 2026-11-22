import { flushPromises, shallowMount } from '@vue/test-utils'
import { computed, onMounted, ref, watch } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import PdfSettings from './PdfSettings.vue'

const mocks = vi.hoisted(() => ({
  submit: vi.fn(),
  toastError: vi.fn(),
  update: vi.fn(),
}))

vi.mock('@/modules/core/composables/form.ts', () => ({
  useForm: (state: Record<string, unknown>) => ({
    state,
    errors: {
      value: {
        pdf_default_driver:
          'The Cloudflare Browser Rendering driver could not generate the verification PDF.',
      },
    },
    loading: { value: false },
    submit: mocks.submit,
  }),
}))

vi.mock('@/modules/setting/composables/setting.ts', () => ({
  useSetting: () => ({
    getSettings: vi.fn().mockResolvedValue(false),
    store: { loading: false },
    update: mocks.update,
  }),
}))

vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    locale: { value: 'en' },
    t: (key: string) => key,
  }),
}))

vi.mock('vue-toastification', () => ({
  useToast: () => ({ error: mocks.toastError }),
}))

const formStub = {
  name: 'VForm',
  emits: ['submit'],
  template: '<form @submit.prevent="$emit(\'submit\', $event)"><slot /></form>',
}

describe('PDF settings feedback', () => {
  beforeEach(() => {
    vi.stubGlobal('computed', computed)
    vi.stubGlobal('onMounted', onMounted)
    vi.stubGlobal('ref', ref)
    vi.stubGlobal('watch', watch)
    mocks.submit.mockReset()
    mocks.submit.mockResolvedValue(false)
    mocks.toastError.mockReset()
    mocks.update.mockReset()
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows driver verification failures in a toast', async () => {
    const wrapper = shallowMount(PdfSettings, {
      global: {
        stubs: {
          PageLoader: true,
          VAlert: true,
          VBtn: true,
          VCheckbox: true,
          VChip: true,
          VCol: true,
          VDivider: true,
          VForm: formStub,
          VIcon: true,
          VRow: true,
          VSelect: true,
          VSwitch: true,
          VTextField: true,
        },
        directives: { integerEn: () => {} },
      },
    })

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(mocks.toastError).toHaveBeenCalledOnce()
    expect(mocks.toastError).toHaveBeenCalledWith(
      'The Cloudflare Browser Rendering driver could not generate the verification PDF.',
    )
  })
})
