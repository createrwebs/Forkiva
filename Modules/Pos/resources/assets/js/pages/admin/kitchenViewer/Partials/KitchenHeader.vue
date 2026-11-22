<script lang="ts" setup>
import { useMediaQuery } from '@vueuse/core'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth'
import NavbarAction from '@/app/layouts/components/NavbarAction.vue'

defineProps<{
  filters: { branchId: number | null; searchQuery: string | null; type: string | null }
  meta: { branches: { id: number; name: string }[]; orderTypes: { id: string; name: string }[] }
  disabled: boolean
  refreshing: boolean
  reloadBranchData: boolean
}>()
defineEmits<{ refresh: [] }>()
const { t } = useI18n()
const { user } = useAuth()
const compactScreen = useMediaQuery('(max-width: 767px)')
</script>

<template>
  <Teleport
    to="#main-header-left-content"
    :disabled="compactScreen"
    defer
  >
    <div class="kds-header-inputs">
      <VTooltip :text="t('pos::kds.refresh')">
        <template #activator="{ props: tooltipProps }">
          <NavbarAction
            v-bind="tooltipProps"
            class="kds-header-refresh"
          >
            <VBtn
              color="default"
              icon="tabler-refresh"
              :aria-label="t('pos::kds.refresh')"
              :disabled="!filters.branchId || disabled || refreshing"
              :loading="refreshing"
              @click="$emit('refresh')"
            />
          </NavbarAction>
        </template>
      </VTooltip>
      <VTextField
        v-model="filters.searchQuery"
        class="kds-header-search"
        clearable
        density="compact"
        hide-details
        :aria-label="t('pos::kds.search')"
        :placeholder="t('pos::kds.search')"
        :disabled="disabled"
        prepend-inner-icon="tabler-search"
        variant="solo-filled"
      />
      <VSelect
        v-if="!user?.assigned_to_branch"
        v-model="filters.branchId"
        class="kds-header-branch"
        density="compact"
        hide-details
        :disabled="disabled"
        :loading="reloadBranchData"
        :items="meta.branches"
        item-title="name"
        item-value="id"
        :aria-label="t('pos::pos_viewer.select_branch')"
        :placeholder="t('pos::pos_viewer.select_branch')"
        prepend-inner-icon="tabler-git-branch"
        variant="solo-filled"
      />
      <VSelect
        v-model="filters.type"
        class="kds-header-type"
        clearable
        density="compact"
        hide-details
        :disabled="!filters.branchId || disabled"
        :items="meta.orderTypes"
        item-title="name"
        item-value="id"
        :aria-label="t('pos::pos_viewer.select_order_type')"
        :placeholder="t('pos::kds.all_types')"
        prepend-inner-icon="tabler-paper-bag"
        variant="solo-filled"
      />
    </div>
  </Teleport>
</template>

<style lang="scss" scoped>
.kds-header-inputs {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  width: 100%;
  max-width: 780px;
}
.kds-header-refresh {
  flex-shrink: 0;
  margin-inline-end: 0 !important;
}
.kds-header-search {
  flex: 1.3 1 220px;
  min-width: 140px;
}
.kds-header-branch,
.kds-header-type {
  flex: 1 1 185px;
  min-width: 140px;
}
@media (max-width: 767px) {
  .kds-header-inputs {
    display: grid;
    grid-template-columns: 40px minmax(0, 1fr) minmax(0, 1fr);
    max-width: none;
    padding: 8px 12px;
    border-block-end: 1px solid var(--kds-line);
  }
  .kds-header-search {
    grid-column: span 2;
  }
  .kds-header-branch {
    grid-column: span 2;
  }
  .kds-header-type {
    grid-column: 3;
  }
  .kds-header-type:nth-child(3) {
    grid-column: 1 / -1;
  }
  .kds-header-search,
  .kds-header-branch,
  .kds-header-type {
    min-width: 0;
  }
}
</style>
