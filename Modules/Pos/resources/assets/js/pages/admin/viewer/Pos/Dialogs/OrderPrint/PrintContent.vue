<script lang="ts" setup>
import type { AxiosError } from 'axios'
import { computed, ref, watch } from 'vue'
import type { useQintrix } from '@/modules/printer/composables/qintrix.ts'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useOrder } from '@/modules/order/composables/order.ts'

const props = defineProps<{
  orderId: string | number
  content: Record<string, any>
  registerId: number | null
  qintrix: ReturnType<typeof useQintrix>
  agentAvailable: boolean
}>()
const emit = defineEmits<{ (e: 'busy', value: boolean): void; (e: 'close'): void }>()
const { t } = useI18n()
const { printPreview } = useOrder()
const toast = useToast()

const printLoading = ref(false)
const previewLoading = ref(false)
const printUnavailableReason = computed(() => {
  if (!props.registerId && props.content.type.id !== 'kitchen')
    return t('order::orders.print_dialog.select_register')
  if (!props.agentAvailable) return t('order::orders.print_dialog.agent_unavailable')
  return ''
})

const busy = computed(() => printLoading.value || previewLoading.value)
watch(busy, (value) => emit('busy', value), { flush: 'sync' })

async function directPrint() {
  if (busy.value || printUnavailableReason.value) return
  try {
    printLoading.value = true
    const contentType = 'pdf'
    const response = (
      await printPreview(
        props.orderId,
        props.content.type.id,
        props.content?.id,
        props.registerId,
        contentType,
      )
    ).data.body
    if (props.agentAvailable) {
      await props.qintrix.createJob(
        response.content,
        response.printer.qintrix_id,
        response.printer.paper_size,
        `${props.orderId}`,
        `order-${props.content.type.id}`,
        contentType,
      )
    }
  } catch (error) {
    toast.error(
      (
        error as AxiosError<{
          message?: string
        }>
      ).response?.data?.message || t('core::errors.an_unexpected_error_occurred'),
    )
  } finally {
    printLoading.value = false
  }
}

async function previewPrint() {
  if (busy.value) return
  let printWindow: Window | null = null

  try {
    printWindow = window.open('', '_blank', 'width=800,height=600')
    if (!printWindow) {
      toast.error(t('order::orders.print_dialog.popup_blocked'))
      return
    }
    previewLoading.value = true

    const response = await printPreview(
      props.orderId,
      props.content.type.id,
      props.content?.id,
      props.registerId,
      'html',
    )

    const html = response.data.body.content

    if (printWindow.closed) return
    const doc = printWindow.document
    doc.open()

    const closeAfterPrint = () => {
      printWindow?.close()
    }

    printWindow.addEventListener('afterprint', closeAfterPrint)

    printWindow.addEventListener(
      'load',
      () => {
        if (printWindow?.closed) return
        printWindow!.focus()
        printWindow!.print()
      },
      { once: true },
    )

    doc.append(doc.createElement('html'))
    doc.documentElement.innerHTML = html
    doc.close()
  } catch (error) {
    toast.error(
      (
        error as AxiosError<{
          message?: string
        }>
      ).response?.data?.message || t('core::errors.an_unexpected_error_occurred'),
    )

    printWindow?.close()
  } finally {
    previewLoading.value = false
  }
}
</script>

<template>
  <div class="pos-print-completion">
    <div
      v-if="printUnavailableReason"
      class="pos-print-notice"
      role="status"
    >
      <VIcon
        icon="tabler-info-circle"
        size="23"
      />
      <p id="pos-print-notice-text">
        {{ printUnavailableReason }}
        <br />
        {{ t('order::orders.print_dialog.browser_available') }}
      </p>
    </div>
    <div class="pos-print-footer">
      <VBtn
        class="pos-print-cancel"
        :disabled="busy"
        color="default"
        variant="text"
        @click="emit('close')"
      >
        {{ t('admin::admin.buttons.cancel') }}
      </VBtn>
      <div class="pos-print-actions">
        <VBtn
          class="pos-direct-print"
          :disabled="!!printUnavailableReason || busy"
          :loading="printLoading"
          :aria-label="`${t('order::orders.print_dialog.direct_print')} — ${content.label}`"
          :aria-describedby="printUnavailableReason ? 'pos-print-notice-text' : undefined"
          prepend-icon="tabler-printer"
          color="primary"
          variant="tonal"
          @click="directPrint"
        >
          {{ t('order::orders.print_dialog.direct_print') }}
        </VBtn>
        <VBtn
          :disabled="busy"
          :loading="previewLoading"
          :aria-label="`${t('order::orders.print_dialog.preview_print')} — ${content.label}`"
          prepend-icon="tabler-eye"
          color="primary"
          variant="flat"
          @click="previewPrint"
        >
          {{ t('order::orders.print_dialog.preview_print') }}
        </VBtn>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.pos-print-completion {
  flex-shrink: 0;
  padding: 0 20px;
}
.pos-print-notice {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  margin-block-end: 14px;
  border-radius: 8px;
  background: var(--pos-soft);
  color: var(--pos-muted);
}
.pos-print-notice .v-icon {
  flex-shrink: 0;
}
.pos-print-notice p {
  margin: 0;
  font-size: 12px;
  line-height: 1.5;
}
.pos-print-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-block: 12px 16px;
  border-block-start: 1px solid var(--pos-border);
}
.pos-print-actions {
  display: flex;
  gap: 12px;
}
.pos-print-footer .v-btn {
  min-block-size: 42px;
  font-size: 13px;
  font-weight: 600;
  border-radius: 8px;
  padding-inline: 14px;
  letter-spacing: 0;
}
.pos-print-cancel {
  color: var(--pos-muted) !important;
  margin-inline-start: -12px;
}
.pos-direct-print.v-btn--disabled {
  background: var(--pos-border) !important;
  color: var(--pos-muted) !important;
  opacity: 1;
}
.pos-direct-print.v-btn--disabled :deep(.v-btn__overlay) {
  opacity: 0;
}
@media (max-width: 600px) {
  .pos-print-completion {
    padding-inline: 16px;
  }
  .pos-print-notice {
    gap: 10px;
    padding: 10px;
    margin-block-end: 12px;
  }
  .pos-print-notice p {
    font-size: 12px;
  }
  .pos-print-footer {
    flex-wrap: wrap;
    padding-block: 8px 12px;
  }
  .pos-print-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    inline-size: 100%;
    gap: 8px;
  }
  .pos-print-footer .v-btn {
    font-size: 12px;
    padding-inline: 10px;
  }
}
</style>
