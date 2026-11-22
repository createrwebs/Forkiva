import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useForm } from './form'

const toast = vi.hoisted(() => ({ error: vi.fn(), success: vi.fn() }))

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('vue-toastification', () => ({ useToast: () => toast }))

describe('form submission boundaries', () => {
  beforeEach(() => vi.clearAllMocks())

  it('returns the original typed response when requested', async () => {
    const form = useForm({ name: '' })
    const response = { data: { message: 'Saved.', body: { id: 7 } } }

    expect(await form.submit(async () => response, { withResponse: true })).toBe(response)
    expect(toast.success).toHaveBeenCalledWith('Saved.')
    expect(form.loading.value).toBe(false)
  })

  it('maps nested validation errors to form fields and unrelated errors to the general message', async () => {
    const form = useForm({ name: '', items: [] })
    const result = await form.submit(async () => {
      throw {
        response: {
          status: 422,
          data: {
            errors: { 'items.0.quantity': ['Invalid quantity.'], branch: ['Branch unavailable.'] },
          },
        },
      }
    })

    expect(result).toBe(false)
    expect(form.errors.value['items.0.quantity']).toBe('Invalid quantity.')
    expect(form.errors.value.general).toBe('Branch unavailable.')
    expect(form.loading.value).toBe(false)
  })

  it('handles non-object rejections without leaving the form loading', async () => {
    const form = useForm({ name: '' })

    expect(
      await form.submit(async () => {
        throw null
      }),
    ).toBe(false)
    expect(form.errors.value.general).toBe('core::errors.an_unexpected_error_occurred')
    expect(form.loading.value).toBe(false)
  })

  it('keeps login rejection messages on the identifier field', async () => {
    const form = useForm({ identifier: '', password: '' })
    await form.submit(async () => {
      throw {
        config: { url: '/auth/login' },
        response: { status: 401, data: { message: 'Login failed.' } },
      }
    })

    expect(form.errors.value.identifier).toBe('Login failed.')
    expect(toast.error).not.toHaveBeenCalled()
  })
})
