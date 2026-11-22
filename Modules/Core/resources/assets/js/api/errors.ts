function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null
}

function responseOf(error: unknown): Record<string, unknown> | undefined {
  return isRecord(error) && isRecord(error.response) ? error.response : undefined
}

export function getApiErrorStatus(error: unknown): number | undefined {
  const status = responseOf(error)?.status
  return typeof status === 'number' ? status : undefined
}

export function getApiErrorMessage(error: unknown): string | undefined {
  return getApiResponseMessage(responseOf(error))
}

export function getApiResponseMessage(response: unknown): string | undefined {
  const data = isRecord(response) ? response.data : undefined
  return isRecord(data) && typeof data.message === 'string' ? data.message : undefined
}

export function getApiErrorUrl(error: unknown): string | undefined {
  const config = isRecord(error) ? error.config : undefined
  return isRecord(config) && typeof config.url === 'string' ? config.url : undefined
}

export function getApiValidationErrors(
  error: unknown,
): Record<string, string | string[]> | undefined {
  const data = responseOf(error)?.data
  if (!isRecord(data) || !isRecord(data.errors)) return undefined

  const errors: Record<string, string | string[]> = {}
  for (const [field, value] of Object.entries(data.errors)) {
    if (typeof value === 'string') {
      errors[field] = value
    } else if (Array.isArray(value) && value.every((message) => typeof message === 'string')) {
      errors[field] = value
    }
  }
  return errors
}
