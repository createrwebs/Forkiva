import type { Draft } from '../contracts/draft'

let database: Promise<IDBDatabase> | undefined
/** Reuse the database connection and reopen it after schema changes or an opening failure. */
function open(): Promise<IDBDatabase> {
  if (!database) {
    database = new Promise((resolve, reject) => {
      const request = indexedDB.open('forkiva-cart-drafts', 1)
      request.onupgradeneeded = () => request.result.createObjectStore('drafts', { keyPath: 'key' })
      request.onerror = () => {
        database = undefined
        reject(request.error)
      }
      request.onsuccess = () => {
        request.result.onversionchange = () => {
          request.result.close()
          database = undefined
        }
        resolve(request.result)
      }
      request.onblocked = () => reject(new Error('draft_storage_blocked'))
    }).catch((error) => {
      database = undefined
      throw error
    }) as Promise<IDBDatabase>
  }

  return database
}
export class DraftConflict extends Error {}
/** Read one ownership-scoped draft without mutating its revision. */
export async function readDraft(key: string): Promise<Draft | undefined> {
  const db = await open()
  return new Promise((resolve, reject) => {
    const tx = db.transaction('drafts', 'readonly')
    const request = tx.objectStore('drafts').get(key)
    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(request.error)
  })
}
/** Compare and increment the revision in the same transaction; resolve only after commit. */
export async function writeDraft(draft: Draft, expected: number): Promise<Draft> {
  const db = await open()
  return new Promise((resolve, reject) => {
    const tx = db.transaction('drafts', 'readwrite')
    const store = tx.objectStore('drafts')
    let conflict = false
    let result: Draft
    const request = store.get(draft.key)
    request.onsuccess = () => {
      if ((request.result?.revision ?? 0) !== expected) {
        conflict = true
        tx.abort()
        return
      }
      result = { ...draft, revision: expected + 1, updatedAt: Date.now() }
      store.put(result)
    }
    tx.oncomplete = () => resolve(result)
    tx.onabort = tx.onerror = () =>
      reject(
        conflict
          ? new DraftConflict('draft_changed')
          : (tx.error ?? new Error('draft_storage_failed')),
      )
  })
}
/** Delete every draft owned by an explicitly logged-out account, then notify other tabs. */
export async function removeAccountDrafts(accountId: number): Promise<void> {
  const db = await open()
  await new Promise<void>((resolve, reject) => {
    const tx = db.transaction('drafts', 'readwrite')
    const request = tx.objectStore('drafts').openCursor()
    request.onsuccess = () => {
      const cursor = request.result
      if (cursor) {
        if (cursor.value.accountId === accountId) cursor.delete()
        cursor.continue()
      }
    }
    tx.oncomplete = () => resolve()
    tx.onabort = tx.onerror = () => reject(tx.error)
  })
  const channel =
    typeof BroadcastChannel === 'undefined' ? null : new BroadcastChannel('forkiva:drafts')
  channel?.postMessage({ accountRemoved: accountId })
  channel?.close()
}

/** List persisted drafts for recovery; callers must filter by authenticated ownership. */
export async function listDrafts(): Promise<Draft[]> {
  const db = await open()
  return new Promise((resolve, reject) => {
    const tx = db.transaction('drafts', 'readonly')
    const request = tx.objectStore('drafts').getAll()
    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(request.error)
  })
}

/** Atomically move an explicitly recovered draft to the new open session. */
export async function moveDraft(source: Draft, target: Draft): Promise<Draft> {
  const db = await open()
  return new Promise((resolve, reject) => {
    const tx = db.transaction('drafts', 'readwrite')
    const store = tx.objectStore('drafts')
    let saved: Draft
    const sourceRequest = store.get(source.key)
    const targetRequest = store.get(target.key)
    targetRequest.onsuccess = () => {
      if (
        sourceRequest.result?.revision !== source.revision ||
        targetRequest.result?.revision !== target.revision ||
        targetRequest.result?.lines.length ||
        targetRequest.result?.submission
      ) {
        tx.abort()
        return
      }
      saved = {
        ...source,
        key: target.key,
        revision: target.revision + 1,
        context: { ...source.context, ...target.context, form: source.context.form },
        quote: undefined,
        updatedAt: Date.now(),
      }
      store.put(saved)
      store.delete(source.key)
    }
    tx.oncomplete = () => resolve(saved)
    tx.onabort = tx.onerror = () => reject(new DraftConflict('draft_changed'))
  })
}
