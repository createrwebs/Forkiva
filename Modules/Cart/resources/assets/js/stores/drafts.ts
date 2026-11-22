import { defineStore } from 'pinia'
import { shallowReactive } from 'vue'
import type { Draft } from '../contracts/draft'
export const useDraftStore = defineStore('cart-drafts', () => {
  const drafts = shallowReactive<Record<string, Draft>>({})
  /** Apply committed snapshots monotonically so stale broadcasts cannot replace newer state. */
  function set(draft: Draft) {
    if ((drafts[draft.key]?.revision ?? -1) <= draft.revision) drafts[draft.key] = draft
  }
  /** Detach all in-memory drafts for an explicitly removed account. */
  function forgetAccount(id: number) {
    for (const [key, draft] of Object.entries(drafts))
      if (draft.accountId === id) delete drafts[key]
  }
  return { drafts, set, forgetAccount }
})
