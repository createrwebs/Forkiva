<script lang="ts" setup>
import type { UseCart } from '@/modules/cart/composables/cart.ts'
import type { PosForm, PosMeta } from '@/modules/pos/contracts/posViewer.ts'
import { computed, ref, toRefs } from 'vue'
import { useAuth } from '@/modules/auth/composables/auth.ts'

const props = defineProps<{
  meta: PosMeta
  form: PosForm
  cart: UseCart
}>()

const emit = defineEmits<{
  (e: 'on-click-action', value: string): void
  (e: 'require-customer-for-order-type', value: string): void
}>()

const { processing, data, storeOrderType } = props.cart
const { can } = useAuth()
const loading = ref(false)
const targetId = ref<string | null>(null)
const { form } = toRefs(props)
const isTableOrderEdit = computed(() => form.value.mode === 'edit' && !!form.value.table)

const toggleOrderType = async (id: string) => {
  if (loading.value || processing.value) return
  if (isTableOrderEdit.value && id !== 'dine_in') return
  if (id === 'dine_in' && can('admin.tables.viewer')) {
    emit('on-click-action', 'table_viewer')
  } else {
    if (id === 'delivery' && !data.value.customer?.id) {
      emit('require-customer-for-order-type', id)
      return
    }

    if (id !== data.value.orderType?.id) {
      form.value.table = null
      loading.value = true
      targetId.value = id
      await storeOrderType(id)
      loading.value = false
      targetId.value = null
    }
  }
}
</script>

<template>
  <div class="order-type-scroll">
    <div class="scroll-container">
      <button
        type="button"
        v-for="type in meta.orderTypes"
        v-show="type.id !== 'dine_in'"
        :key="type.id"
        class="order-type-card"
        :class="{
          active: data.orderType?.id === type.id,
          'cursor-not-allowed': loading || processing || isTableOrderEdit,
        }"
        :disabled="loading || processing || isTableOrderEdit"
        :aria-pressed="data.orderType?.id === type.id"
        @click="toggleOrderType(type.id)"
      >
        <div class="type-info">
          <VIcon
            v-if="type.icon"
            :icon="type.icon"
          />
          <span class="name">{{ type.name }}</span>
        </div>
        <div
          v-if="loading && type.id === targetId"
          class="order-type-card-loading"
        >
          <VProgressCircular
            color="primary"
            indeterminate
            size="20"
            width="2"
          />
        </div>
      </button>
    </div>
  </div>
</template>
