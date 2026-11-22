<script lang="ts" setup>
import { onBeforeMount, ref } from 'vue'
import { useRoute } from 'vue-router'
import PageStateWrapper from '@/modules/core/components/PageStateWrapper.vue'
import { useReservationSetting } from '@/modules/reservation/composables/reservationSetting.ts'
import CustomForm from './Partials/Form.vue'

const route = useRoute()
const { getShowData } = useReservationSetting()
const loading = ref(false)
const isNotFound = ref(false)
const item = ref<Record<string, any> | null>(null)

onBeforeMount(async () => {
  loading.value = true
  const response = await getShowData((route.params as Record<string, any>).id)
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
