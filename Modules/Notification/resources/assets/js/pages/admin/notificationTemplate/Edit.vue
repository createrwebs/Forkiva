<script lang="ts" setup>
import type { NotificationTemplate } from '@/modules/notification/contracts/NotificationTemplate.ts'
import PageStateWrapper from '@/modules/core/components/PageStateWrapper.vue'
import { useNotificationTemplate } from '@/modules/notification/composables/notificationTemplate.ts'
import Form from './Partials/Form.vue'

const route = useRoute()
const { getTemplate } = useNotificationTemplate()

const loading = ref(false)
const isNotFound = ref(false)
const item = ref<NotificationTemplate | null>(null)

onBeforeMount(async () => {
  loading.value = true
  const response = await getTemplate(
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
