import { describe, expect, it } from 'vitest'
import { DashboardGridEngine } from './dashboardGridEngine'

function createGrid() {
  const engine = new DashboardGridEngine({ column: 12, float: true })
  const first = engine.addNode({ id: 'first', x: 0, y: 0, w: 3, h: 2 })
  const second = engine.addNode({ id: 'second', x: 3, y: 0, w: 3, h: 2 })
  const chart = engine.addNode({ id: 'chart', x: 0, y: 2, w: 8, h: 5 })
  engine.beginUpdate(first)
  // Supply the drag state and measured rectangles normally populated by GridStack's DOM layer.
  Object.assign(first, { _moving: true })
  for (const node of engine.nodes) {
    Object.assign(node, {
      _rect: { x: node.x! * 100, y: node.y! * 100, w: node.w! * 100, h: node.h! * 100 },
    })
  }
  return { engine, first, second, chart }
}

describe('Dashboard grid dragging', () => {
  it('swaps neighboring KPI cards without displacing charts below them', () => {
    const { engine, first, second, chart } = createGrid()

    engine.moveNode(first, { x: 3, y: 0, rect: { x: 300, y: 0, w: 300, h: 200 } })

    expect(first).toMatchObject({ x: 3, y: 0, w: 3, h: 2 })
    expect(second).toMatchObject({ x: 0, y: 0, w: 3, h: 2 })
    expect(chart).toMatchObject({ x: 0, y: 2, w: 8, h: 5 })
  })

  it('keeps the layout still until the pointer crosses halfway over its neighbor', () => {
    const { engine, first, second } = createGrid()

    engine.moveNode(first, { x: 1, y: 0, rect: { x: 100, y: 0, w: 300, h: 200 } })

    expect(first).toMatchObject({ x: 0, y: 0 })
    expect(second).toMatchObject({ x: 3, y: 0 })
  })

  it('preserves free placement in empty grid space', () => {
    const { engine, first, second, chart } = createGrid()

    engine.moveNode(first, { x: 9, y: 4, rect: { x: 900, y: 400, w: 300, h: 200 } })

    expect(first).toMatchObject({ x: 9, y: 4 })
    expect(second).toMatchObject({ x: 3, y: 0 })
    expect(chart).toMatchObject({ x: 0, y: 2 })
  })

  it('resolves collisions between different sizes without overlapping widgets', () => {
    const { engine, first, chart } = createGrid()

    engine.moveNode(first, { x: 0, y: 2, rect: { x: 0, y: 200, w: 300, h: 200 } })

    expect(first).toMatchObject({ w: 3, h: 2 })
    expect(chart).toMatchObject({ w: 8, h: 5 })
    for (const node of engine.nodes) expect(engine.collide(node)).toBeUndefined()
  })
})
