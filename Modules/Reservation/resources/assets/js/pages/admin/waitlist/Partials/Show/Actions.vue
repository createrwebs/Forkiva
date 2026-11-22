<script lang="ts" setup>
import { useI18n } from 'vue-i18n'

defineProps<{
  activeAction: 'notify' | 'cancel' | 'convert' | null
  canCancelWaitlist: boolean
  canConvertWaitlist: boolean
  canNotifyWaitlist: boolean
  canShowEdit: boolean
  hasActiveAction: boolean
  item: Record<string, any>
}>()

defineEmits<{
  (event: 'cancel' | 'convert' | 'notify'): void
}>()

const { t } = useI18n()
</script>

<template>
  <VCol cols="12">
    <div class="d-flex align-center justify-end flex-wrap ga-2">
      <div class="d-flex align-center flex-wrap ga-2 justify-end">
        <VBtn
          v-if="canNotifyWaitlist"
          color="primary"
          :disabled="hasActiveAction && activeAction !== 'notify'"
          :loading="activeAction === 'notify'"
          prepend-icon="tabler-bell"
          variant="tonal"
          @click="$emit('notify')"
        >
          {{ t('reservation::frontend.notify') }}
        </VBtn>

        <VBtn
          v-if="canCancelWaitlist"
          color="error"
          :disabled="hasActiveAction && activeAction !== 'cancel'"
          :loading="activeAction === 'cancel'"
          prepend-icon="tabler-x"
          variant="tonal"
          @click="$emit('cancel')"
        >
          {{ t('reservation::frontend.cancel') }}
        </VBtn>

        <VBtn
          v-if="canConvertWaitlist"
          color="info"
          :disabled="hasActiveAction && activeAction !== 'convert'"
          :loading="activeAction === 'convert'"
          prepend-icon="tabler-calendar-plus"
          variant="tonal"
          @click="$emit('convert')"
        >
          {{ t('reservation::frontend.convert_to_reservation') }}
        </VBtn>

        <VBtn
          v-if="canShowEdit"
          color="success"
          :disabled="hasActiveAction"
          prepend-icon="tabler-edit"
          :to="{ name: 'admin.reservation_waitlists.edit', params: { id: item.id } }"
          variant="tonal"
        >
          {{
            t('admin::resource.edit', {
              resource: t('reservation::reservation_waitlists.reservation_waitlist'),
            })
          }}
        </VBtn>
      </div>
    </div>
  </VCol>
</template>
