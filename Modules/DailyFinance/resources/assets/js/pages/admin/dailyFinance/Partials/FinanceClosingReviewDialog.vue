<script lang="ts" setup>
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'
import type { DayClosing } from '@/modules/dailyfinance/contracts/dayClosing'
import FinanceClosingSummary from './FinanceClosingSummary.vue'

const props = defineProps<{ record: DayClosing; branchName: string }>()
const emit = defineEmits<{ close: [] }>()
const { t } = useI18n()
const titleId = useId()
const open = ref(true)
const activator = document.activeElement
const savedBranch = computed(
  () =>
    props.record.summary.branches.find((branch) => branch.id === props.record.branch_id)?.name ??
    props.branchName,
)

function afterLeave() {
  if (activator instanceof HTMLElement && activator.isConnected) {
    activator.focus({ preventScroll: true })
  }

  emit('close')
}
</script>

<template>
  <VDialog
    v-model="open"
    :aria-labelledby="titleId"
    max-width="800"
    scrollable
    @after-leave="afterLeave"
  >
    <VCard class="closing-review">
      <header class="closing-review__header">
        <div>
          <h2 :id="titleId">{{ t('dailyfinance::day_closings.review_title') }}</h2>
          <div class="closing-review__reference">
            <strong>{{ record.reference }}</strong>
            <TableStatus :item="record" />
          </div>
          <div class="closing-review__context">
            <span>
              <VIcon
                icon="tabler-calendar"
                size="16"
              />
              <bdi dir="ltr">{{ record.date_formatted }}</bdi>
            </span>
            <span>
              <VIcon
                icon="tabler-building-store"
                size="16"
              />
              {{ savedBranch }}
            </span>
          </div>
        </div>
        <VBtn
          :aria-label="t('dailyfinance::daily_finance.actions.dismiss')"
          icon="tabler-x"
          variant="text"
          color="on-surface"
          @click="open = false"
        />
      </header>
      <VCardText class="closing-review__body">
        <div class="closing-review__audit">
          <span>
            <VIcon
              icon="tabler-user"
              size="18"
            />
            {{ t('dailyfinance::day_closings.saved_by') }} {{ record.created_by.name }}
          </span>
          <span>
            <VIcon
              icon="tabler-clock"
              size="18"
            />
            {{ t('dailyfinance::day_closings.saved_at') }}
            <bdi dir="ltr">{{ record.created_at }}</bdi>
          </span>
        </div>
        <FinanceClosingSummary :summary="record.summary" />
        <section
          v-if="record.notes"
          class="closing-review__notes"
        >
          <h3>
            <VIcon
              icon="tabler-notes"
              size="22"
              color="secondary"
            />
            {{ t('dailyfinance::day_closings.notes') }}
          </h3>
          <p>{{ record.notes }}</p>
        </section>
      </VCardText>
      <footer class="closing-review__footer">
        <p>{{ t('dailyfinance::day_closings.saved_hint') }}</p>
        <VBtn
          color="secondary"
          variant="tonal"
          @click="open = false"
        >
          {{ t('dailyfinance::day_closings.dismiss') }}
        </VBtn>
      </footer>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.closing-review {
  border-radius: 14px !important;
}

.closing-review__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 18px 24px;
  border-block-end: 1px solid rgba(var(--v-theme-secondary), 0.12);
}

.closing-review__header > div {
  min-inline-size: 0;
}

.closing-review h2 {
  margin: 0;
  font-size: 1.25rem;
  line-height: 1.4;
}

.closing-review__reference {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-block-start: 6px;
  color: rgb(var(--v-theme-secondary));
  font-size: 0.875rem;
}

.closing-review__context,
.closing-review__audit {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 20px;
  font-size: 0.8125rem;
}

.closing-review__context {
  margin-block-start: 10px;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.closing-review__context > span,
.closing-review__audit > span {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.closing-review__body {
  display: grid;
  gap: 24px;
  padding: 20px 24px !important;
}

.closing-review__audit {
  justify-content: space-between;
  padding: 12px 14px;
  border-radius: 8px;
  background: rgba(var(--v-theme-secondary), 0.045);
}

.closing-review__notes h3 {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0 0 12px;
  font-size: 1.125rem;
}

.closing-review__notes p {
  margin: 0;
  padding: 14px 16px;
  border-radius: 8px;
  background: rgba(var(--v-theme-secondary), 0.045);
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  font-size: 0.875rem;
  line-height: 1.6;
}

.closing-review__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 14px 24px;
  border-block-start: 1px solid rgba(var(--v-theme-secondary), 0.12);
}

.closing-review__footer p {
  margin: 0;
  font-size: 0.75rem;
  line-height: 1.5;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

@media (max-width: 600px) {
  .closing-review__header,
  .closing-review__body,
  .closing-review__footer {
    padding-inline: 18px !important;
  }

  .closing-review__header {
    padding-block: 14px;
  }

  .closing-review__audit {
    flex-direction: column;
  }
}
</style>
