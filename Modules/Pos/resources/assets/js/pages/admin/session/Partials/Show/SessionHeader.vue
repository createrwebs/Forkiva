<script lang="ts" setup>
import type { PosSessionDetail } from '@/modules/pos/contracts/sessionReconciliation'

defineProps<{ item: PosSessionDetail }>()
</script>

<template>
  <VCard class="session-header">
    <div class="session-identity">
      <span
        class="session-register-icon"
        aria-hidden="true"
      >
        <VIcon
          icon="tabler-building-store"
          size="32"
        />
      </span>
      <div class="session-identity-text">
        <div class="session-register-heading">
          <h2 class="session-register-name">{{ item.pos_register.name }}</h2>
          <VChip
            :color="item.status.id === 'closed' ? 'success' : 'primary'"
            class="session-status"
            size="small"
            variant="tonal"
          >
            <VIcon
              :icon="item.status.id === 'closed' ? 'tabler-circle-check' : 'tabler-clock'"
              size="18"
              start
            />
            {{ item.status.name }}
          </VChip>
        </div>
        <p class="session-branch-name">{{ item.branch.name }}</p>
      </div>
    </div>
    <div class="session-actions">
      <slot name="actions" />
    </div>
  </VCard>
</template>

<style lang="scss" scoped>
.session-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 20px;
  border: 1px solid var(--session-line);
  border-radius: 12px;
  box-shadow: var(--session-shadow);
}

.session-identity {
  display: flex;
  align-items: center;
  gap: 20px;
  min-inline-size: 0;
}

.session-register-icon {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  inline-size: 70px;
  block-size: 70px;
  border-radius: 12px;
  color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.1);
}

.session-identity-text {
  min-inline-size: 0;
  overflow-wrap: anywhere;
}

.session-register-heading {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.session-register-name {
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1.25;
  color: rgb(var(--v-theme-on-surface));
}

.session-status {
  font-size: 0.875rem;
  font-weight: 500;
}

.session-status.text-success {
  color: rgb(var(--session-positive)) !important;
  background: rgba(var(--session-positive), 0.08);
}

.session-branch-name {
  margin: 6px 0 0;
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 1rem;
}

.session-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  flex-shrink: 0;
  flex-wrap: wrap;
}

.session-actions :deep(.v-btn) {
  min-block-size: 46px;
  padding-inline: 20px;
  font-size: 0.9375rem;
  text-transform: none;
  letter-spacing: 0;
}

@media (max-width: 800px) {
  .session-header {
    align-items: flex-start;
    flex-wrap: wrap;
  }
}

@media (max-width: 600px) {
  .session-header {
    padding: 18px;
    gap: 16px;
  }

  .session-identity {
    gap: 12px;
    inline-size: 100%;
  }

  .session-register-icon {
    inline-size: 50px;
    block-size: 50px;
  }

  .session-register-name {
    font-size: 1.25rem;
  }

  .session-register-heading {
    gap: 8px;
  }

  .session-branch-name {
    font-size: 0.875rem;
  }

  .session-actions {
    justify-content: flex-start;
    inline-size: 100%;
    flex-shrink: 1;
  }
}
</style>
