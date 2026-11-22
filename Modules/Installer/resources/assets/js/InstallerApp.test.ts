import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import InstallerApp from './InstallerApp.vue'
import InstallerSelect from './components/InstallerSelect.vue'
import { InstallerError, installerRequest } from './api/installer'
import type { Configuration, InstallationState } from './contracts/installer'
import type * as InstallerApi from './api/installer'

vi.mock('./api/installer', async (original) => {
  const actual = await original<typeof InstallerApi>()
  return { ...actual, installerRequest: vi.fn() }
})
const request = vi.mocked(installerRequest)
const configuration: Configuration = {
  app_name: 'Forkiva',
  app_url: 'https://pos.example.test',
  supported_countries: ['JO'],
  default_country: 'JO',
  supported_locales: ['en', 'ar'],
  default_locale: 'en',
  default_timezone: 'Asia/Amman',
  supported_currencies: ['JOD'],
  default_currency: 'JOD',
  passkeys_enabled: true,
  passkeys_relying_party_id: 'pos.example.test',
  passkeys_allowed_origins: ['https://pos.example.test'],
  cache_enabled: true,
  cache_store: 'file',
}
const initial: InstallationState = {
  checks_passed: false,
  database: false,
  tasks: [],
  admin: false,
  finished: false,
}
const wrappers: ReturnType<typeof mount>[] = []
function render(initialState?: InstallationState) {
  const wrapper = mount(InstallerApp, { props: { initialState } })
  wrappers.push(wrapper)
  return wrapper
}
beforeEach(() => request.mockReset())
afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
  vi.useRealTimers()
})

