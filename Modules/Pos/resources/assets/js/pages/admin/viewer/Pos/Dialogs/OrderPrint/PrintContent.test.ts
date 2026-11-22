import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import PrintContent from './PrintContent.vue'

const { printPreview, toastError, createJob } = vi.hoisted(() => ({
  printPreview: vi.fn(),
  toastError: vi.fn(),
  createJob: vi.fn(),
}))
vi.mock('@/modules/order/composables/order.ts', () => ({ useOrder: () => ({ printPreview }) }))
vi.mock('vue-toastification', () => ({ useToast: () => ({ error: toastError }) }))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))

function mountActions(props = {}) {
  return mount(PrintContent, {
    props: {
      orderId: 42,
      content: { type: { id: 'bill', name: 'Bill' }, label: 'Print Bill' },
      registerId: null,
      agentAvailable: false,
      qintrix: { createJob } as any,
      ...props,
    },
    global: {
      stubs: {
        VIcon: true,
        VBtn: { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
      },
    },
  })
}
beforeEach(() => {
  vi.restoreAllMocks()
  printPreview.mockReset()
  createJob.mockReset()
  toastError.mockReset()
})

describe('Order print actions', () => {
  it('previews the currently selected kitchen and registers print listeners before closing the document', async () => {
    const document = window.document.implementation.createHTMLDocument()
    const events: string[] = []
    vi.spyOn(document, 'close').mockImplementation(() => {
      events.push('close')
    })
    vi.spyOn(window, 'open').mockReturnValue({
      document,
      closed: false,
      addEventListener: (name: string) => events.push(name),
      close: vi.fn(),
    } as unknown as Window)
    printPreview.mockResolvedValue({
      data: { body: { content: '<html><body>Kitchen ticket</body></html>' } },
    })
    const wrapper = mountActions()
    await wrapper.setProps({
      content: { type: { id: 'kitchen', name: 'Kitchen' }, id: 7, label: 'Hot kitchen' },
      registerId: 10,
    })
    await wrapper.findAll('button')[2]!.trigger('click')
    await flushPromises()
    expect(printPreview).toHaveBeenCalledWith(42, 'kitchen', 7, 10, 'html')
    expect(document.body.textContent).toBe('Kitchen ticket')
    expect(events).toEqual(['afterprint', 'load', 'close'])
    expect(wrapper.emitted('busy')).toEqual([[true], [false]])
  })
  it('explains blocked pop-ups without starting a preview request', async () => {
    vi.spyOn(window, 'open').mockReturnValue(null)
    const wrapper = mountActions()
    await wrapper.findAll('button')[2]!.trigger('click')
    expect(toastError).toHaveBeenCalledWith('order::orders.print_dialog.popup_blocked')
    expect(printPreview).not.toHaveBeenCalled()
  })
  it('allows kitchen direct printing with its own destination and no register', async () => {
    printPreview.mockResolvedValue({
      data: {
        body: {
          content: 'test-pdf',
          printer: { qintrix_id: 'kitchen-printer', paper_size: '80mm' },
        },
      },
    })
    const wrapper = mountActions({
      content: { type: { id: 'kitchen', name: 'Kitchen' }, id: 7, label: 'Hot kitchen' },
      agentAvailable: true,
    })
    await wrapper.findAll('button')[1]!.trigger('click')
    await flushPromises()
    expect(printPreview).toHaveBeenCalledWith(42, 'kitchen', 7, null, 'pdf')
    expect(createJob).toHaveBeenCalledWith(
      'test-pdf',
      'kitchen-printer',
      '80mm',
      '42',
      'order-kitchen',
      'pdf',
    )
  })
})
