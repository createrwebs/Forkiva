<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import type { PosSessionDetail } from '@/modules/pos/contracts/sessionReconciliation'

defineProps<{ item: PosSessionDetail }>()
const { t } = useI18n()
</script>

<template>
  <VCard class="closing-audit">
    <h3>{{ t('pos::pos_sessions.show.closing_audit') }}</h3>
    <template v-if="item.closing_custody">
      <dl class="closing-audit-people">
        <div
          v-for="key in ['responsible_cashier', 'counted_by', 'approved_by'] as const"
          :key="key"
        >
          <dt>{{ t(`pos::pos_sessions.show.${key}`) }}</dt>
          <dd>
            <bdi>
              {{ item.closing_custody[key]?.name || t('pos::pos_sessions.show.not_available') }}
            </bdi>
          </dd>
        </div>
        <div>
          <dt>{{ t('pos::pos_sessions.show.approved_at') }}</dt>
          <dd>
            <bdi dir="ltr">{{ item.closing_custody.approved_at }}</bdi>
          </dd>
        </div>
      </dl>
      <dl class="closing-audit-reasons">
        <div
          v-for="key in ['closing_reason', 'variance_reason'] as const"
          :key="key"
          v-show="item.closing_custody[key]"
        >
          <dt>{{ t(`pos::pos_sessions.show.${key}`) }}</dt>
          <dd>{{ item.closing_custody[key] }}</dd>
        </div>
      </dl>
    </template>
    <p v-else>{{ t('pos::pos_sessions.show.legacy_closing_audit') }}</p>
  </VCard>
</template>

<style lang="scss" scoped>
.closing-audit {
  padding: 20px;
  border: 1px solid var(--session-line);
  border-radius: 12px;
  box-shadow: var(--session-shadow);
}

.closing-audit h3 {
  margin-block-end: 16px;
  font-size: 1rem;
}

.closing-audit-people {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}

.closing-audit dt {
  margin-block-end: 4px;
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 0.75rem;
}

.closing-audit dd,
.closing-audit p {
  margin: 0;
  font-size: 0.8125rem;
  overflow-wrap: anywhere;
}

.closing-audit-reasons > div {
  padding-block-start: 16px;
}

.closing-audit-reasons dd {
  white-space: pre-wrap;
}

@media (max-width: 700px) {
  .closing-audit-people {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
