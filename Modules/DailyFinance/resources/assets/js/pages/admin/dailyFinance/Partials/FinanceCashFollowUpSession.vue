<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import type {
  CashHandoverFollowUpKind,
  CashHandoverFollowUpSession,
} from '@/modules/dailyfinance/contracts/cashHandover'

withDefaults(
  defineProps<{
    item?: CashHandoverFollowUpSession
    kind: CashHandoverFollowUpKind
    loading?: boolean
  }>(),
  { loading: false },
)
const emit = defineEmits<{ open: [item: CashHandoverFollowUpSession] }>()
const { t } = useI18n()
</script>

<template>
  <component
    :is="loading ? 'div' : 'button'"
    :type="loading ? undefined : 'button'"
    class="cash-followup-session"
    :class="{ 'cash-followup-session--loading': loading }"
    :aria-hidden="loading ? true : undefined"
    @click="!loading && item && emit('open', item)"
  >
    <span
      class="cash-followup-session__icon"
      :class="{ 'cash-followup-session__bone': loading }"
    >
      <VIcon
        v-if="!loading"
        icon="tabler-building-store"
        size="22"
      />
    </span>
    <span class="cash-followup-session__identity">
      <span
        v-if="loading"
        class="cash-followup-session__bone cash-followup-session__loading-title"
      />
      <span
        v-else
        class="cash-followup-session__name"
      >
        <strong>{{ item?.register ?? '—' }}</strong>
        <small>#{{ item?.id }}</small>
      </span>
      <span class="cash-followup-session__cashier">
        <span
          v-if="loading"
          class="cash-followup-session__bone cash-followup-session__loading-cashier"
        />
        <template v-else>
          <VIcon
            icon="tabler-user"
            size="15"
          />
          <span>{{ item?.cashier ?? '—' }}</span>
        </template>
      </span>
    </span>
    <span class="cash-followup-session__amount">
      <template v-if="loading">
        <span class="cash-followup-session__bone cash-followup-session__loading-amount" />
        <span class="cash-followup-session__bone cash-followup-session__loading-currency" />
      </template>
      <template v-else>
        <strong>
          <bdi>{{ item?.[kind].formatted ?? '—' }}</bdi>
        </strong>
        <small>{{ item?.currency }}</small>
      </template>
    </span>
    <span
      class="cash-followup-session__open"
      :class="{ 'cash-followup-session__bone': loading }"
    >
      <VIcon
        v-if="!loading"
        icon="tabler-arrow-right"
        size="18"
        class="flip-in-rtl"
      />
    </span>
    <span class="cash-followup-session__closed">
      <span
        v-if="loading"
        class="cash-followup-session__bone cash-followup-session__loading-date"
      />
      <template v-else>
        <VIcon
          icon="tabler-clock"
          size="15"
        />
        <span>
          {{ t('dailyfinance::daily_finance.sessions.closed') }}
          <bdi>{{ item?.closed_at_formatted ?? '—' }}</bdi>
        </span>
      </template>
    </span>
  </component>
</template>

<style lang="scss" scoped>
.cash-followup-session {
  display: grid;
  grid-template-columns: 36px minmax(0, 1fr) max-content 30px;
  align-items: center;
  gap: 6px 14px;
  inline-size: 100%;
  min-block-size: 110px;
  padding: 18px 0;
  border-block-end: 1px solid rgba(var(--v-theme-secondary), 0.1);
  color: rgb(var(--v-theme-secondary));
  text-align: start;
  cursor: pointer;
}

.cash-followup-session:not(.cash-followup-session--loading):hover {
  background: rgba(var(--v-theme-primary), 0.035);
}

.cash-followup-session:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -2px;
  border-radius: 8px;
}

.cash-followup-session__icon {
  display: grid;
  place-items: center;
  grid-column: 1;
  grid-row: 1 / 3;
  inline-size: 36px;
  block-size: 36px;
  border-radius: 9px;
  background: rgba(var(--v-theme-secondary), 0.055);
}

