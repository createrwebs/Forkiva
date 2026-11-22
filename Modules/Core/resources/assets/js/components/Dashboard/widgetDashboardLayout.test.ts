import { ref } from 'vue'
import { describe, expect, it } from 'vitest'
import { useWidgetDashboardLayout } from './widgetDashboardLayout.ts'

describe('shared dashboard layout', () => {
  it('restores the account layout ahead of a different browser layout', () => {
    const storageKey = 'forkiva.preference-test.layout.v1.919'
    localStorage.setItem(
      storageKey,
      JSON.stringify([{ key: 'sales', visible: false, minimized: false, order: 0 }]),
    )
    const definitions = [
      { key: 'sales', kind: 'metric', cols: 3, defaultVisible: true },
      { key: 'orders', kind: 'metric', cols: 3, defaultVisible: true },
    ]
    const { layout } = useWidgetDashboardLayout(
      'preference-test',
      1,
      919,
      definitions,
      ref(new Set(['sales', 'orders'])),
      undefined,
      [{ key: 'orders', visible: false, minimized: true, order: 0 }],
    )

    expect(layout.value.find((item) => item.key === 'orders')).toMatchObject({
      visible: false,
      minimized: true,
    })
    expect(layout.value.find((item) => item.key === 'sales')?.visible).toBe(true)
    localStorage.removeItem(storageKey)
  })
})
