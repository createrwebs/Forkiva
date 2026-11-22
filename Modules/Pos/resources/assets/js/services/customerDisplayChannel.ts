/** Same-browser transport for an allowlisted display snapshot; it carries no credentials. */
export function openCustomerDisplayChannel(scope: string): BroadcastChannel | null {
  try {
    return typeof BroadcastChannel === 'undefined'
      ? null
      : new BroadcastChannel(`forkiva:customer-display:${scope}`)
  } catch {
    return null
  }
}
