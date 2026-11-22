<script setup lang="ts">
import { ref } from 'vue'
import { lookupProductByBarcode } from '@/modules/pos/api/retailPos.ts'

const props = defineProps<{
  pendingOfflineCount: number
  isSyncingOffline: boolean
}>()

const emit = defineEmits<{
  scanned: [result: unknown]
  holdCart: []
  syncOffline: []
}>()

const barcodeInput = ref('')
const scanError = ref<string | null>(null)
const isLookingUp = ref(false)

async function handleScan(): Promise<void> {
  const code = barcodeInput.value.trim()
  if (!code) return

  isLookingUp.value = true
  scanError.value = null

  try {
    const result = await lookupProductByBarcode(code)
    emit('scanned', result)
    barcodeInput.value = ''
  } catch {
    scanError.value = `ไม่พบสินค้าสำหรับบาร์โค้ด ${code}`
  } finally {
    isLookingUp.value = false
  }
}
</script>

<template>
  <div class="d-flex align-center ga-2 flex-wrap">
    <VTextField
      v-model="barcodeInput"
      density="compact"
      hide-details
      placeholder="ยิงบาร์โค้ดสินค้า (EAN-13 / SKU)..."
      prepend-inner-icon="tabler-barcode"
      :loading="isLookingUp"
      style="max-width: 300px"
      @keydown.enter.prevent="handleScan"
    />
    <VBtn variant="tonal" color="secondary" @click="emit('holdCart')">
      พักบิล
    </VBtn>
    <VBtn
      v-if="props.pendingOfflineCount > 0"
      variant="tonal"
      color="warning"
      :loading="props.isSyncingOffline"
      @click="emit('syncOffline')"
    >
      ซิงก์ออฟไลน์ ({{ props.pendingOfflineCount }})
    </VBtn>
    <span v-if="scanError" class="text-error text-caption">{{ scanError }}</span>
  </div>
</template>
