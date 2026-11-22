import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { schedulePwaSetup } from './schedulePwaSetup'

const setupPwa = vi.hoisted(() => vi.fn())
vi.mock('@/app/plugins/pwa.ts', () => ({ setupPwa }))

describe('deferred PWA setup', () => {
  let frame: FrameRequestCallback
  let idle: IdleRequestCallback

  beforeEach(() => {
    vi.stubEnv('DEV', false)
    vi.useFakeTimers()
    setupPwa.mockReset()
    vi.stubGlobal(
      'requestAnimationFrame',
      vi.fn((callback) => {
        frame = callback
        return 1
      }),
    )
    vi.stubGlobal('cancelAnimationFrame', vi.fn())
    vi.stubGlobal(
      'requestIdleCallback',
      vi.fn((callback) => {
        idle = callback
        return 2
      }),
    )
    vi.stubGlobal('cancelIdleCallback', vi.fn())
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
    vi.unstubAllEnvs()
  })

  it('waits for a frame and idle time before registering the PWA', () => {
    schedulePwaSetup()
    expect(setupPwa).not.toHaveBeenCalled()
    frame(0)
    expect(setupPwa).not.toHaveBeenCalled()
    expect(window.requestIdleCallback).toHaveBeenCalledWith(expect.any(Function), {
      timeout: 5000,
    })
    idle({ didTimeout: false, timeRemaining: () => 20 })
    expect(setupPwa).toHaveBeenCalledOnce()
  })

  it('supports browsers without idle callbacks', () => {
    vi.stubGlobal('requestIdleCallback', undefined)
    schedulePwaSetup()
    frame(0)
    expect(setupPwa).not.toHaveBeenCalled()
    vi.runAllTimers()
    expect(setupPwa).toHaveBeenCalledOnce()
  })

  it('registers even when a background tab never paints', () => {
    schedulePwaSetup()
    vi.advanceTimersByTime(5000)
    expect(setupPwa).toHaveBeenCalledOnce()
    frame(0)
    expect(window.requestIdleCallback).not.toHaveBeenCalled()
    expect(setupPwa).toHaveBeenCalledOnce()
  })

  it('does not register twice when idle time follows the deadline', () => {
    schedulePwaSetup()
    frame(0)
    vi.advanceTimersByTime(5000)
    idle({ didTimeout: true, timeRemaining: () => 0 })
    expect(setupPwa).toHaveBeenCalledOnce()
  })

  it('cancels the deadline before the first frame', () => {
    const cancel = schedulePwaSetup()
    cancel()
    vi.runAllTimers()
    frame(0)
    expect(setupPwa).not.toHaveBeenCalled()
  })

  it('cancels deferred callbacks when the application is disposed', () => {
    const cancel = schedulePwaSetup()
    frame(0)
    cancel()
    expect(window.cancelAnimationFrame).toHaveBeenCalledWith(1)
    expect(window.cancelIdleCallback).toHaveBeenCalledWith(2)
    expect(setupPwa).not.toHaveBeenCalled()
  })

  it('cancels the fallback timer when the application is disposed', () => {
    vi.stubGlobal('requestIdleCallback', undefined)
    const cancel = schedulePwaSetup()
    frame(0)
    cancel()
    vi.runAllTimers()
    expect(setupPwa).not.toHaveBeenCalled()
  })

  it('does not register service workers in development', () => {
    vi.stubEnv('DEV', true)
    schedulePwaSetup()()
    expect(window.requestAnimationFrame).not.toHaveBeenCalled()
    expect(setupPwa).not.toHaveBeenCalled()
  })
})
