import { setupPwa } from '@/app/plugins/pwa.ts'

export function schedulePwaSetup(): () => void {
  if (import.meta.env.DEV) {
    return () => {}
  }

  let idleId: number | undefined
  let timerId: number | undefined
  let finished = false

  const cancel = () => {
    finished = true
    window.cancelAnimationFrame(frameId)
    if (idleId !== undefined) window.cancelIdleCallback(idleId)
    if (timerId !== undefined) window.clearTimeout(timerId)
    window.clearTimeout(deadlineId)
  }
  const run = () => {
    if (finished) return
    cancel()
    setupPwa()
  }

  // Background tabs can suspend animation frames, so the deadline must start now.
  const deadlineId = window.setTimeout(run, 5000)
  // Let the mounted app paint before beginning service-worker installation.
  const frameId = window.requestAnimationFrame(() => {
    if (finished) return
    if (typeof window.requestIdleCallback === 'function') {
      idleId = window.requestIdleCallback(run, { timeout: 5000 })
    } else {
      timerId = window.setTimeout(run, 0)
    }
  })

  return cancel
}
