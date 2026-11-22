<script lang="ts" setup>
import { useI18n } from 'vue-i18n'

defineProps<{
  activeAction: 'status' | 'assign' | 'auto_assign' | null
  canShowEdit: boolean
  hasActiveAction: boolean
  hasStatusActions: boolean
  item: Record<string, any>
}>()

defineEmits<{
  (event: 'change-status'): void
}>()

const { t } = useI18n()
</script>

<template>
  <VCol cols="12">
    <div class="d-flex align-center justify-end flex-wrap ga-2">
      <VBtn
        v-if="hasStatusActions"
        color="primary"
        :disabled="hasActiveAction && activeAction !== 'status'"
        :loading="activeAction === 'status'"
        prepend-icon="tabler-checkup-list"
        variant="tonal"
        @click="$emit('change-status')"
      >
        {{ t('reservation::frontend.change_status') }}
      </VBtn>

      <VBtn
        v-if="canShowEdit"
        color="success"
        :disabled="hasActiveAction"
        prepend-icon="tabler-edit"
        :to="{ name: 'admin.reservations.edit', params: { id: item.id } }"
        variant="tonal"
      >
        {{
          t('admin::resource.edit', {
            resource: t('reservation::reservations.reservation'),
          })
        }}
      </VBtn>
    </div>
  </VCol>
</template>
