<script lang="ts" setup>
import { onUnmounted, ref, watch } from 'vue'
import { isAxiosError } from 'axios'
import QRCodeVue3 from 'qrcode-vue3'
import { useI18n } from 'vue-i18n'
import { getPromptPayQr } from '@/modules/order/api/order.api.ts'

const props = defineProps<{
  orderId: string | number
  amount: number | string | null
  currency: string
}>()

const { t } = useI18n()
const payload = ref('')
const qrAmount = ref('')
const error = ref('')
const loading = ref(false)

// Ignore responses from superseded amount edits so the QR always matches
// the latest requested collection amount.
let requestSequence = 0
let debounceTimer: ReturnType<typeof setTimeout> | null = null

function errorMessage(cause: unknown): string {
  if (isAxiosError(cause)) {
    const message = cause.response?.data?.message
    if (typeof message === 'string' && message !== '') {
      return message
    }
  }

  return t('admin::admin.messages.something_went_wrong')
}

async function loadQr() {
  const sequence = ++requestSequence
  loading.value = true
  error.value = ''

  try {
    const response = await getPromptPayQr(props.orderId, props.amount ?? undefined)
    if (sequence !== requestSequence) return
    payload.value = response.data.body.payload
    qrAmount.value = String(response.data.body.amount?.amount ?? '')
  } catch (cause) {
    if (sequence !== requestSequence) return
    payload.value = ''
    error.value = errorMessage(cause)
  } finally {
    if (sequence === requestSequence) {
      loading.value = false
    }
  }
}

watch(
  () => [props.orderId, props.amount, props.currency],
  () => {
    if (debounceTimer) clearTimeout(debounceTimer)
    if (props.currency !== 'THB') {
      requestSequence++
      payload.value = ''
      error.value = ''
      loading.value = false

      return
    }
    debounceTimer = setTimeout(() => void loadQr(), 400)
  },
  { immediate: true },
)

onUnmounted(() => {
  if (debounceTimer) clearTimeout(debounceTimer)
  requestSequence++
})
</script>

<template>
  <div
    v-if="currency === 'THB'"
    class="promptpay-qr"
  >
    <div class="promptpay-qr__title">
      <VIcon
        icon="tabler-qrcode"
        size="18"
      />
      {{ t('payment::enums.payment_methods.promptpay') }}
    </div>
    <VProgressCircular
      v-if="loading"
      indeterminate
      size="24"
      width="2"
    />
    <template v-else-if="payload">
      <QRCodeVue3
        :background-options="{ color: '#ffffff', round: 0 }"
        :dots-options="{ type: 'square', color: '#10367d' }"
        :height="200"
        :qr-options="{ typeNumber: 0, mode: 'Byte', errorCorrectionLevel: 'M' }"
        :value="payload"
        :width="200"
      />
      <div class="promptpay-qr__amount">
        {{ t('order::orders.amount_to_be_paid') }}: {{ qrAmount }} {{ currency }}
      </div>
      <div class="promptpay-qr__hint">
        {{ t('pos::pos.promptpay_scan_hint') }}
      </div>
    </template>
    <div
      v-else-if="error"
      class="promptpay-qr__error"
    >
      {{ error }}
    </div>
  </div>
</template>

<style lang="scss" scoped>
.promptpay-qr {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  margin: 0 0 10px;
  padding: 12px;
  border: 1px dashed rgba(var(--v-theme-on-surface), 0.24);
  border-radius: 10px;
}

.promptpay-qr__title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
}

.promptpay-qr__amount {
  font-weight: 600;
}

.promptpay-qr__hint {
  font-size: 12px;
  opacity: 0.7;
}

.promptpay-qr__error {
  color: rgb(var(--v-theme-error));
  font-size: 13px;
}
</style>
