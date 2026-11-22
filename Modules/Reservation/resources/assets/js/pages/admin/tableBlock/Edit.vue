<script lang="ts" setup>
import { onBeforeMount, ref } from 'vue'
import { useRoute } from 'vue-router'
import PageStateWrapper from '@/modules/core/components/PageStateWrapper.vue'
import { useReservationTableBlock } from '@/modules/reservation/composables/reservationTableBlock.ts'
import CustomForm from './Partials/Form.vue'

const route = useRoute()
const { getShowData } = useReservationTableBlock()
const loading = ref(false)
const isNotFound = ref(false)
const item = ref<Record<string, any> | null>(null)

onBeforeMount(async () => {
  loading.value = true
  const response = await getShowData((route.params as Record<string, any>).id, true)
  if (response.status === 200) {
    item.value = response.data
  } else if (response.status === 404) {
    isNotFound.value = true
  }
  loading.value = false
})
</script>

<template>
  <PageStateWrapper
    :loading="loading"
    :not-found="isNotFound"
  >
    <CustomForm
      v-if="item"
      action="update"
      :item="item"
    />
  </PageStateWrapper>
</template>
