<script lang="ts" setup>
import type { UseCart } from '@/modules/cart/composables/cart.ts'
import type { PosForm, PosMeta } from '@/modules/pos/contracts/posViewer.ts'
import { useI18n } from 'vue-i18n'
import DateTimePicker from '@/modules/core/components/Form/DateTimePicker.vue'
import PosExtensionRenderer from '@/modules/pos/components/PosExtensionRenderer.vue'

const props = defineProps<{
  form: PosForm
  meta: PosMeta
  cart: UseCart
}>()
const { processing, data } = props.cart
const { t } = useI18n()
const { form } = toRefs(props)
const isDeliveryOrder = computed(() => data.value.orderType?.id === 'delivery')
</script>

<template>
  <div class="pos-order-information">
    <VRow dense>
      <VCol
        v-if="['pre_order', 'catering'].includes(data.orderType?.id as string)"
        cols="12"
      >
        <DateTimePicker
          v-model="form.meta.scheduledAt"
          density="compact"
          clearable
          :disabled="processing"
          :label="t('order::attributes.orders.scheduled_at')"
          :min="new Date().toLocaleDateString('en-CA')"
        />
      </VCol>
      <template v-if="data.orderType?.id === 'drive_thru'">
        <VCol cols="12">
          <VTextField
            v-model="form.meta.carPlate"
            density="compact"
            clearable
            :label="t('order::attributes.orders.car_plate')"
            :readonly="processing"
          />
        </VCol>
        <VCol cols="12">
          <VTextField
            v-model="form.meta.carDescription"
            density="compact"
            clearable
            :label="t('order::attributes.orders.car_description')"
            :readonly="processing"
          />
        </VCol>
      </template>
      <PosExtensionRenderer
        mount="order-panel-additional-information"
        :cart="cart"
        :form="form"
        :meta="meta"
      />
      <VCol
        v-if="!isDeliveryOrder"
        cols="12"
      >
        <VTextarea
          v-model="form.meta.notes"
          auto-grow
          density="compact"
          clearable
          :label="t('order::attributes.orders.notes')"
          :readonly="processing"
          rows="3"
          max-rows="5"
        />
      </VCol>
      <VCol
        v-if="!isDeliveryOrder"
        cols="12"
      >
        <VTextField
          v-model="form.meta.guestCount"
          density="compact"
          prepend-inner-icon="tabler-users"
          :label="t('order::attributes.orders.guest_count')"
          :readonly="processing"
        />
      </VCol>
    </VRow>
  </div>
</template>
