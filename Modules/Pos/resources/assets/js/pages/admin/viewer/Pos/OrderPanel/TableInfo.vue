<script lang="ts" setup>
import { ref, toRefs } from 'vue'
import type { UseCart } from '@/modules/cart/composables/cart.ts'
import type { PosForm, PosMeta } from '@/modules/pos/contracts/posViewer.ts'

const props = defineProps<{
  form: PosForm
  meta: PosMeta
  cart: UseCart
}>()

defineEmits<{ (e: 'select-table'): void }>()
const loading = ref<boolean>(false)
const { form } = toRefs(props)

const removeTable = async () => {
  if (form.value.mode === 'edit') return
  loading.value = true
  const defaultOrderType = props.meta.orderTypes.find((type) => type.id !== 'dine_in')
  await (defaultOrderType?.id
    ? props.cart.storeOrderType(defaultOrderType.id)
    : props.cart.removeOrderType())
  form.value.table = null
  loading.value = false
}
</script>

<template>
  <div
    v-if="form.table"
    class="pos-current-table"
  >
    <button
      type="button"
      class="pos-current-table-select"
      @click="$emit('select-table')"
    >
      <VIcon icon="tabler-armchair" />
      <div class="text-h6 font-weight-bold">{{ form.table.name }}</div>
      <div class="text-caption text-grey">{{ form.table.zone }} • {{ form.table.floor }}</div>
    </button>
    <VBtn
      v-if="form.mode !== 'edit'"
      :disabled="loading"
      icon
      :loading="loading"
      text
      variant="text"
      @click="removeTable"
    >
      <VIcon icon="tabler-trash" />
    </VBtn>
  </div>
</template>
