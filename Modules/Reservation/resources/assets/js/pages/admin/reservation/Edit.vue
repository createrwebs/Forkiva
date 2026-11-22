<script lang="ts" setup>
import { onBeforeMount, ref } from 'vue'
import { useRoute } from 'vue-router'
import PageStateWrapper from '@/modules/core/components/PageStateWrapper.vue'
import { useReservation } from '@/modules/reservation/composables/reservation.ts'
import CustomForm from './Partials/Form.vue'

const route = useRoute()
const { getShowData } = useReservation()

const loading = ref(false)
const isNotFound = ref(false)
const item = ref<Record<string, any> | null>(null)

function canEditReservation(reservation: Record<string, any>): boolean {
  return reservation.abilities?.can_edit === true
}

onBeforeMount(async () => {
  loading.value = true
  const response = await getShowData((route.params as Record<string, any>).id)
  if (response.status === 200) {
    if (canEditReservation(response.data)) {
      item.value = response.data
    } else {
      isNotFound.value = true
    }
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
