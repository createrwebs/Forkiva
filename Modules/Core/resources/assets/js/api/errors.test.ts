import { describe, expect, it } from 'vitest'
import {
  getApiErrorMessage,
  getApiErrorStatus,
  getApiErrorUrl,
  getApiResponseMessage,
  getApiValidationErrors,
} from './errors'

describe('API error details', () => {
  it('reads status and message from a server rejection', () => {
    const error = { response: { status: 409, data: { message: 'The order changed.' } } }

    expect(getApiErrorStatus(error)).toBe(409)
    expect(getApiErrorMessage(error)).toBe('The order changed.')
  })

  it.each([null, undefined, 'offline', 0, new Error('Network error'), {}])(
    'handles a rejection without an API response: %s',
    (error) => {
      expect(getApiErrorStatus(error)).toBeUndefined()
      expect(getApiErrorMessage(error)).toBeUndefined()
    },
  )

  it('does not treat malformed payload values as a status or a user-facing message', () => {
    const error = { response: { status: '409', data: { message: { private: true } } } }

    expect(getApiErrorStatus(error)).toBeUndefined()
    expect(getApiErrorMessage(error)).toBeUndefined()
  })

  it('preserves field errors while excluding malformed messages', () => {
    const error = {
      config: { url: '/auth/login' },
      response: {
        data: {
          errors: {
            identifier: ['Unknown account.'],
            password: 'Required.',
            private: { token: true },
          },
        },
      },
    }

    expect(getApiErrorUrl(error)).toBe('/auth/login')
    expect(getApiValidationErrors(error)).toEqual({
      identifier: ['Unknown account.'],
      password: 'Required.',
    })
    expect(getApiValidationErrors(null)).toBeUndefined()
    expect(getApiResponseMessage({ data: { message: 'Saved.' } })).toBe('Saved.')
  })
})
