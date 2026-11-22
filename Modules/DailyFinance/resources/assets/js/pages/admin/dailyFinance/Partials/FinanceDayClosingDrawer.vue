<script lang="ts" setup>
import { computed, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'
import { useDayClosings } from '@/modules/dailyfinance/composables/useDayClosings'
import type { DayClosing, DayClosingReview } from '@/modules/dailyfinance/contracts/dayClosing'
import FinanceDrawer from './FinanceDrawer.vue'
import FinanceClosingSummary from './FinanceClosingSummary.vue'
import FinanceClosingHistory from './FinanceClosingHistory.vue'
import FinanceClosingReviewDialog from './FinanceClosingReviewDialog.vue'

const props = defineProps<{ branchId: number; date: string; branchName: string }>()
const emit = defineEmits<{
  close: []
  saved: []
  sessions: []
  handovers: []
  reviewed: [review: DayClosingReview]
}>()
const { t } = useI18n()
const { can } = useAuth()
const {
  review,
  history,
  loading,
  refreshing,
  saving,
  uncertain,
  error,
  errors,
  load,
  refresh,
  save,
} = useDayClosings(() => ({ branchId: props.branchId, date: props.date }))
const drawer = ref<InstanceType<typeof FinanceDrawer> | null>(null)
const sourceAfterClose = ref<'sessions' | 'handovers' | null>(null)
const tab = ref<'review' | 'history'>('review')
const confirming = ref(false)
const selectedClosing = ref<DayClosing | null>(null)
const confirmationTitleId = useId()
const notes = ref('')
const acknowledged = ref(false)
const savedReview = ref(false)
const busy = computed(() => saving.value || uncertain.value)
const actionsDisabled = computed(() => busy.value || loading.value)
const canConfirm = computed(
  () =>
    !saving.value &&
    acknowledged.value &&
    (!review.value?.warnings.length || notes.value.trim().length >= 3),
)
const checks = computed(() => review.value?.checks)

watch(
  () => review.value?.closing,
  (closing) => {
    if (review.value) savedReview.value = !!closing
  },
  { immediate: true },
)
watch(
  [review, loading, saving, error],
  ([value, isLoading, isSaving, failure]) => {
    if (value && !isLoading && !isSaving && !failure) emit('reviewed', value)
  },
  { immediate: true },
)
watch(tab, (value) => {
  void load(value)
})
watch(notes, () => {
  acknowledged.value = false
})

async function confirm() {
  if (!uncertain.value && !canConfirm.value) return
  if (await save(notes.value.trim() || null)) {
    confirming.value = false
    emit('saved')
  } else if (!uncertain.value) {
    acknowledged.value = false
  }
}

function refreshReview() {
  confirming.value = false
  void refresh()
}

function inspect(kind: 'sessions' | 'handovers') {
  if (actionsDisabled.value || confirming.value) return
  sourceAfterClose.value = kind
  drawer.value?.close()
}
function afterClose() {
  if (sourceAfterClose.value === 'sessions') emit('sessions')
  else if (sourceAfterClose.value === 'handovers') emit('handovers')
  else emit('close')
}
</script>

<template>
  <FinanceDrawer
    ref="drawer"
    :persistent="actionsDisabled || confirming || !!selectedClosing"
    :title="t('dailyfinance::day_closings.title')"
    compact
    @close="afterClose"
  >
    <template #context>
      <div class="closing__context">
        <span>
          <VIcon
            icon="tabler-calendar"
            size="15"
          />
          {{ review?.date_formatted ?? date }}
        </span>
        <span>
          <VIcon
            icon="tabler-building-store"
            size="15"
          />
          {{ branchName }}
        </span>
      </div>
    </template>
    <template #header-end>
      <VTabs
        v-model="tab"
        :disabled="actionsDisabled || confirming"
        :show-arrows="false"
        class="closing-tabs"
        color="secondary"
        density="compact"
        hide-slider
      >
        <VTab
          :disabled="actionsDisabled || confirming"
          prepend-icon="tabler-checklist"
          value="review"
        >
          {{ t('dailyfinance::day_closings.review') }}
        </VTab>
        <VTab
          :disabled="actionsDisabled || confirming"
          prepend-icon="tabler-history"
          value="history"
        >
          {{ t('dailyfinance::day_closings.history') }}
        </VTab>
      </VTabs>
    </template>
    <section class="closing">
      <VAlert
        v-if="error && !confirming"
        role="alert"
        type="warning"
        variant="tonal"
      >
        {{ error }}
        <VBtn
          :disabled="actionsDisabled"
          variant="text"
          @click="tab === 'review' && review ? refresh() : load(tab)"
        >
          {{ t('dailyfinance::day_closings.retry') }}
        </VBtn>
      </VAlert>
      <div
        v-if="loading && !refreshing"
        :aria-label="t('dailyfinance::daily_finance.loading')"
        :class="{ 'closing__loading--history': tab === 'history' }"
        class="closing__loading"
        role="status"
      >
        <template v-if="tab === 'review'">
          <div
            v-if="savedReview"
            class="closing__loading-label"
          >
            <span class="closing__bone closing__bone--status" />
            <span class="closing__bone" />
          </div>
          <section
            v-else
            class="closing__loading-section"
          >
            <span class="closing__bone closing__bone--heading" />
            <div class="closing__loading-readiness">
              <div
                v-for="n in 3"
                :key="n"
                class="closing__loading-row"
              >
                <div class="closing__loading-label">
                  <span class="closing__bone closing__bone--icon" />
                  <span class="closing__bone" />
                </div>
                <span class="closing__bone" />
              </div>
            </div>
            <span class="closing__bone closing__bone--notice" />
          </section>
          <section class="closing__loading-section">
            <span class="closing__bone closing__bone--heading" />
            <div class="closing__loading-sales">
              <div
                v-for="n in 2"
                :key="n"
              >
                <span class="closing__bone closing__bone--icon" />
                <div>
                  <span class="closing__bone" />
                  <span class="closing__bone closing__bone--amount" />
                </div>
              </div>
            </div>
            <div class="closing__loading-totals">
              <div
                v-for="n in 3"
                :key="n"
              >
                <span class="closing__bone" />
                <span class="closing__bone closing__bone--value" />
              </div>
            </div>
          </section>
          <section class="closing__loading-section">
            <span class="closing__bone closing__bone--heading" />
            <div class="closing__loading-hero">
              <div
                v-for="n in 3"
                :key="n"
              >
                <span class="closing__bone" />
                <span class="closing__bone closing__bone--value" />
              </div>
            </div>
            <div class="closing__loading-cash">
              <div
                v-for="n in 2"
                :key="n"
              >
                <span class="closing__bone closing__bone--icon" />
                <span class="closing__bone" />
                <span class="closing__bone closing__bone--value" />
              </div>
            </div>
          </section>
          <section class="closing__loading-section">
            <span class="closing__bone closing__bone--heading" />
            <div class="closing__loading-payments">
              <div class="closing__loading-payment-heading">
                <span
                  v-for="n in 4"
                  :key="n"
                  class="closing__bone"
                />
              </div>
              <div
                v-for="n in 6"
                :key="`method-${n}`"
                class="closing__loading-payment"
              >
                <div class="closing__loading-label">
                  <span class="closing__bone closing__bone--icon" />
                  <span class="closing__bone" />
                </div>
                <span
                  v-for="amount in 3"
                  :key="amount"
                  class="closing__bone"
                />
              </div>
            </div>
          </section>
          <footer class="closing__footer">
            <p>
              {{ t(`dailyfinance::day_closings.${savedReview ? 'saved_hint' : 'fixed_hint'}`) }}
            </p>
            <div>
              <VBtn
                color="secondary"
                disabled
                prepend-icon="tabler-refresh"
                variant="tonal"
              >
                {{ t('dailyfinance::day_closings.refresh') }}
              </VBtn>
              <VBtn
                v-if="!savedReview"
                color="primary"
                disabled
                prepend-icon="tabler-lock"
              >
                {{ t('dailyfinance::day_closings.close') }}
              </VBtn>
            </div>
          </footer>
        </template>
        <template v-if="tab === 'history'">
          <div class="closing__loading-history-heading">
            <span class="closing__bone closing__bone--heading" />
            <span class="closing__bone" />
          </div>
          <div
            v-for="n in 4"
            :key="`history-${n}`"
            class="closing__loading-history"
          >
            <div class="closing__loading-history-title">
              <div class="closing__loading-label">
                <span class="closing__bone closing__bone--icon" />
                <div class="closing__loading-history-date">
                  <span class="closing__bone" />
                  <span class="closing__bone" />
                </div>
              </div>
              <div class="closing__loading-history-totals">
                <div
                  v-for="total in 3"
                  :key="total"
                >
                  <span class="closing__bone" />
                  <span class="closing__bone closing__bone--value" />
                </div>
              </div>
              <span class="closing__bone closing__bone--status" />
            </div>
            <div class="closing__loading-history-audit">
              <span class="closing__bone" />
              <span class="closing__bone" />
            </div>
          </div>
        </template>
      </div>
      <template v-else-if="tab === 'review' && review">
        <div
          v-if="review.closing"
          class="closing__audit"
        >
          <TableStatus :item="review.closing" />
          <strong>{{ review.closing.reference }}</strong>
          <span>
            {{ t('dailyfinance::day_closings.saved_by') }} {{ review.closing.created_by.name }} ·
            <bdi dir="ltr">{{ review.closing.created_at }}</bdi>
          </span>
        </div>
        <section
          v-if="!review.closing && checks"
          class="closing__readiness"
        >
          <h3>{{ t('dailyfinance::day_closings.before_close') }}</h3>
          <div class="closing__checks">
            <button
              :disabled="actionsDisabled"
              class="closing__check"
              type="button"
              @click="inspect('sessions')"
            >
              <VIcon
                :color="checks.open_sessions ? 'primary' : 'success'"
                :icon="checks.open_sessions ? 'tabler-clock' : 'tabler-circle-check'"
                size="22"
              />
              <span>{{ t('dailyfinance::day_closings.sessions_check') }}</span>
              <strong>
                {{
                  t(
                    `dailyfinance::day_closings.${checks.open_sessions ? 'sessions_open' : 'sessions_closed'}`,
                    { count: checks.open_sessions || checks.closed_sessions },
                  )
                }}
              </strong>
              <VIcon
                icon="tabler-chevron-right"
                size="18"
              />
            </button>
            <button
              :disabled="actionsDisabled"
              class="closing__check"
              type="button"
              @click="inspect('handovers')"
            >
              <VIcon
                :color="checks.pending_handovers ? 'primary' : 'success'"
                :icon="checks.pending_handovers ? 'tabler-clock' : 'tabler-circle-check'"
                size="22"
              />
              <span>{{ t('dailyfinance::day_closings.handover_check') }}</span>
              <strong>
                {{
                  checks.pending_handovers
                    ? t('dailyfinance::day_closings.pending_receipts', {
                        count: checks.pending_handovers,
                      })
                    : t('dailyfinance::day_closings.all_received')
                }}
              </strong>
              <VIcon
                icon="tabler-chevron-right"
                size="18"
              />
            </button>
            <div class="closing__check">
              <VIcon
                :color="checks.unknown_counts || checks.session_variances ? 'primary' : 'success'"
                :icon="
                  checks.unknown_counts || checks.session_variances
                    ? 'tabler-alert-circle'
                    : 'tabler-circle-check'
                "
                size="22"
              />
              <span>{{ t('dailyfinance::day_closings.cash_check') }}</span>
              <strong>
                {{
                  checks.unknown_counts
                    ? t('dailyfinance::day_closings.counts_missing', {
                        count: checks.unknown_counts,
                      })
                    : (review.summary.sessions?.variance?.formatted ??
                      t('dailyfinance::day_closings.no_sessions'))
                }}
              </strong>
            </div>
          </div>
          <aside
            v-if="review.blockers.length || review.warnings.length"
            class="closing__notice"
          >
            <VIcon
              aria-hidden="true"
              color="primary"
              icon="tabler-alert-circle"
              size="22"
            />
            <div>
              <div class="closing__notice-heading">
                <strong>
                  {{
                    t(
                      `dailyfinance::day_closings.${review.blockers.length ? 'blocked_title' : 'review_required'}`,
                    )
                  }}
                </strong>
                <span v-if="review.warnings.length && !review.blockers.length">
                  {{ t('dailyfinance::day_closings.explanation_hint') }}
                </span>
              </div>
              <ul>
                <li
                  v-for="key in [...review.blockers, ...review.warnings]"
                  :key="key"
                >
                  {{ t(`dailyfinance::day_closings.checks.${key}`) }}
                </li>
              </ul>
            </div>
          </aside>
          <p
            v-if="!can('admin.daily_finance_closings.close')"
            class="closing__hint"
          >
            {{ t('dailyfinance::day_closings.read_only') }}
          </p>
        </section>
        <FinanceClosingSummary :summary="review.summary" />
        <p
          v-if="review.closing?.notes"
          class="closing__note"
        >
          <strong>{{ t('dailyfinance::day_closings.notes') }}:</strong>
          {{ review.closing.notes }}
        </p>
        <footer class="closing__footer">
          <p>
            {{ t(`dailyfinance::day_closings.${review.closing ? 'saved_hint' : 'fixed_hint'}`) }}
          </p>
          <div>
            <VBtn
              :disabled="actionsDisabled"
              :loading="refreshing"
              color="secondary"
              prepend-icon="tabler-refresh"
              variant="tonal"
              @click="refresh()"
            >
              {{ t('dailyfinance::day_closings.refresh') }}
            </VBtn>
            <VBtn
              v-if="!review.closing"
              :disabled="!review.can_close || actionsDisabled"
              color="primary"
              prepend-icon="tabler-lock"
              @click="confirming = true"
            >
              {{ t('dailyfinance::day_closings.close') }}
            </VBtn>
          </div>
        </footer>
      </template>
      <template v-else-if="tab === 'history' && history">
        <div
          v-if="!history.data.length"
          class="closing__empty"
        >
          <VIcon
            color="secondary"
            icon="tabler-calendar-check"
            size="36"
          />
          <h3>{{ t('dailyfinance::day_closings.no_history') }}</h3>
          <p>{{ t('dailyfinance::day_closings.no_history_hint') }}</p>
        </div>
        <FinanceClosingHistory
          v-else
          :history="history"
          @review="selectedClosing = $event"
          @page="load('history', $event)"
        />
      </template>
    </section>
    <FinanceClosingReviewDialog
      v-if="selectedClosing"
      :record="selectedClosing"
      :branch-name="branchName"
      @close="selectedClosing = null"
    />
    <VDialog
      v-model="confirming"
      :aria-labelledby="confirmationTitleId"
      :persistent="busy"
      max-width="520"
      scrollable
    >
      <VCard class="closing-dialog">
        <header>
          <div>
            <h2 :id="confirmationTitleId">{{ t('dailyfinance::day_closings.confirm_title') }}</h2>
            <p>{{ review?.date_formatted ?? date }} · {{ branchName }}</p>
          </div>
          <VBtn
            :aria-label="t('dailyfinance::daily_finance.actions.dismiss')"
            :disabled="busy"
            icon="tabler-x"
            variant="text"
            @click="confirming = false"
          />
        </header>
        <VCardText>
          <VForm @submit.prevent="confirm">
            <p class="closing__hint">{{ t('dailyfinance::day_closings.confirm_hint') }}</p>
            <VAlert
              v-if="error"
              role="alert"
              type="warning"
              variant="tonal"
            >
              {{ error }}
            </VAlert>
            <VAlert
              v-if="uncertain"
              type="info"
              variant="tonal"
            >
              {{ t('dailyfinance::day_closings.uncertain') }}
            </VAlert>
            <VTextarea
              v-model="notes"
              :disabled="busy"
              :error-messages="errors.notes"
              :label="
                t(
                  `dailyfinance::day_closings.${review?.warnings.length ? 'notes_required' : 'notes'}`,
                )
              "
              :placeholder="t('dailyfinance::day_closings.notes_placeholder')"
              auto-grow
              hide-details="auto"
              max-rows="5"
              maxlength="1000"
              rows="3"
            />
            <VCheckbox
              v-model="acknowledged"
              :disabled="busy"
              :label="t('dailyfinance::day_closings.acknowledge')"
              hide-details
            />
            <div class="closing-dialog__actions">
              <VBtn
                :disabled="busy"
                variant="text"
                @click="confirming = false"
              >
                {{ t('dailyfinance::day_closings.cancel') }}
              </VBtn>
              <VBtn
                v-if="!uncertain && review && !review.can_close"
                variant="tonal"
                @click="refreshReview"
              >
                {{ t('dailyfinance::day_closings.refresh') }}
              </VBtn>
              <VBtn
                v-else
                :disabled="uncertain ? saving : !canConfirm"
                :loading="saving"
                color="primary"
                prepend-icon="tabler-lock"
                type="submit"
              >
                {{ t(`dailyfinance::day_closings.${uncertain ? 'retry' : 'confirm'}`) }}
              </VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>
    </VDialog>
  </FinanceDrawer>
</template>

<style lang="scss" scoped>
.closing {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  min-inline-size: 0;
  gap: 20px;
  font-size: 0.875rem;
}
.closing__context {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 14px;
  margin-block-start: 5px;
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 0.75rem;
  line-height: 1.5;
}
.closing__context > span {
  display: flex;
  align-items: center;
  gap: 8px;
}

.closing-tabs {
  block-size: 42px;
  padding: 4px;
  border-radius: 10px;
  background: rgba(var(--v-theme-secondary), 0.055);
}

.closing-tabs :deep(.v-slide-group__content) {
  gap: 4px;
}

.closing-tabs :deep(.v-tab) {
  min-inline-size: 0;
  block-size: 34px;
  padding-inline: 14px;
  border-radius: 7px !important;
  color: rgba(var(--v-theme-on-surface), 0.7);
  font-size: 0.8125rem;
  font-weight: 500;
  letter-spacing: 0;
}

.closing-tabs :deep(.v-tab--selected) {
  background: rgb(var(--v-theme-surface));
  color: rgb(var(--v-theme-secondary));
  box-shadow: 0 1px 4px rgba(var(--v-theme-secondary), 0.1);
}

.closing-tabs :deep(.v-icon) {
  font-size: 18px;
}

.closing h3 {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 650;
}
.closing__readiness {
  display: grid;
  gap: 12px;
}
.closing__checks {
  border: 1px solid rgba(var(--v-theme-secondary), 0.12);
  border-radius: 10px;
  overflow: hidden;
}
.closing__check {
  display: flex;
  align-items: center;
  gap: 12px;
  inline-size: 100%;
  padding: 12px 16px;
  background: rgba(var(--v-theme-secondary), 0.035);
  color: rgb(var(--v-theme-on-surface));
  font-size: 1rem;
  text-align: start;
}
button.closing__check:hover:not(:disabled) {
  background: rgba(var(--v-theme-primary), 0.04);
}
button.closing__check:disabled {
  opacity: 0.55;
  cursor: default;
}
.closing__check + .closing__check {
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}
.closing__check > .v-icon {
  flex-shrink: 0;
}
.closing__check > .v-icon:last-child:dir(rtl) {
  transform: rotate(180deg);
}
.closing__check strong {
  margin-inline-start: auto;
  font-size: 0.875rem;
  font-weight: 600;
  text-align: end;
}
.closing :deep(.v-alert__content) {
  color: rgb(var(--v-theme-on-surface));
}
.closing__notice {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 8px;
  background: rgba(var(--v-theme-primary), 0.1);
  font-size: 0.875rem;
  line-height: 1.5;
}
.closing__notice > .v-icon {
  flex-shrink: 0;
  margin-block-start: 1px;
}
.closing__notice strong {
  color: rgb(var(--v-theme-secondary));
}
.closing__notice-heading {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 4px 12px;
}
.closing__notice-heading > span {
  font-size: 0.875rem;
}
.closing ul {
  margin-block-start: 8px;
  padding-inline-start: 18px;
  font-size: 0.875rem;
}
.closing__footer {
  position: sticky;
  inset-block-end: -10px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-block-start: 20px;
  padding-block: 16px;
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  background: rgb(var(--v-theme-surface));
}
.closing__footer > p {
  margin: 0;
  max-inline-size: 340px;
  font-size: 0.75rem;
  line-height: 1.5;
  color: rgba(var(--v-theme-on-surface), 0.6);
}
.closing__footer > div {
  display: flex;
  gap: 10px;
  flex-shrink: 0;
}
.closing__hint,
.closing__note {
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.6;
  color: rgba(var(--v-theme-on-surface), 0.65);
}
.closing__note {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.closing__audit {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  font-size: 0.75rem;
}
.closing__audit > span {
  color: rgba(var(--v-theme-on-surface), 0.65);
}
.closing__loading-history {
  padding: 14px 16px;
  overflow: hidden;
  border: 1px solid rgba(var(--v-theme-secondary), 0.14);
  border-radius: 10px;
}

.closing__loading,
.closing__loading-section {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 12px;
}
.closing__loading {
  gap: 20px;
}
.closing__loading--history {
  gap: 10px;
}
.closing__loading-history-heading,
.closing__loading-history-title,
.closing__loading-history-audit {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.closing__loading-history-title {
  display: grid;
  grid-template-columns: minmax(150px, 1.1fr) minmax(0, 2fr) auto;
}
.closing__loading-history-totals {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}
.closing__loading-history-date,
.closing__loading-history-totals > div {
  display: grid;
  gap: 6px;
}
.closing__loading-history-date .closing__bone:last-child {
  inline-size: 72px;
  block-size: 12px;
}
.closing__loading-history-audit {
  margin-block-start: 10px;
  padding-block-start: 10px;
  border-block-start: 1px solid rgba(var(--v-theme-secondary), 0.08);
}
.closing__empty {
  display: grid;
  justify-items: center;
  gap: 12px;
  padding: 48px 16px;
  text-align: center;
}
.closing__empty p {
  margin: 0;
  font-size: 0.8125rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}
.closing__loading-readiness {
  padding: 0 16px;
  border-radius: 10px;
  border: 1px solid rgba(var(--v-theme-secondary), 0.12);
  background: rgba(var(--v-theme-secondary), 0.035);
}
.closing__loading-sales {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  padding: 20px 24px;
  border-block: 1px solid rgba(var(--v-theme-secondary), 0.12);
}
.closing__loading-sales > div {
  display: flex;
  align-items: center;
  gap: 14px;
}
.closing__loading-sales > div + div {
  padding-inline-start: 24px;
  border-inline-start: 1px solid rgba(var(--v-theme-secondary), 0.12);
}
.closing__loading-sales > div > div,
.closing__loading-totals > div {
  display: grid;
  gap: 8px;
}
.closing__loading-totals {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}
.closing__loading-totals > div {
  padding: 14px 16px;
  border-radius: 8px;
  background: rgba(var(--v-theme-secondary), 0.045);
}
.closing__bone--notice {
  inline-size: 100% !important;
  block-size: 64px !important;
  background: rgba(var(--v-theme-primary), 0.1) !important;
}
.closing__bone--value {
  block-size: 22px !important;
}
.closing__loading-hero {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  padding: 20px;
  border-radius: 10px;
  background: rgb(var(--v-theme-secondary));
}
.closing__loading-hero > div {
  display: grid;
  justify-items: center;
  gap: 8px;
  padding-inline: 12px;
}
.closing__loading-hero > div + div {
  border-inline-start: 1px solid rgba(var(--v-theme-on-secondary), 0.22);
}
.closing__loading-hero .closing__bone {
  background: rgb(var(--v-theme-on-secondary), 0.2);
}
.closing__loading-cash {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  padding: 14px 20px;
  border-radius: 8px;
  background: rgba(var(--v-theme-secondary), 0.045);
}
.closing__loading-cash > div {
  display: flex;
  align-items: center;
  gap: 12px;
}
.closing__loading-cash > div:first-child {
  padding-inline-end: 20px;
}
.closing__loading-cash > div + div {
  padding-inline-start: 20px;
  border-inline-start: 1px solid rgba(var(--v-theme-secondary), 0.12);
}
.closing__bone--heading {
  inline-size: 160px !important;
  block-size: 24px !important;
}
.closing__loading-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding-block: 12px;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}
.closing__loading-row:last-child {
  border-block-end: 0;
}
.closing__loading-label {
  display: flex;
  align-items: center;
  gap: 10px;
  min-inline-size: 0;
}
.closing__loading-payments {
  overflow: hidden;
  border: 1px solid rgba(var(--v-theme-secondary), 0.12);
  border-radius: 8px;
}
.closing__loading-payment-heading,
.closing__loading-payment {
  display: grid;
  grid-template-columns: minmax(0, 1.3fr) repeat(3, minmax(0, 1fr));
  align-items: center;
  gap: 16px;
  padding: 12px 16px;
}
.closing__loading-payment-heading {
  background: rgba(var(--v-theme-secondary), 0.045);
}
.closing__loading-payment + .closing__loading-payment {
  border-block-start: 1px solid rgba(var(--v-theme-secondary), 0.1);
}
.closing__loading-payment-heading > .closing__bone:not(:first-child),
.closing__loading-payment > .closing__bone {
  justify-self: end;
  inline-size: 72px;
}
.closing__bone {
  display: block;
  inline-size: 120px;
  max-inline-size: 100%;
  block-size: 14px;
  border-radius: 4px;
  background: rgba(var(--v-theme-on-surface), 0.08);
  animation: closing-pulse 1.6s ease-in-out infinite alternate;
}
.closing__bone--amount {
  block-size: 32px;
}
.closing__bone--icon {
  inline-size: 24px;
  block-size: 24px;
  flex-shrink: 0;
}
.closing__bone--status {
  inline-size: 64px;
  block-size: 28px;
  border-radius: 20px;
}
.closing-dialog {
  border-radius: 14px !important;
}
.closing-dialog > header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 20px;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}
.closing-dialog h2 {
  font-size: 1rem;
}
.closing-dialog header p {
  margin: 4px 0 0;
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}
.closing-dialog :deep(.v-card-text) {
  padding: 20px !important;
}
.closing-dialog :deep(.v-form) {
  display: grid;
  gap: 18px;
}
.closing-dialog__actions {
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 8px;
  margin-block-start: 8px;
  padding-block-start: 16px;
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}
@keyframes closing-pulse {
  to {
    opacity: 0.45;
  }
}
@media (prefers-reduced-motion: reduce) {
  .closing__bone {
    animation: none;
  }
}
@media (max-width: 600px) {
  .closing__loading-sales {
    padding: 18px 0;
  }
  .closing__loading-sales > div > .closing__bone--icon {
    display: none;
  }
  .closing__loading-sales > div + div {
    padding-inline-start: 16px;
  }
  .closing__loading-totals,
  .closing__loading-hero {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
  }
  .closing__loading-totals {
    gap: 0;
    padding: 4px 14px;
    border-radius: 8px;
    background: rgba(var(--v-theme-secondary), 0.045);
  }
  .closing__loading-totals > div {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    background: none;
  }
  .closing__loading-totals > div + div {
    border-block-start: 1px solid rgba(var(--v-theme-secondary), 0.1);
  }
  .closing__loading-hero {
    padding: 6px 18px;
  }
  .closing__loading-hero > div {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 0;
  }
  .closing__loading-hero > div + div {
    border-inline-start: 0;
    border-block-start: 1px solid rgba(var(--v-theme-on-secondary), 0.22);
  }
  .closing__loading-cash {
    padding: 14px;
  }
  .closing__loading-cash > div {
    flex-wrap: wrap;
    gap: 8px;
  }
  .closing__loading-cash > div > .closing__bone--value {
    inline-size: 100%;
  }
  .closing__loading-payment-heading {
    display: none;
  }
  .closing__loading-payment {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    padding: 14px;
  }
  .closing__loading-payment > .closing__loading-label {
    grid-column: 1 / -1;
  }
  .closing__loading-payment > .closing__bone {
    justify-self: start;
    block-size: 30px;
  }
  .closing__loading-history-title {
    grid-template-columns: minmax(0, 1fr) auto;
  }
  .closing__loading-history-totals {
    grid-row: 2;
    grid-column: 1 / -1;
  }
  .closing__loading-history-audit {
    flex-wrap: wrap;
  }
  .closing__footer {
    align-items: stretch;
    flex-direction: column;
  }
  .closing__footer > p {
    max-inline-size: none;
  }
  .closing__footer > div > .v-btn {
    flex: 1;
    min-inline-size: 0;
    font-size: 0.75rem;
    padding-inline: 12px;
  }
  .closing__check strong {
    max-inline-size: 45%;
    overflow-wrap: anywhere;
  }
  .closing__check {
    gap: 8px;
    padding: 14px 12px;
    font-size: 1rem;
  }
}
</style>
