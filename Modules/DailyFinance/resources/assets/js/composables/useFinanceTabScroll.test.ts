import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { effectScope, nextTick, ref } from 'vue'
import { useFinanceTabScroll } from './useFinanceTabScroll'

const observer = vi.hoisted(() => ({ resize: () => {} }))
vi.mock('@vueuse/core', () => ({
  useResizeObserver: (_target: unknown, callback: () => void) => {
    observer.resize = callback
  },
}))

let scope: ReturnType<typeof effectScope>
let root: HTMLElement
let container: HTMLElement
let active: HTMLElement
let width: number
const scrollBy = vi.fn()

beforeEach(() => {
  scope = effectScope()
  root = document.createElement('div')
  root.innerHTML = `
    <div class="v-slide-group__prev"><span>Previous</span></div>
    <div class="v-slide-group__container">
      <button role="tab" aria-selected="true">First</button>
      <button role="tab" aria-selected="false">Last</button>
    </div>
    <div class="v-slide-group__next"><span>Next</span></div>
  `
  document.body.append(root)
  container = root.querySelector<HTMLElement>('.v-slide-group__container')!
  active = root.querySelector<HTMLElement>('[aria-selected="true"]')!
  width = 100
  Object.defineProperty(container, 'clientWidth', { get: () => width })
  container.getBoundingClientRect = () => new DOMRect(0, 0, width, 44)
  active.getBoundingClientRect = () => new DOMRect(90, 0, 80, 44)
  container.scrollBy = scrollBy
  scrollBy.mockClear()
})

afterEach(() => {
  scope.stop()
  root.remove()
})

function setup() {
  const selection = ref('first')
  const handlers = scope.run(() => useFinanceTabScroll(ref(root), selection))!
  root.addEventListener('focusin', handlers.focusTab, true)
  root.addEventListener('click', handlers.scrollTabs, true)

  return selection
}

describe('daily finance tab scrolling', () => {
  it('uses current bounds after every resize and ignores collapsed layouts', () => {
    setup()
    observer.resize()
    expect(scrollBy).toHaveBeenLastCalledWith({ left: 70, behavior: 'instant' })

    width = 0
    observer.resize()
    expect(scrollBy).toHaveBeenCalledTimes(1)

    width = 150
    observer.resize()
    expect(scrollBy).toHaveBeenLastCalledWith({ left: 20, behavior: 'instant' })

    width = 200
    observer.resize()
    expect(scrollBy).toHaveBeenCalledTimes(2)
  })

  it('reveals a changed selection after the tab DOM updates', async () => {
    const selection = setup()
    const last = root.querySelector<HTMLElement>('[aria-selected="false"]')!
    last.getBoundingClientRect = () => new DOMRect(-30, 0, 80, 44)
    active.setAttribute('aria-selected', 'false')
    last.setAttribute('aria-selected', 'true')
    selection.value = 'last'
    await nextTick()
    expect(scrollBy).toHaveBeenLastCalledWith({ left: -30, behavior: 'instant' })
  })

  it('reveals focused tabs without starting Vuetify scrolling or changing selection', () => {
    const selection = setup()
    const animate = vi.fn()
    container.addEventListener('focusin', animate)
    active.dispatchEvent(new FocusEvent('focusin', { bubbles: true }))
    expect(scrollBy).toHaveBeenCalledWith({ left: 70, behavior: 'instant' })
    expect(animate).not.toHaveBeenCalled()
    expect(selection.value).toBe('first')
  })

  it('scrolls arrow clicks in both directions and respects RTL without a second animation', () => {
    setup()
    const next = root.querySelector<HTMLElement>('.v-slide-group__next')!
    const previous = root.querySelector<HTMLElement>('.v-slide-group__prev')!
    const animate = vi.fn()
    next.addEventListener('click', animate)
    next.querySelector('span')!.click()
    expect(scrollBy).toHaveBeenLastCalledWith({ left: 100, behavior: 'instant' })
    expect(animate).not.toHaveBeenCalled()

    previous.click()
    expect(scrollBy).toHaveBeenLastCalledWith({ left: -100, behavior: 'instant' })
    container.style.direction = 'rtl'
    next.click()
    expect(scrollBy).toHaveBeenLastCalledWith({ left: -100, behavior: 'instant' })
  })
})
