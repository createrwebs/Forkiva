<script setup lang="ts">
import { ref } from 'vue'
import {
  verifyThaiSlip,
  type SlipVerificationDriver,
  type SlipVerificationRecord,
} from '@/modules/payment/api/slipVerification.ts'

const props = defineProps<{
  modelValue: boolean
  branchId: number
  expectedAmount: string
  orderId?: number
  sessionId?: number
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  verified: [record: SlipVerificationRecord]
}>()

const qrPayload = ref('')
const driver = ref<SlipVerificationDriver>('local_mini_qr')
const isVerifying = ref(false)
const errorMessage = ref<string | null>(null)
const verifiedRecord = ref<SlipVerificationRecord | null>(null)

async function submitVerification(): Promise<void> {
  if (!qrPayload.value.trim()) {
    errorMessage.value = 'กรุณาสแกนหรือวางข้อมูล QR สลิปโอนเงิน'
    return
  }

  isVerifying.value = true
  errorMessage.value = null

  try {
    const record = await verifyThaiSlip({
      branch_id: props.branchId,
      order_id: props.orderId,
      session_id: props.sessionId,
      auto_pay: Boolean(props.orderId && props.sessionId),
      qr_payload: qrPayload.value.trim(),
      expected_amount: props.expectedAmount,
      driver: driver.value,
    })

    verifiedRecord.value = record
    emit('verified', record)
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'ไม่สามารถตรวจสอบสลิปได้'
  } finally {
    isVerifying.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="520"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard>
      <VCardTitle>ตรวจสอบสลิปโอนเงิน (Thai Mini-QR)</VCardTitle>
      <VCardText>
        <div class="mb-3 text-body-2">ยอดที่ต้องชำระ: ฿{{ expectedAmount }}</div>
        <VSelect
          v-model="driver"
          label="ช่องทางตรวจสอบ"
          :items="[
            { title: 'Self-Hosted BOT Mini-QR', value: 'local_mini_qr' },
            { title: 'SCB Open API (ตรงธนาคาร)', value: 'scb_open_api' },
            { title: 'KBank Open API (ตรงธนาคาร)', value: 'kbank_open_api' },
          ]"
          item-title="title"
          item-value="value"
          class="mb-3"
        />
        <VTextarea
          v-model="qrPayload"
          label="สแกน QR จากสลิป"
          rows="3"
          autofocus
          @keydown.enter.prevent="submitVerification"
        />
        <VAlert v-if="errorMessage" type="error" variant="tonal" class="mt-2">
          {{ errorMessage }}
        </VAlert>
        <VAlert v-if="verifiedRecord" type="success" variant="tonal" class="mt-2">
          ยืนยันสำเร็จ • Ref: {{ verifiedRecord.trans_ref }} • ฿{{ verifiedRecord.amount }}
        </VAlert>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn variant="text" @click="emit('update:modelValue', false)">ปิด</VBtn>
        <VBtn color="primary" :loading="isVerifying" @click="submitVerification">
          ตรวจสอบสลิป
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
