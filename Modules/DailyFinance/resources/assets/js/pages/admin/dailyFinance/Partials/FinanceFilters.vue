<script lang="ts" setup>
import { computed, ref, watch } from 'vue'
import { format, parseISO, subDays } from 'date-fns'
import { useI18n } from 'vue-i18n'
import DatePicker from '@/modules/core/components/Form/DatePicker.vue'

const props = defineProps<{
  branchId: number | null
  branches: { id: number; name: string }[]
  branchLocked: boolean
  date: string | null
  today: string
  loading: boolean
}>()
const emit = defineEmits<{
  branch: [value: number | null]
  date: [value: string | null]
}>()
const { t, locale } = useI18n()
const menu = ref(false)
const draftDate = ref<string | null>(null)
const yesterday = computed(() =>
  props.today ? format(subDays(parseISO(props.today), 1), 'yyyy-MM-dd') : '',
)
const preset = computed(() =>
  props.date === props.today ? 'today' : props.date === yesterday.value ? 'yesterday' : 'custom',
)
const periods = computed(() =>
  ['today', 'yesterday', 'custom'].map((id) => ({
    id,
    name: t(`dailyfinance::daily_finance.filters.${id}`),
  })),
)
const dateLabel = computed(() =>
  props.date
    ? new Intl.DateTimeFormat(locale.value, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
      }).format(parseISO(props.date))
    : '—',
)
watch(menu, (open) => {
  if (open) draftDate.value = props.date
})

function selectPeriod(value: string) {
  if (value === 'custom') menu.value = true
  else emit('date', value === 'today' ? null : yesterday.value)
}
function applyDate() {
  if (!draftDate.value) return
  emit('date', draftDate.value)
  menu.value = false
}
</script>

<template>
  <VCard
    class="finance-filters"
    elevation="0"
  >
    <VSelect
      :model-value="preset"
      :items="periods"
      item-title="name"
      item-value="id"
      :label="t('dailyfinance::daily_finance.filters.period')"
      :disabled="loading || !today"
      prepend-inner-icon="tabler-calendar-stats"
      variant="outlined"
      hide-details
      @update:model-value="selectPeriod"
    />
    <VSelect
      :model-value="branchId"
      :items="branches"
      item-title="name"
      item-value="id"
      :label="t('dailyfinance::daily_finance.filters.branch_id')"
      :disabled="loading || branchLocked || !branches.length"
      prepend-inner-icon="tabler-building-store"
      variant="outlined"
      hide-details
      @update:model-value="emit('branch', $event)"
    />
    <VMenu
      v-model="menu"
      :close-on-content-click="false"
      :offset="8"
      location="bottom start"
    >
      <template #activator="{ props: menuProps }">
        <VBtn
          v-bind="menuProps"
          class="finance-date"
          color="primary"
          variant="tonal"
          prepend-icon="tabler-calendar-event"
          :disabled="loading || !today"
          :aria-label="t('dailyfinance::daily_finance.filters.choose_date')"
        >
          {{ dateLabel }}
        </VBtn>
      </template>
      <VCard
        class="finance-date-menu"
        elevation="8"
      >
        <div class="finance-date-menu__heading">
          <VAvatar
            color="primary"
            variant="tonal"
            rounded="lg"
            size="38"
          >
            <VIcon
              icon="tabler-calendar-event"
              size="20"
            />
          </VAvatar>
          <strong>{{ t('dailyfinance::daily_finance.filters.custom') }}</strong>
          <VBtn
            icon="tabler-x"
            variant="text"
            size="small"
            :aria-label="t('dailyfinance::daily_finance.actions.dismiss')"
            @click="menu = false"
          />
        </div>
        <VDivider />
        <VCardText class="finance-date-menu__body">
          <DatePicker
            v-model="draftDate"
            :label="t('dailyfinance::daily_finance.filters.date')"
            density="comfortable"
            hide-details
          />
          <VBtn
            block
            color="primary"
            :disabled="!draftDate"
            @click="applyDate"
          >
            {{ t('dailyfinance::daily_finance.actions.apply') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VMenu>
  </VCard>
</template>

<style lang="scss" scoped>
.finance-filters {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  border: 1px solid rgba(var(--v-border-color), 0.1);
  border-radius: 16px;
  box-shadow: 0 7px 24px rgba(0, 0, 0, 0.045) !important;
}
.finance-filters :deep(.v-input) {
  max-inline-size: 220px;
}
.finance-filters :deep(.v-field) {
  border-radius: 11px;
}
.finance-date {
  block-size: 37px;
  border: 1px solid rgba(var(--v-theme-primary), 0.14);
  border-radius: 11px;
  font-size: 0.76rem;
  letter-spacing: 0;
}
.finance-date-menu {
  inline-size: min(360px, calc(100vw - 32px));
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 16px !important;
}
.finance-date-menu__heading {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 16px;
}
.finance-date-menu__heading strong {
  flex: 1;
  font-size: 0.9rem;
}
.finance-date-menu__body {
  display: grid;
  gap: 18px;
  padding: 18px !important;
}
@media (max-width: 900px) {
  .finance-filters {
    flex-wrap: wrap;
  }
  .finance-filters :deep(.v-input) {
    flex: 1 1 145px;
    max-inline-size: none;
  }
}
@media (max-width: 600px) {
  .finance-date {
    flex: 1 1 auto;
  }
}
</style>
