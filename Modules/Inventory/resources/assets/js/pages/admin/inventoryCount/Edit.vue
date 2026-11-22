<script lang="ts" setup>
import PageStateWrapper from '@/modules/core/components/PageStateWrapper.vue'
import { useInventoryCount } from '@/modules/inventory/composables/inventoryCount.ts'
import Form from './Partials/Form.vue'

const route = useRoute()
const { getInventoryCount } = useInventoryCount()
const loading = ref(false)
const isNotFound = ref(false)
const item = ref<Record<string, unknown> | null>(null)

onBeforeMount(async () => {
  loading.value = true
  const response = await getInventoryCount(Number(route.params.id))
  if (response.status === 200 && response.data?.allow_edit) item.value = response.data
  else isNotFound.value = true
  loading.value = false
})
</script>

<template>
  <PageStateWrapper
    :loading="loading"
    :not-found="isNotFound"
  >
    <Form
      v-if="item"
      action="update"
      :item="item"
    />
  </PageStateWrapper>
</template>