.cash-followup-session__identity,
.cash-followup-session__amount {
  display: grid;
  gap: 5px;
  min-inline-size: 0;
}

.cash-followup-session__identity {
  grid-column: 2;
  grid-row: 1;
}

.cash-followup-session__name,
.cash-followup-session__cashier,
.cash-followup-session__closed {
  display: flex;
  align-items: center;
  gap: 6px;
  min-inline-size: 0;
}

.cash-followup-session__name {
  flex-wrap: wrap;
  gap: 4px 8px;
}

.cash-followup-session__name strong {
  font-size: 1rem;
  font-weight: 650;
  line-height: 1.4;
  overflow-wrap: anywhere;
}

.cash-followup-session__name small,
.cash-followup-session__cashier,
.cash-followup-session__amount small,
.cash-followup-session__closed {
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 0.8125rem;
  line-height: 1.5;
}

.cash-followup-session__cashier .v-icon,
.cash-followup-session__closed .v-icon {
  flex-shrink: 0;
}

.cash-followup-session__amount {
  grid-column: 3;
  grid-row: 1;
  text-align: end;
  font-variant-numeric: tabular-nums;
}

.cash-followup-session__amount strong {
  font-size: 1.25rem;
  line-height: 1.3;
  font-weight: 700;
  overflow-wrap: anywhere;
}

.cash-followup-session__open {
  display: grid;
  place-items: center;
  grid-column: 4;
  grid-row: 1 / 3;
  inline-size: 30px;
  block-size: 30px;
  border-radius: 50%;
  color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.1);
}

.cash-followup-session__closed {
  grid-column: 2 / 4;
  grid-row: 2;
  font-size: 0.75rem;
}

.cash-followup-session--loading {
  cursor: default;
}

.cash-followup-session__bone {
  position: relative;
  overflow: hidden;
  max-inline-size: 100%;
  block-size: 12px;
  border-radius: 4px;
  background: rgba(var(--v-theme-on-surface), 0.07);
}

.cash-followup-session__bone::after {
  position: absolute;
  inset: 0;
  content: '';
  background: linear-gradient(90deg, transparent, rgba(var(--v-theme-surface), 0.7), transparent);
  animation: cash-followup-shimmer 1.8s ease-in-out infinite;
}

.cash-followup-session__icon.cash-followup-session__bone {
  block-size: 36px;
  border-radius: 9px;
}

.cash-followup-session__open.cash-followup-session__bone {
  block-size: 30px;
  border-radius: 50%;
}

.cash-followup-session__loading-title {
  inline-size: 170px;
  block-size: 16px;
}

.cash-followup-session__loading-cashier {
  inline-size: 100px;
}

.cash-followup-session__loading-amount {
  inline-size: 110px;
  block-size: 24px;
}

.cash-followup-session__loading-currency {
  justify-self: end;
  inline-size: 28px;
}

.cash-followup-session__loading-date {
  inline-size: 210px;
}

@keyframes cash-followup-shimmer {
  from {
    transform: translateX(-100%);
  }
  to {
    transform: translateX(100%);
  }
}

@media (max-width: 600px) {
  .cash-followup-session {
    grid-template-columns: 32px minmax(0, 1fr) max-content 24px;
    gap: 6px 10px;
    padding-block: 16px;
  }

  .cash-followup-session__icon {
    inline-size: 32px;
    block-size: 32px;
  }

  .cash-followup-session__icon.cash-followup-session__bone {
    block-size: 32px;
  }

  .cash-followup-session__name strong {
    font-size: 0.9375rem;
  }
  .cash-followup-session__amount strong {
    font-size: 1.0625rem;
  }
  .cash-followup-session__open {
    inline-size: 24px;
    block-size: 24px;
  }
  .cash-followup-session__open.cash-followup-session__bone {
    block-size: 24px;
  }
  .cash-followup-session__loading-amount {
    inline-size: 90px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .cash-followup-session__bone::after {
    display: none;
    animation: none;
  }
}
</style>
