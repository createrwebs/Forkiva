<script lang="ts" setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DailyFinanceSession } from '@/modules/dailyfinance/contracts/dailyFinance'
import FinanceDrawer from './FinanceDrawer.vue'
import FinanceHandovers from './FinanceHandovers.vue'

const props = defineProps<{ sessions: DailyFinanceSession[]; initialId?: number | null }>()
const emit = defineEmits<{ close: []; saved: [] }>()
const { t } = useI18n()
const closedSessions = computed(() =>
  props.sessions.filter((session) => session.status === 'closed'),
)
const sessionId = ref(
  closedSessions.value.find((session) => session.id === props.initialId)?.id ??
    closedSessions.value[0]?.id ??
    null,
)
const session = computed(() => closedSessions.value.find((row) => row.id === sessionId.value))
const editing = ref(false)
const busy = ref(false)
const choices = computed(() =>
  closedSessions.value.map((row) => ({
    id: row.id,
    name: `${row.register ?? '—'} · #${row.id}`,
  })),
)

function changeSession(value: number | null) {
  if (!editing.value && !busy.value && value !== null) sessionId.value = value
}
</script>

<template>
  <FinanceDrawer
    :title="t('dailyfinance::cash_handovers.title')"
    :context="t('dailyfinance::cash_handovers.subtitle')"
    :persistent="busy || editing"
    compact
    narrow
    @close="emit('close')"
  >
    <template
      v-if="closedSessions.length"
      #header-end
    >
      <VSelect
        :model-value="sessionId"
        :items="choices"
        item-title="name"
        item-value="id"
        :label="t('dailyfinance::cash_handovers.session')"
        :disabled="editing || busy"
        prepend-inner-icon="tabler-building-store"
        variant="outlined"
        hide-details
        class="finance-handover-workspace__session-select"
        @update:model-value="changeSession"
      />
    </template>
    <div class="finance-handover-workspace">
      <template v-if="session">
        <section class="finance-handover-workspace__source">
          <p>
            <VIcon
              icon="tabler-user"
              size="16"
              aria-hidden="true"
            />
            <span>{{ session.cashier ?? '—' }}</span>
            <span aria-hidden="true">·</span>
            <bdi dir="ltr">{{ session.opened_at_formatted ?? '—' }}</bdi>
          </p>
        </section>
        <FinanceHandovers
          :key="session.id"
          :session-id="session.id"
          :session-label="choices.find((choice) => choice.id === sessionId)?.name"
          @editing="editing = $event"
          @busy="busy = $event"
          @saved="emit('saved')"
        />
      </template>
      <div
        v-else
        class="finance-handover-workspace__empty"
      >
        <VAvatar
          color="secondary"
          variant="tonal"
          size="64"
        >
          <VIcon
            icon="tabler-cash-register"
            size="30"
          />
        </VAvatar>
        <h3>{{ t('dailyfinance::cash_handovers.no_sessions') }}</h3>
        <p>{{ t('dailyfinance::cash_handovers.no_sessions_hint') }}</p>
      </div>
    </div>
  </FinanceDrawer>
</template>

<style lang="scss" scoped>
.finance-handover-workspace {
  padding-block: 16px 24px;
}

.finance-handover-workspace__session-select {
  inline-size: 230px;
}

.finance-handover-workspace__source p {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin: 0;
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.finance-handover-workspace__empty {
  display: grid;
  justify-items: center;
  gap: 16px;
  padding: 56px 24px;
  text-align: center;
}

.finance-handover-workspace__empty h3 {
  font-size: 1rem;
}

.finance-handover-workspace__empty p {
  max-inline-size: 340px;
  margin: 0;
  line-height: 1.7;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

@media (max-width: 600px) {
  .finance-handover-workspace__session-select {
    inline-size: 100%;
  }
}
</style>
