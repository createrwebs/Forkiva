import { GridStackEngine, type GridStackMoveOpts, type GridStackNode } from 'gridstack'

export class AnalyticsGridEngine extends GridStackEngine {
  protected override directionCollideCoverage(
    node: GridStackNode,
    options: GridStackMoveOpts,
    collisions: GridStackNode[],
  ): GridStackNode | undefined {
    const target = super.directionCollideCoverage(node, options, collisions)
    if (
      this.float &&
      target &&
      node.w === target.w &&
      node.h === target.h &&
      this.swap(node, target)
    )
      return undefined
    return target
  }
}
