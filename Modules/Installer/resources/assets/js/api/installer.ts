import type { FieldErrors, InstallationState } from '../contracts/installer'

export class InstallerError extends Error {
  constructor(
    message: string,
    public fields: FieldErrors = {},
    public status = 0,
  ) {
    super(message)
  }
}

// Setup precedes Pinia and bearer authentication. The installer web routes use their own
// same-origin session and CSRF token without booting the product's authenticated stores.
export async function installerRequest(
  action: string,
  data?: object,
  signal?: AbortSignal,
): Promise<InstallationState> {
  const response = await fetch(`/installer/${action}`, {
    method: data ? 'POST' : 'GET',
    signal,
    credentials: 'same-origin',
    cache: 'no-store',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN':
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
    },
    body: data ? JSON.stringify(data) : undefined,
  })
  const payload = await response.json().catch(() => null)
  if (!response.ok || !payload?.body) {
    const fields = payload?.errors ?? {}
    const hostingFailure =
      response.status === 502 || response.status === 504
        ? 'The hosting server interrupted the installation request. Keep this session open and refresh the status first; the task may still be running. If it repeats, ask hosting support to check PHP and proxy request timeouts before retrying.'
        : 'The hosting server returned an unexpected response. Ask hosting support to check the PHP error log and web-server log for this request. Keep this session open and refresh the status before retrying.'
    throw new InstallerError(
      response.status === 419
        ? 'Your setup session expired. Reload this page before continuing.'
        : fields.database?.length
          ? 'Database verification failed.'
          : Object.keys(fields).length
            ? 'Review the highlighted fields and try again.'
            : payload?.message || hostingFailure,
      fields,
      response.status,
    )
  }
  return payload.body
}
