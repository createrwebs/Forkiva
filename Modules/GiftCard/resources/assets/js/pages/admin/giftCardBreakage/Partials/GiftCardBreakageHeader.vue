<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import Money from '@/modules/core/components/Money.vue'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'

defineProps<{
  item: any
  actions: string[]
  loading: boolean
}>()

const emit = defineEmits<{
  review: []
  post: []
  reverse: []
}>()

const { t } = useI18n()
</script>

<template>
  <VCard class="breakage-header mb-5">
    <VCardText class="d-flex flex-wrap align-center gap-4 pa-5">
      <VAvatar
        color="primary"
        rounded
        size="48"
        variant="tonal"
      >
        <VIcon
          icon="tabler-gift-card"
          size="26"
        />
      </VAvatar>

      <div class="breakage-header__identity">
        <div class="d-flex flex-wrap align-center gap-2">
          <h2 class="text-h5 font-weight-bold">{{ item.reference_no }}</h2>
          <TableStatus :item="item" />
        </div>

        <div
          class="d-flex flex-wrap align-center gap-x-4 gap-y-1 mt-1 text-body-2 text-medium-emphasis"
        >
          <span class="d-flex align-center gap-1">
            <VIcon
              icon="tabler-building-store"
              size="16"
            />
            {{ item.branch.name }}
          </span>

          <span class="d-flex align-center gap-1">
            <VIcon
              icon="tabler-calendar"
              size="16"
            />
            {{ item.economic_date }}
          </span>
        </div>
      </div>

      <VSpacer />

      <div class="d-flex flex-wrap gap-2">
        <VBtn
          v-if="actions.includes('review')"
          color="info"
          prepend-icon="tabler-shield-check"
          variant="tonal"
          @click="emit('review')"
        >
          {{ t('giftcard::gift_card_breakage_batches.actions.review') }}
        </VBtn>

        <VBtn
          v-if="actions.includes('post')"
          color="primary"
          :loading="loading"
          prepend-icon="tabler-send"
          @click="emit('post')"
        >
          {{ t('giftcard::gift_card_breakage_batches.actions.post') }}
        </VBtn>

        <VBtn
          v-if="actions.includes('reverse')"
          color="error"
          prepend-icon="tabler-arrow-back-up"
          variant="tonal"
          @click="emit('reverse')"
        >
          {{ t('giftcard::gift_card_breakage_batches.actions.reverse') }}
        </VBtn>
      </div>
    </VCardText>

    <div class="breakage-header__metrics">
      <div class="breakage-header__metric breakage-header__metric--amount">
        <div class="breakage-header__metric-icon">
          <VIcon
            icon="tabler-cash"
            size="21"
          />
        </div>

        <div class="min-w-0">
          <div class="breakage-header__metric-label">
            {{ t('giftcard::gift_card_breakage_batches.table.total_amount') }}
          </div>

          <div class="breakage-header__metric-value">
            <Money :money="item.total_amount" />
          </div>
        </div>
      </div>

      <div class="breakage-header__metric breakage-header__metric--rate">
        <div class="breakage-header__metric-icon">
          <VIcon
            icon="tabler-arrows-exchange"
            size="21"
          />
        </div>

        <div class="min-w-0">
          <div class="breakage-header__metric-label">
            {{ t('giftcard::gift_card_breakage_batches.table.currency_rate') }}
          </div>

          <div class="breakage-header__metric-value">
            {{ item.currency_rate }}
          </div>
        </div>
      </div>

      <div class="breakage-header__metric breakage-header__metric--count">
        <div class="breakage-header__metric-icon">
          <VIcon
            icon="tabler-cards"
            size="21"
          />
        </div>

        <div class="min-w-0">
          <div class="breakage-header__metric-label">
            {{ t('giftcard::gift_card_breakage_batches.card_count') }}
          </div>

          <div class="breakage-header__metric-value">
            {{ item.lines.length }}
          </div>
        </div>
      </div>
    </div>
  </VCard>
</template>

<style lang="scss" scoped>
.breakage-header {
  overflow: hidden;
}

.breakage-header__identity {
  min-width: 220px;
}

.breakage-header__metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  padding: 0 20px 20px;
}

.breakage-header__metric {
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 0;
  padding: 14px 16px;
  border: 1px solid rgb(var(--v-theme-on-surface), 0.08);
  border-radius: 10px;
  background: rgb(var(--v-theme-on-surface), 0.025);
}

.breakage-header__metric-icon {
  display: grid;
  flex: 0 0 40px;
  block-size: 40px;
  border-radius: 9px;
  inline-size: 40px;
  place-items: center;
}

.breakage-header__metric-label {
  overflow: hidden;
  color: rgb(var(--v-theme-on-surface), 0.62);
  font-size: 0.75rem;
  line-height: 1.2;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.breakage-header__metric-value {
  overflow: hidden;
  margin-block-start: 4px;
  color: rgb(var(--v-theme-on-surface), 0.9);
  font-size: 1.05rem;
  font-weight: 700;
  line-height: 1.25;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.breakage-header__metric--amount .breakage-header__metric-icon {
  color: rgb(var(--v-theme-primary));
  background: rgb(var(--v-theme-primary), 0.1);
}

.breakage-header__metric--rate .breakage-header__metric-icon {
  color: rgb(var(--v-theme-info));
  background: rgb(var(--v-theme-info), 0.1);
}

.breakage-header__metric--count .breakage-header__metric-icon {
  color: rgb(var(--v-theme-success));
  background: rgb(var(--v-theme-success), 0.1);
}

@media (max-width: 599px) {
  .breakage-header__metrics {
    grid-template-columns: 1fr;
    padding: 0 16px 16px;
  }
}
</style>
