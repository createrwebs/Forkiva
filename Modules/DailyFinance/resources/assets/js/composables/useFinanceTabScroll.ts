import { watch, type Ref } from 'vue'
import { useResizeObserver } from '@vueuse/core'

/** Keep tab scrolling bound to current geometry, including responsive drawer resizing. */
export function useFinanceTabScroll(element: Ref<HTMLElement | null>, selection: Ref<unknown>) {
  const viewport = () =>
    element.value?.querySelector<HTMLElement>('.v-slide-group__container') ?? null

  function reveal(tab?: HTMLElement | null) {
    const container = viewport()
    const active =
      tab ?? container?.querySelector<HTMLElement>('[role="tab"][aria-selected="true"]')
    if (!container?.clientWidth || !active) return

    const bounds = container.getBoundingClientRect()
    const target = active.getBoundingClientRect()
    const offset =
      target.left < bounds.left
        ? target.left - bounds.left
        : Math.max(0, target.right - bounds.right)

    if (offset) container.scrollBy({ left: offset, behavior: 'instant' })
  }

  function focusTab(event: FocusEvent) {
    const tab =
      event.target instanceof HTMLElement ? event.target.closest<HTMLElement>('[role="tab"]') : null
    if (!tab || !viewport()?.contains(tab)) return

    // Vuetify's animated target can become unreachable while the layout is resizing.
    event.stopPropagation()
    reveal(tab)
  }

  function scrollTabs(event: MouseEvent) {
    const container = viewport()
    const arrow =
      event.target instanceof Element
        ? event.target.closest('.v-slide-group__prev, .v-slide-group__next')
        : null
    if (!container || !arrow || !element.value?.contains(arrow)) return

    event.stopPropagation()
    const direction = arrow.classList.contains('v-slide-group__prev') ? -1 : 1
    const rtl = getComputedStyle(container).direction === 'rtl' ? -1 : 1
    container.scrollBy({ left: container.clientWidth * direction * rtl, behavior: 'instant' })
  }

  watch(selection, () => reveal(), { flush: 'post' })
  useResizeObserver(viewport, () => reveal())

  return { focusTab, scrollTabs }
}
