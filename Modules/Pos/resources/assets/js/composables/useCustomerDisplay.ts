import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import type {
  CustomerDisplayCart,
  DisplayConnection,
} from '@/modules/pos/contracts/customerDisplay'
export function useCustomerDisplay(context: () => { scope: string; locale: string }) {
  const cart = ref<CustomerDisplayCart | null>(null)
  const connection = ref<DisplayConnection>('connecting')
  const lastUpdated = ref<Date | null>(null)
  const loading = ref(false)
  const terminal = computed(() => connection.value === 'unavailable')
  let channel: BroadcastChannel | null = null
  let timer: ReturnType<typeof setInterval> | undefined
  function retry() {
    channel?.postMessage({ type: 'request-snapshot' })
  }
  function connect() {
    channel?.close()
    cart.value = null
    lastUpdated.value = null
    if (typeof BroadcastChannel === 'undefined' || !context().scope) {
      connection.value = 'unavailable'
      return
    }
    channel = new BroadcastChannel(`forkiva:customer-display:${context().scope}`)
    channel.onmessage = (event) => {
      if (event.data?.type === 'snapshot') {
        cart.value = event.data.cart
        lastUpdated.value = new Date()
        connection.value = 'connected'
      }
      if (event.data?.type === 'closed') {
        cart.value = null
        connection.value = 'reconnecting'
      }
    }
    retry()
  }
  onMounted(() => {
    connect()
    timer = setInterval(() => {
      if (!lastUpdated.value || Date.now() - lastUpdated.value.getTime() > 15000) {
        connection.value = 'reconnecting'
        cart.value = null
      }
      retry()
    }, 5000)
  })
  watch(() => context().scope, connect)
  onBeforeUnmount(() => {
    channel?.close()
    clearInterval(timer)
  })
  return { cart, connection, lastUpdated, loading, terminal, retry }
}
