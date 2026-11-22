import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { computed, defineComponent } from 'vue'
import { useKitchenAutoReload, type KitchenAutoReloadSettings } from './kitchenAutoReload'

afterEach(() => vi.useRealTimers())
const config: KitchenAutoReloadSettings = {
  enabled: true,
  mode: 'smart_polling',
  interval: 1000,
  pause_on_idle: false,
  idle_timeout: 2,
}
describe('Kitchen polling resilience', () => {
  it('retries transient failures and stops after unmount', async () => {
    vi.useFakeTimers()
    const fetchData = vi
      .fn()
      .mockRejectedValueOnce(new Error('offline'))
      .mockResolvedValue({ last_updated_at: null })
    const wrapper = mount(
      defineComponent({
        setup() {
          useKitchenAutoReload({ settings: computed(() => config), fetchData })
          return () => null
        },
      }),
    )
    await vi.advanceTimersByTimeAsync(2100)
    expect(fetchData).toHaveBeenCalledTimes(2)
    wrapper.unmount()
    await vi.advanceTimersByTimeAsync(3000)
    expect(fetchData).toHaveBeenCalledTimes(2)
  })
  it('resumes idle polling on touchscreen input', async () => {
    vi.useFakeTimers()
    const fetchData = vi.fn().mockResolvedValue({ last_updated_at: null })
    const wrapper = mount(
      defineComponent({
        setup() {
          useKitchenAutoReload({
            settings: computed(() => ({ ...config, pause_on_idle: true })),
            fetchData,
          })
          return () => null
        },
      }),
    )
    await vi.advanceTimersByTimeAsync(3100)
    const pausedCount = fetchData.mock.calls.length
    await vi.advanceTimersByTimeAsync(2000)
    expect(fetchData).toHaveBeenCalledTimes(pausedCount)
    window.dispatchEvent(new Event('pointerdown'))
    await vi.advanceTimersByTimeAsync(1000)
    expect(fetchData).toHaveBeenCalledTimes(pausedCount + 1)
    wrapper.unmount()
  })
})
