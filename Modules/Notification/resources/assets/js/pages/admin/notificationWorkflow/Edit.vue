<script lang="ts" setup>
import type { NotificationWorkflow } from '@/modules/notification/contracts/NotificationWorkflow.ts'
import PageStateWrapper from '@/modules/core/components/PageStateWrapper.vue'
import { useNotificationWorkflow } from '@/modules/notification/composables/notificationWorkflow.ts'
import Form from './Partials/Form.vue'

const route = useRoute()
const { getWorkflow } = useNotificationWorkflow()

const loading = ref(false)
const isNotFound = ref(false)
const item = ref<NotificationWorkflow | null>(null)

onBeforeMount(async () => {
  loading.value = true
  const response = await getWorkflow(
    String((route.params as Record<string, unknown>).key || ''),
    true,
  )

  if (response) {
    item.value = response
  } else {
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
    <Form
      v-if="item"
      :item="item"
    />
  </PageStateWrapper>
</template>
