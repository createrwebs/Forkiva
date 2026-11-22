import {
  GridStackEngine,
  type GridItemHTMLElement,
  type GridStack,
  type GridStackMoveOpts,
  type GridStackNode,
} from 'gridstack'

export function packDashboardGridUpwards(grid: GridStack): void {
  grid.float(false)
  grid.float(true)
}

export function findDashboardGridCollision(
  grid: GridStack,
  moving: GridItemHTMLElement,
  target: { x: number; y: number },
  size: { w: number; h: number },
): GridItemHTMLElement | undefined {
  return grid.getGridItems().find((candidate) => {
    const node = candidate.gridstackNode
    return (
      candidate !== moving &&
      node?.x !== undefined &&
      node.y !== undefined &&
      node.w !== undefined &&
      node.h !== undefined &&
      target.x < node.x + node.w &&
      target.x + size.w > node.x &&
      target.y < node.y + node.h &&
      target.y + size.h > node.y
    )
  })
}

export class DashboardGridEngine extends GridStackEngine {
  protected override directionCollideCoverage(
    node: GridStackNode,
    options: GridStackMoveOpts,
    collisions: GridStackNode[],
  ): GridStackNode | undefined {
    const target = super.directionCollideCoverage(node, options, collisions)

    // GridStack normally swaps only in gravity mode. Keep free placement while
    // letting adjacent, equally sized cards exchange places without pushing a row down.
    if (
      this.float &&
      target &&
      node.w === target.w &&
      node.h === target.h &&
      this.swap(node, target)
    ) {
      return undefined
    }

    return target
  }
}