describe('installer workflow', () => {
  it('uses the selected engine port while preserving a custom port', async () => {
    const saved = { ...initial, checks_passed: true, configuration }
    request.mockResolvedValue(saved)
    const wrapper = render(saved)
    await flushPromises()
    const engine = wrapper
      .findAllComponents(InstallerSelect)
      .find((field) => field.props('name') === 'db_connection')!
    expect((wrapper.get('[name="db_port"]').element as HTMLInputElement).value).toBe('3306')
    engine.vm.$emit('update:modelValue', 'pgsql')
    await flushPromises()
    expect((wrapper.get('[name="db_port"]').element as HTMLInputElement).value).toBe('5432')
    await wrapper.get('[name="db_port"]').setValue('55432')
    engine.vm.$emit('update:modelValue', 'mysql')
    await flushPromises()
    expect((wrapper.get('[name="db_port"]').element as HTMLInputElement).value).toBe('55432')
  })

  it('keeps the setup content visible while the initial status loads', async () => {
    let resolveStatus!: (state: InstallationState) => void
    request.mockImplementationOnce(() => new Promise((resolve) => (resolveStatus = resolve)))
    const wrapper = render()
    await flushPromises()
    expect(wrapper.get('.welcome-content').isVisible()).toBe(true)
    expect(wrapper.find('.step-loading').exists()).toBe(false)
    expect(wrapper.get('.setup-navigation').isVisible()).toBe(true)
    expect(wrapper.get('.button.primary').attributes('disabled')).toBeDefined()
    resolveStatus({ ...initial, checks_passed: true })
    await flushPromises()
    expect(wrapper.find('.step-loading').exists()).toBe(false)
    expect(wrapper.find('#configuration-form').exists()).toBe(true)
  })

  it('shows saved database progress without activity spinners during refresh', async () => {
    let resolveStatus!: (state: InstallationState) => void
    request.mockImplementationOnce(() => new Promise((resolve) => (resolveStatus = resolve)))
    const saved: InstallationState = {
      ...initial,
      checks_passed: true,
      database: true,
      tasks: [{ id: 'seed', label: 'Initialize data', status: 'running' }],
    }
    const wrapper = render(saved)
    await flushPromises()
    expect(wrapper.get('.preparation').isVisible()).toBe(true)
    expect(wrapper.find('.spinner').exists()).toBe(false)
    expect(wrapper.get('.button.primary').attributes('disabled')).toBeDefined()
    resolveStatus(saved)
    await flushPromises()
    expect(wrapper.get('.button.primary').attributes('disabled')).toBeUndefined()
  })

  it('renders the saved configuration immediately and keeps it disabled if status fails', async () => {
    let rejectStatus!: (error: Error) => void
    request.mockImplementationOnce(() => new Promise((_, reject) => (rejectStatus = reject)))
    const wrapper = render({ ...initial, checks_passed: true })
    expect(wrapper.get('#configuration-form').isVisible()).toBe(true)
    expect(wrapper.get('#configuration-form fieldset').attributes('disabled')).toBeDefined()
    expect(wrapper.find('.welcome-content').exists()).toBe(false)
    await flushPromises()
    expect(wrapper.find('.step-loading').exists()).toBe(false)
    rejectStatus(new Error('Offline'))
    await flushPromises()
    expect(wrapper.get('#configuration-form fieldset').attributes('disabled')).toBeDefined()
    expect(wrapper.get('button[form=configuration-form]').attributes('disabled')).toBeDefined()
    expect(wrapper.get('.error-notice button').attributes('disabled')).toBeUndefined()
  })

  it('keeps configuration mounted during saving and a status retry', async () => {
    let rejectSave!: (error: Error) => void
    let resolveStatus!: (state: InstallationState) => void
    request
      .mockResolvedValueOnce({ ...initial, checks_passed: true })
      .mockImplementationOnce(() => new Promise((_, reject) => (rejectSave = reject)))
      .mockImplementationOnce(() => new Promise((resolve) => (resolveStatus = resolve)))
    const wrapper = render()
    await flushPromises()
    await wrapper.get('input[name=app_name]').setValue('My Restaurant')
    const form = wrapper.get('#configuration-form').element
    await wrapper.get('#configuration-form').trigger('submit')
    expect(wrapper.get('.step-loading').text()).toBe('Saving configuration…')
    expect(wrapper.get('#configuration-form').element).toBe(form)
    expect(wrapper.get('#configuration-form').isVisible()).toBe(true)
    expect(wrapper.get('#configuration-form fieldset').attributes('disabled')).toBeDefined()
    rejectSave(new Error('Connection interrupted'))
    await flushPromises()
    expect(wrapper.find('.step-loading').exists()).toBe(false)
    await wrapper.get('.error-notice button').trigger('click')
    expect(wrapper.find('.step-loading').exists()).toBe(false)
    expect(wrapper.get('#configuration-form').element).toBe(form)
    expect(wrapper.get('#configuration-form').isVisible()).toBe(true)
    expect((wrapper.get('input[name=app_name]').element as HTMLInputElement).value).toBe(
      'My Restaurant',
    )
    resolveStatus({ ...initial, checks_passed: true })
    await flushPromises()
    expect(wrapper.find('.step-loading').exists()).toBe(false)
    expect(wrapper.get('#configuration-form fieldset').attributes('disabled')).toBeUndefined()
  })

  it('shows the configuration step after checks and advances only after saving', async () => {
    request
      .mockResolvedValueOnce({ ...initial, checks_passed: true })
      .mockResolvedValueOnce({ ...initial, checks_passed: true, configuration })
    const wrapper = render()
    await flushPromises()
    expect(wrapper.find('h1').text()).toBe('Make it your own.')
    expect((wrapper.get('input[name=passkeys_enabled]').element as HTMLInputElement).checked).toBe(
      true,
    )
    expect(wrapper.find('button[name=cache_store]').exists()).toBe(true)
    await wrapper.get('input[name=app_url]').setValue('https://')
    await wrapper.get('input[name=app_url]').setValue('https://shop.example.test')
    expect(
      (wrapper.get('input[name=passkeys_relying_party_id]').element as HTMLInputElement).value,
    ).toBe('shop.example.test')
    await wrapper.get('input[name=passkeys_allowed_origins]').setValue('https://shop.example.test')
    expect(wrapper.find('textarea[name=passkeys_allowed_origins]').exists()).toBe(false)
    await wrapper.get('#configuration-form').trigger('submit')
    await flushPromises()
    expect(request.mock.calls.map((call) => call[0])).toEqual(['status', 'configuration'])
    expect(wrapper.find('#database-form').exists()).toBe(true)
  })

  it('keeps invalid configuration visible with server validation errors', async () => {
    request.mockResolvedValueOnce({ ...initial, checks_passed: true }).mockRejectedValueOnce(
      new InstallerError('Review highlighted fields.', {
        default_currency: ['Choose a supported currency.'],
      }),
    )
    const wrapper = render()
    await flushPromises()
    await wrapper.get('#configuration-form').trigger('submit')
    await flushPromises()
    expect(wrapper.get('button[name=default_currency]').attributes('aria-invalid')).toBe('true')
    expect(wrapper.text()).toContain('Choose a supported currency.')
    expect(wrapper.find('#database-form').exists()).toBe(false)
  })

  it('does not allow continuing when server checks fail', async () => {
    request.mockResolvedValueOnce(initial).mockResolvedValueOnce({
      ...initial,
      checks: [
        {
          id: 'php',
          label: 'PHP runtime',
          passed: false,
          detail: 'Missing dependency',
          remedy: 'Enable the extension',
        },
      ],
    })
    const wrapper = render()
    await flushPromises()
    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('Let’s get started'))!
      .trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Enable the extension')
    expect(
      wrapper
        .findAll('button')
        .find((button) => button.text().includes('Continue to configuration'))!
        .attributes('disabled'),
    ).toBeDefined()
    expect(request.mock.calls.map((call) => call[0])).toEqual(['status', 'checks'])
  })

  it('resumes preparation from real server progress and stops on failure', async () => {
    const saved = {
      ...initial,
      configuration,
      checks_passed: true,
      database: true,
      tasks: [{ id: 'seed', label: 'Initialize data', status: 'pending' as const }],
    }
    request.mockResolvedValueOnce(saved).mockResolvedValueOnce({
      ...saved,
      error: 'Initialize data failed.',
      tasks: [{ ...saved.tasks[0]!, status: 'failed' }],
    })
    const wrapper = render()
    await flushPromises()
    expect(wrapper.text()).toContain('0%')
    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('Resume preparation'))!
      .trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Initialize data failed.')
    expect(wrapper.text()).toContain('Retry preparation')
    expect(wrapper.text()).not.toContain('100%')
    expect(request.mock.calls.map((call) => call[0])).toEqual(['status', 'process'])
  })

  it('polls an accepted task until completion before submitting the next task', async () => {
    vi.useFakeTimers()
    const saved: InstallationState = {
      ...initial,
      database: true,
      checks_passed: true,
      tasks: [
        { id: 'accounting', label: 'Initialize Accounting', status: 'pending' },
        { id: 'last', label: 'Initialize final module', status: 'pending' },
      ],
    }
    const running = {
      ...saved,
      processing: true,
      tasks: [{ ...saved.tasks[0]!, status: 'running' as const }, saved.tasks[1]!],
    }
    const firstComplete = {
      ...saved,
      processing: false,
      tasks: [{ ...saved.tasks[0]!, status: 'complete' as const }, saved.tasks[1]!],
    }
    request
      .mockResolvedValueOnce(saved)
      .mockResolvedValueOnce(running)
      .mockResolvedValueOnce(running)
      .mockResolvedValueOnce(firstComplete)
      .mockResolvedValueOnce({
        ...firstComplete,
        tasks: firstComplete.tasks.map((task) => ({ ...task, status: 'complete' as const })),
      })
    const wrapper = render(saved)
    await flushPromises()
    await wrapper.get('.button.primary').trigger('click')
    await flushPromises()
    expect(request.mock.calls.map((call) => call[0])).toEqual(['status', 'process', 'status'])
    expect(wrapper.get('.button.primary').attributes('disabled')).toBeDefined()
    await vi.advanceTimersByTimeAsync(2000)
    await flushPromises()
    expect(request.mock.calls.map((call) => call[0])).toEqual([
      'status',
      'process',
      'status',
      'status',
      'process',
    ])
    expect(wrapper.find('#admin-form').exists()).toBe(true)
  })

  it('reconciles a gateway timeout and never repeats the completed task', async () => {
    vi.useFakeTimers()
    const saved: InstallationState = {
      ...initial,
      database: true,
      checks_passed: true,
      tasks: [{ id: 'accounting', label: 'Initialize Accounting', status: 'pending' }],
    }
    request
      .mockResolvedValueOnce(saved)
      .mockRejectedValueOnce(new InstallerError('Gateway timeout', {}, 504))
      .mockResolvedValueOnce({
        ...saved,
        processing: true,
        tasks: [{ ...saved.tasks[0]!, status: 'running' }],
      })
      .mockResolvedValueOnce({
        ...saved,
        processing: false,
        tasks: [{ ...saved.tasks[0]!, status: 'complete' }],
      })
    const wrapper = render(saved)
    await flushPromises()
    await wrapper.get('.button.primary').trigger('click')
    await flushPromises()
    expect(wrapper.get('.step-loading').text()).toBe('Checking saved progress…')
    expect(wrapper.find('.error-notice').exists()).toBe(false)
    expect(wrapper.get('.button.primary').attributes('disabled')).toBeDefined()
    const reload = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(reload)
    expect(reload.defaultPrevented).toBe(false)
    await vi.advanceTimersByTimeAsync(2000)
    await flushPromises()
    expect(wrapper.find('#admin-form').exists()).toBe(true)
    expect(request.mock.calls.map((call) => call[0])).toEqual([
      'status',
      'process',
      'status',
      'status',
    ])
  })

  it('offers a manual resume when a timed-out worker has stopped without completing', async () => {
    const saved: InstallationState = {
      ...initial,
      database: true,
      checks_passed: true,
      tasks: [{ id: 'accounting', label: 'Initialize Accounting', status: 'running' }],
    }
    request
      .mockResolvedValueOnce(saved)
      .mockRejectedValueOnce(new InstallerError('Gateway timeout', {}, 504))
      .mockResolvedValueOnce({ ...saved, processing: false })
    const wrapper = render(saved)
    await flushPromises()
    await wrapper.get('.button.primary').trigger('click')
    await flushPromises()
    expect(wrapper.get('.error-notice').text()).toContain('Your completed tasks are saved.')
    expect(wrapper.get('.button.primary').text()).toContain('Resume preparation')
    expect(wrapper.find('#admin-form').exists()).toBe(false)
    expect(request.mock.calls.map((call) => call[0])).toEqual(['status', 'process', 'status'])
  })

  it('continues an active run after refresh without enabling controls between tasks', async () => {
    vi.useFakeTimers()
    const running: InstallationState = {
      ...initial,
      database: true,
      checks_passed: true,
      processing: true,
      tasks: [
        { id: 'accounting', label: 'Initialize Accounting', status: 'running' },
        { id: 'last', label: 'Initialize last module', status: 'pending' },
      ],
    }
    const next = {
      ...running,
      processing: false,
      tasks: [{ ...running.tasks[0]!, status: 'complete' as const }, running.tasks[1]!],
    }
    let finishNext!: (value: InstallationState) => void
    request
      .mockResolvedValueOnce(running)
      .mockResolvedValueOnce(next)
      .mockImplementationOnce(
        () =>
          new Promise((resolve) => {
            finishNext = resolve
          }),
      )
    const wrapper = render(running)
    const content = wrapper.get('.preparation').element
    await flushPromises()
    expect(wrapper.get('.button.primary').attributes('disabled')).toBeDefined()
    expect(wrapper.find('.step-loading').exists()).toBe(false)
    await vi.advanceTimersByTimeAsync(2000)
    await flushPromises()
    expect(request.mock.calls.map((call) => call[0])).toEqual(['status', 'status', 'process'])
    expect(wrapper.get('.preparation').element).toBe(content)
    expect(wrapper.get('.button.primary').attributes('disabled')).toBeDefined()
    expect(wrapper.get('.button.primary').text()).toContain('Preparing…')
    expect(wrapper.get('.steps .active').text()).toContain('Database')
    finishNext({
      ...next,
      tasks: next.tasks.map((task) => ({ ...task, status: 'complete' as const })),
    })
    await flushPromises()
    expect(wrapper.find('#admin-form').exists()).toBe(true)
    expect(wrapper.get('.button.primary').attributes('disabled')).toBeUndefined()
  })

  it('does not restart a failed task when refreshing an active run', async () => {
    const running: InstallationState = {
      ...initial,
      database: true,
      processing: true,
      tasks: [{ id: 'seed', label: 'Initialize data', status: 'running' }],
    }
    request.mockResolvedValueOnce({
      ...running,
      processing: false,
      error: 'Initialize data failed.',
      tasks: [{ ...running.tasks[0]!, status: 'failed' }],
    })
    const wrapper = render(running)
    await flushPromises()
    expect(request).toHaveBeenCalledTimes(1)
    expect(wrapper.get('.error-notice').text()).toContain('Initialize data failed.')
    expect(wrapper.get('.button.primary').text()).toContain('Retry preparation')
  })

  it('keeps a refreshed page disabled while the server is working and stops polling on unmount', async () => {
    vi.useFakeTimers()
    const saved: InstallationState = {
      ...initial,
      database: true,
      checks_passed: true,
      processing: true,
      tasks: [{ id: 'accounting', label: 'Initialize Accounting', status: 'running' }],
    }
    request.mockResolvedValue(saved)
    const wrapper = render(saved)
    await flushPromises()
    expect(wrapper.get('.button.primary').attributes('disabled')).toBeDefined()
    expect(wrapper.find('.step-loading').exists()).toBe(false)
    wrapper.unmount()
    await vi.advanceTimersByTimeAsync(4000)
    await flushPromises()
    expect(request).toHaveBeenCalledTimes(1)
    expect(request.mock.calls[0]![2]!.aborted).toBe(true)
  })

  it('advances after confirmed completion without repeating database configuration', async () => {
    const saved = {
      ...initial,
      configuration,
      checks_passed: true,
      database: true,
      tasks: [{ id: 'seed', label: 'Initialize data', status: 'running' as const }],
    }
    request
      .mockResolvedValueOnce(saved)
      .mockResolvedValueOnce({ ...saved, tasks: [{ ...saved.tasks[0]!, status: 'complete' }] })
    const wrapper = render()
    await flushPromises()
    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('Resume preparation'))!
      .trigger('click')
    await flushPromises()
    expect(wrapper.find('h1').text()).toBe('Make yourself at home.')
    expect(request.mock.calls.map((call) => call[0])).toEqual(['status', 'process'])
    expect(wrapper.find('input[name=password_confirmation]').exists()).toBe(true)
  })

  it('keeps the form and displays server field validation errors', async () => {
    request
      .mockResolvedValueOnce({ ...initial, configuration, checks_passed: true })
      .mockRejectedValueOnce(
        new InstallerError('Review highlighted fields.', {
          db_host: ['The database host field format is invalid.'],
        }),
      )
    const wrapper = render()
    await flushPromises()
    await wrapper.get('input[name=db_host]').setValue('bad;host')
    await wrapper.get('#database-form').trigger('submit')
    await flushPromises()
    expect(wrapper.get('input[name=db_host]').attributes('aria-invalid')).toBe('true')
    expect(wrapper.text()).toContain('The database host field format is invalid.')
    expect(wrapper.find('h1').text()).toBe('A home for your data.')
  })

  it('shows cache verification failures on the launch step', async () => {
    const ready = {
      ...initial,
      configuration,
      checks_passed: true,
      database: true,
      admin: true,
      tasks: [{ id: 'seed', label: 'Initialize data', status: 'complete' as const }],
    }
    request.mockResolvedValueOnce(ready).mockRejectedValueOnce(
      new InstallerError('Review highlighted fields.', {
        cache_store: ['Cache storage is not writable.'],
      }),
    )
    const wrapper = render()
    await flushPromises()
    expect(wrapper.get('input[name=app_url]').attributes('readonly')).toBeDefined()
    await wrapper.get('#launch-form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('Cache storage is not writable.')
  })

  it('shows success only after the server confirms launch', async () => {
    const ready = {
      ...initial,
      configuration,
      checks_passed: true,
      database: true,
      admin: true,
      tasks: [{ id: 'seed', label: 'Initialize data', status: 'complete' as const }],
    }
    request.mockResolvedValueOnce(ready).mockResolvedValueOnce({ ...ready, finished: true })
    const wrapper = render()
    await flushPromises()
    expect(wrapper.text()).not.toContain('Your workspace is ready.')
    await wrapper.get('#launch-form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('Your workspace is ready.')
    expect(wrapper.get('a[href="/auth/login"]').text()).toContain('Open Forkiva')
    expect(wrapper.find('#launch-form').exists()).toBe(false)
  })
})
