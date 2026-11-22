<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { PosSessionDetail } from '@/modules/pos/contracts/sessionReconciliation'

const props = defineProps<{ item: PosSessionDetail }>()
const { t } = useI18n()
const fields = computed(() => [
  {
    key: 'responsible_cashier',
    value: (props.item.responsible_cashier ?? props.item.opened_by).name,
    icon: 'tabler-user',
  },
  { key: 'opened_at', value: props.item.opened_at, icon: 'tabler-calendar' },
  { key: 'closed_by', value: props.item.closed_by.name, icon: 'tabler-user' },
  { key: 'closed_at', value: props.item.closed_at, icon: 'tabler-calendar' },
  { key: 'orders_count', value: props.item.orders_count, icon: 'tabler-file-text' },
  ...(props.item.notes ? [{ key: 'notes', value: props.item.notes, icon: 'tabler-message' }] : []),
])
</script>

<template>
  <VCard
    class="session-information"
    :aria-label="t('pos::pos_sessions.show.cards.session_information.title')"
  >
    <dl class="session-information-fields">
      <div
        v-for="field in fields"
        :key="field.key"
        :class="['session-info-field', { 'session-info-field--notes': field.key === 'notes' }]"
      >
        <span
          class="session-field-icon"
          aria-hidden="true"
        >
          <VIcon
            :icon="field.icon"
            size="23"
          />
        </span>
        <div class="session-field-content">
          <dt class="session-field-label">{{ t(`pos::pos_sessions.show.${field.key}`) }}</dt>
          <dd>
            <bdi :dir="field.key === 'opened_at' || field.key === 'closed_at' ? 'ltr' : 'auto'">
              {{
                field.value === null || field.value === ''
                  ? t('pos::pos_sessions.show.not_available')
                  : field.value
              }}
            </bdi>
          </dd>
        </div>
      </div>
    </dl>
  </VCard>
</template>

<style lang="scss" scoped>
.session-information {
  padding: 20px;
  border: 1px solid var(--session-line);
  border-radius: 12px;
  box-shadow: var(--session-shadow);
}

.session-information-fields {
  display: grid;
  grid-template-columns:
    minmax(0, 0.95fr) minmax(0, 1.25fr) minmax(0, 0.95fr) minmax(0, 1.25fr)
    minmax(0, 0.8fr) minmax(0, 1.15fr);
  gap: 0;
}

.session-info-field {
  display: flex;
  align-items: center;
  gap: 12px;
  min-inline-size: 0;
  padding-inline: 16px;
}

.session-info-field:first-child {
  padding-inline-start: 0;
}

.session-info-field:last-child {
  padding-inline-end: 0;
}

.session-info-field + .session-info-field {
  border-inline-start: 1px solid var(--session-line);
}

.session-field-icon {
  display: grid;
  place-items: center;
  inline-size: 42px;
  block-size: 42px;
  flex-shrink: 0;
  border-radius: 10px;
  color: rgba(var(--v-theme-on-surface), 0.72);
  background: rgba(var(--v-theme-on-surface), 0.035);
}

.session-field-content {
  min-inline-size: 0;
}

.session-field-label {
  margin-block-end: 4px;
  font-size: 0.75rem;
  line-height: 1.4;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.session-info-field dd {
  margin: 0;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.8125rem;
  line-height: 1.55;
  overflow-wrap: anywhere;
}

.session-info-field--notes dd {
  white-space: pre-wrap;
}

@media (max-width: 1450px) {
  .session-info-field {
    gap: 10px;
    padding-inline: 12px;
  }

  .session-field-icon {
    inline-size: 34px;
    block-size: 38px;
  }
}

@media (max-width: 1200px) {
  .session-information-fields {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px 16px;
  }

  .session-info-field,
  .session-info-field + .session-info-field {
    padding-inline: 0;
    border: 0;
  }
}

@media (max-width: 600px) {
  .session-information {
    padding: 18px;
  }

  .session-information-fields {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px 12px;
  }

  .session-info-field {
    align-items: flex-start;
    gap: 8px;
  }

  .session-field-icon {
    inline-size: 30px;
    block-size: 32px;
  }

  .session-field-icon :deep(.v-icon) {
    font-size: 20px !important;
  }

  .session-info-field--notes {
    grid-column: 1 / -1;
  }
}
</style>
