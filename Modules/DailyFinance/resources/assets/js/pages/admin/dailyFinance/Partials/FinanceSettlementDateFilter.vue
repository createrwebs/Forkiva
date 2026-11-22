<script lang="ts" setup>
import { computed, ref, watch } from 'vue'
import { differenceInCalendarDays, format, parseISO, subDays } from 'date-fns'
import { useI18n } from 'vue-i18n'
import DatePicker from '@/modules/core/components/Form/DatePicker.vue'

const props = defineProps<{ from: string; to: string; today: string; disabled: boolean }>()
const emit = defineEmits<{ change: [range: { from: string; to: string }] }>()
const { t, locale } = useI18n()
const open = ref(false)
const draftFrom = ref(props.from)
const draftTo = ref(props.to)
const valid = computed(() => {
  if (
    !draftFrom.value ||
    !draftTo.value ||
    draftFrom.value > draftTo.value ||
    draftTo.value > props.today
  )
    return false

  return differenceInCalendarDays(parseISO(draftTo.value), parseISO(draftFrom.value)) < 366
})
const label = computed(() => {
  const formatter = new Intl.DateTimeFormat(locale.value, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
  return `${formatter.format(parseISO(props.from))} – ${formatter.format(parseISO(props.to))}`
})
watch(open, (value) => {
  if (!value) return
  draftFrom.value = props.from
  draftTo.value = props.to
})
function apply() {
  if (!valid.value || props.disabled) return
  emit('change', { from: draftFrom.value, to: draftTo.value })
  open.value = false
}
function preset(days: number) {
  draftTo.value = props.today
  draftFrom.value = format(subDays(parseISO(props.today), days - 1), 'yyyy-MM-dd')
  apply()
}
</script>

<template>
  <VMenu
    v-model="open"
    :close-on-content-click="false"
    :offset="8"
    location="bottom end"
  >
    <template #activator="{ props: activator }">
      <VBtn
        v-bind="activator"
        class="settlement-date__trigger"
        color="secondary"
        variant="tonal"
        :disabled="disabled"
        :aria-label="t('dailyfinance::payment_settlements.collected_dates')"
        prepend-icon="tabler-calendar-event"
        append-icon="tabler-chevron-down"
      >
        <span class="settlement-date__label">
          <small>{{ t('dailyfinance::payment_settlements.collected_dates') }}</small>
          <bdi>{{ label }}</bdi>
        </span>
      </VBtn>
    </template>
    <VCard
      class="settlement-date"
      elevation="8"
    >
      <header>
        <strong>{{ t('dailyfinance::payment_settlements.collected_dates') }}</strong>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          :aria-label="t('dailyfinance::payment_settlements.dismiss')"
          @click="open = false"
        />
      </header>
      <div class="settlement-date__presets">
        <VBtn
          v-for="days in [1, 7, 30]"
          :key="days"
          color="secondary"
          variant="tonal"
          size="small"
          @click="preset(days)"
        >
          {{ t(`dailyfinance::payment_settlements.range_${days}`) }}
        </VBtn>
      </div>
      <div class="settlement-date__fields">
        <DatePicker
          v-model="draftFrom"
          :label="t('dailyfinance::payment_settlements.from')"
          :max="draftTo || today"
          :disabled="disabled"
          hide-details
        />
        <DatePicker
          v-model="draftTo"
          :label="t('dailyfinance::payment_settlements.to')"
          :min="draftFrom || undefined"
          :max="today"
          :disabled="disabled"
          hide-details
        />
      </div>
      <p
        v-if="!valid"
        class="text-error"
        role="alert"
      >
        {{ t('dailyfinance::payment_settlements.date_range_hint') }}
      </p>
      <VBtn
        block
        color="primary"
        :disabled="disabled || !valid"
        @click="apply"
      >
        {{ t('dailyfinance::payment_settlements.apply_dates') }}
      </VBtn>
    </VCard>
  </VMenu>
</template>

<style lang="scss" scoped>
.settlement-date__trigger {
  block-size: 42px !important;
  inline-size: 100%;
  grid-template-columns: auto minmax(0, 1fr) auto;
  text-transform: none;
}
.settlement-date__trigger :deep(.v-btn__content) {
  flex: 1;
  justify-content: start;
  min-inline-size: 0;
}
.settlement-date__trigger :deep(.v-btn__underlay) {
  opacity: 0.05;
}
.settlement-date__trigger :deep(.v-btn__append) {
  margin-inline-start: auto;
}
.settlement-date__label {
  display: grid;
  gap: 2px;
  min-inline-size: 0;
  text-align: start;
  line-height: 1.2;
}
.settlement-date__label small {
  font-size: 0.625rem;
  opacity: 0.65;
}
.settlement-date__label bdi {
  font-size: 0.8125rem;
  overflow: hidden;
  text-overflow: ellipsis;
}
.settlement-date {
  inline-size: 360px;
  max-inline-size: calc(100vw - 40px);
  padding: 16px;
  display: grid;
  gap: 18px;
}
.settlement-date header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.9375rem;
}
.settlement-date__presets {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.settlement-date__fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
.settlement-date__fields :deep(.v-input__details) {
  display: none;
}
.settlement-date p {
  font-size: 0.75rem;
  margin: 0;
}
</style>
