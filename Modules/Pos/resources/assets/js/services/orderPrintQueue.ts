import { ref } from 'vue'

export const pendingOrderPrints = ref(0)
let queue = Promise.resolve()

/** Preserve printer job order while allowing the cashier to start the next ticket. */
export function enqueueOrderPrint(print: () => Promise<void>, onError: () => void) {
  pendingOrderPrints.value++
  const run = queue
    .then(print)
    .catch(onError)
    .finally(() => {
      pendingOrderPrints.value--
    })
  queue = run.catch(() => undefined)
  return run
}
