<script lang="ts" setup>
import type { RouteLocationRaw } from 'vue-router'
import {
  addMonths,
  eachDayOfInterval,
  endOfMonth,
  endOfWeek,
  format,
  isSameDay,
  isSameMonth,
  parseISO,
  startOfMonth,
  startOfWeek,
  subMonths,
} from 'date-fns'
import { computed, onBeforeMount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import { useAppStore } from '@/modules/core/stores/appStore.ts'
import { useReservation } from '@/modules/reservation/composables/reservation.ts'

type CalendarEvent = {
  id: number
  title: string
  start: string
  end: string
  status?: {
    id?: string
    name?: string
    color?: string
  }
  guest_count?: number
}

type CalendarDay = {
  key: string
  date: Date
  dayNumber: string
  inMonth: boolean
  isToday: boolean
  events: CalendarEvent[]
}

const { t } = useI18n()
const router = useRouter()
const appStore = useAppStore()
const { can } = useAuth()
const { getReservationCalendar } = useReservation()

const calendarLoading = ref(false)
const calendar = ref<CalendarEvent[]>([])
const currentMonth = ref(startOfMonth(new Date()))
const dayDialog = ref(false)
const selectedDay = ref<CalendarDay | null>(null)
let calendarRequestId = 0

const locale = computed(() => appStore.appCurrentLocale || 'en')
const previousMonthIcon = computed(() =>
  appStore.isRtl ? 'tabler-chevron-right' : 'tabler-chevron-left',
)
const nextMonthIcon = computed(() =>
  appStore.isRtl ? 'tabler-chevron-left' : 'tabler-chevron-right',
)
const monthStart = computed(() => startOfMonth(currentMonth.value))
const monthEnd = computed(() => endOfMonth(currentMonth.value))
const calendarStart = computed(() => startOfWeek(monthStart.value, { weekStartsOn: 1 }))
const calendarEnd = computed(() => endOfWeek(monthEnd.value, { weekStartsOn: 1 }))
const monthLabel = computed(() =>
  new Intl.DateTimeFormat(locale.value, {
    month: 'long',
    year: 'numeric',
  }).format(currentMonth.value),
)
const weekDays = computed(() => {
  return eachDayOfInterval({
    start: calendarStart.value,
    end: endOfWeek(calendarStart.value, { weekStartsOn: 1 }),
  }).map((day) => new Intl.DateTimeFormat(locale.value, { weekday: 'short' }).format(day))
})
const monthFilters = computed(() => ({
  from: format(monthStart.value, 'yyyy-MM-dd'),
  to: format(monthEnd.value, 'yyyy-MM-dd'),
}))
const eventsByDate = computed(() => {
  return calendar.value.reduce((dates, event) => {
    if (!event.start) {
      return dates
    }

    const dateKey = format(parseISO(event.start), 'yyyy-MM-dd')
    const events = dates.get(dateKey) || []

    events.push(event)
    dates.set(dateKey, events)

    return dates
  }, new Map<string, CalendarEvent[]>())
})
const calendarDays = computed<CalendarDay[]>(() => {
  return eachDayOfInterval({
    start: calendarStart.value,
    end: calendarEnd.value,
  }).map((day) => {
    const key = format(day, 'yyyy-MM-dd')

    return {
      key,
      date: day,
      dayNumber: format(day, 'd'),
      inMonth: isSameMonth(day, currentMonth.value),
      isToday: isSameDay(day, new Date()),
      events: eventsByDate.value.get(key) || [],
    }
  })
})
const selectedDayEvents = computed(() => selectedDay.value?.events || [])
const selectedDayLabel = computed(() =>
  selectedDay.value
    ? new Intl.DateTimeFormat(locale.value, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      }).format(selectedDay.value.date)
    : '',
)

async function loadCalendar() {
  const requestId = ++calendarRequestId
  calendarLoading.value = true

  try {
    const calendarResponse = await getReservationCalendar(monthFilters.value)

    if (requestId === calendarRequestId) {
      calendar.value = calendarResponse.data.body || []
    }
  } finally {
    if (requestId === calendarRequestId) {
      calendarLoading.value = false
    }
  }
}

function previousMonth() {
  currentMonth.value = subMonths(currentMonth.value, 1)
}

function nextMonth() {
  currentMonth.value = addMonths(currentMonth.value, 1)
}

function goToCurrentMonth() {
  const nextMonthValue = startOfMonth(new Date())

  if (isSameMonth(currentMonth.value, nextMonthValue)) {
    loadCalendar()
    return
  }

  currentMonth.value = nextMonthValue
}

function visibleEvents(day: CalendarDay): CalendarEvent[] {
  return day.events.slice(0, 3)
}

function hiddenEventsCount(day: CalendarDay): number {
  return Math.max(day.events.length - 3, 0)
}

function formatEventTime(value?: string): string {
  if (!value) {
    return ''
  }

  return new Intl.DateTimeFormat(locale.value, {
    hour: '2-digit',
    minute: '2-digit',
  }).format(parseISO(value))
}

function openDayDialog(day: CalendarDay) {
  selectedDay.value = day
  dayDialog.value = true
}

async function openReservation(event: CalendarEvent | Record<string, unknown>) {
  if (!can('admin.reservations.show') || !event.id) {
    return
  }

  await router.push({
    name: 'admin.reservations.show',
    params: { id: event.id },
  } as unknown as RouteLocationRaw)
}

onBeforeMount(loadCalendar)

watch(currentMonth, () => loadCalendar())
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard class="reservation-calendar-card position-relative">
        <VCardTitle class="reservation-calendar__header">
          <div class="d-flex align-center">
            <VIcon
              class="me-2"
              icon="tabler-calendar"
              size="20"
            />
            <span>{{ t('reservation::sidebar.reservation_calendar') }}</span>
          </div>

          <div class="reservation-calendar__controls">
            <IconBtn
              :disabled="calendarLoading"
              :title="t('reservation::frontend.calendar_previous_month')"
              variant="tonal"
              @click="previousMonth"
            >
              <VIcon :icon="previousMonthIcon" />
            </IconBtn>

            <div class="reservation-calendar__month">
              {{ monthLabel }}
            </div>

            <IconBtn
              :disabled="calendarLoading"
              :title="t('reservation::frontend.calendar_next_month')"
              variant="tonal"
              @click="nextMonth"
            >
              <VIcon :icon="nextMonthIcon" />
            </IconBtn>

            <VBtn
              color="primary"
              :disabled="calendarLoading"
              prepend-icon="tabler-calendar-dot"
              variant="tonal"
              @click="goToCurrentMonth"
            >
              {{ t('reservation::frontend.calendar_today') }}
            </VBtn>
          </div>
        </VCardTitle>

        <VCardText>
          <div class="reservation-calendar__body">
            <VOverlay
              class="align-center justify-center"
              contained
              :model-value="calendarLoading"
              persistent
            >
              <div class="reservation-calendar__loader">
                <VProgressCircular
                  color="primary"
                  indeterminate
                  size="42"
                />
                <span>{{ t('reservation::frontend.calendar_loading') }}</span>
              </div>
            </VOverlay>

            <div class="reservation-calendar">
              <div
                v-for="weekday in weekDays"
                :key="weekday"
                class="reservation-calendar__weekday"
              >
                {{ weekday }}
              </div>

              <div
                v-for="day in calendarDays"
                :key="day.key"
                class="reservation-calendar__day"
                :class="{
                  'reservation-calendar__day--muted': !day.inMonth,
                  'reservation-calendar__day--today': day.isToday,
                }"
              >
                <div class="reservation-calendar__day-number">
                  {{ day.dayNumber }}
                </div>

                <div class="reservation-calendar__events">
                  <button
                    v-for="event in visibleEvents(day)"
                    :key="event.id"
                    class="reservation-calendar__event"
                    :class="{
                      'reservation-calendar__event--clickable':
                        can('admin.reservations.show') && !calendarLoading,
                    }"
                    :disabled="calendarLoading"
                    :style="{
                      borderInlineStartColor: event.status?.color || 'rgb(var(--v-theme-primary))',
                    }"
                    type="button"
                    @click.stop="openReservation(event)"
                  >
                    <span class="reservation-calendar__event-title">
                      {{ event.title }}
                    </span>

                    <span class="reservation-calendar__event-meta">
                      {{ formatEventTime(event.start) }}
                      <span v-if="event.guest_count">
                        -
                        {{
                          t('reservation::frontend.guest_target', {
                            value: event.guest_count,
                          })
                        }}
                      </span>
                    </span>
                  </button>

                  <button
                    v-if="hiddenEventsCount(day) > 0"
                    class="reservation-calendar__more"
                    :disabled="calendarLoading"
                    type="button"
                    @click.stop="openDayDialog(day)"
                  >
                    {{
                      t('reservation::frontend.calendar_more_events', {
                        value: hiddenEventsCount(day),
                      })
                    }}
                  </button>

                  <span
                    v-if="day.events.length === 0 && day.inMonth"
                    class="reservation-calendar__empty"
                  >
                    {{ t('reservation::frontend.calendar_no_reservations') }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <VDialog
      v-model="dayDialog"
      max-width="760"
      scrollable
    >
      <VCard>
        <VCardTitle class="reservation-day-dialog__title">
          <div class="d-flex align-center">
            <VIcon
              class="me-2"
              icon="tabler-calendar-event"
              size="20"
            />

            <span>
              {{
                t('reservation::frontend.calendar_day_reservations', {
                  date: selectedDayLabel,
                })
              }}
            </span>
          </div>

          <VChip
            color="primary"
            size="small"
            variant="tonal"
          >
            {{
              t('reservation::frontend.calendar_reservations_count', {
                value: selectedDayEvents.length,
              })
            }}
          </VChip>
        </VCardTitle>

        <VCardText>
          <div class="reservation-day-dialog__list">
            <button
              v-for="event in selectedDayEvents"
              :key="event.id"
              class="reservation-day-dialog__event"
              :class="{
                'reservation-day-dialog__event--clickable': can('admin.reservations.show'),
              }"
              :style="{
                borderInlineStartColor: event.status?.color || 'rgb(var(--v-theme-primary))',
              }"
              type="button"
              @click.stop="openReservation(event)"
            >
              <div>
                <div class="reservation-day-dialog__event-title">
                  {{ event.title }}
                </div>

                <div class="reservation-day-dialog__event-meta">
                  {{ formatEventTime(event.start) }}
                  <span v-if="event.guest_count">
                    -
                    {{
                      t('reservation::frontend.guest_target', {
                        value: event.guest_count,
                      })
                    }}
                  </span>
                </div>
              </div>

              <VChip
                v-if="event.status?.name"
                size="x-small"
                :style="{ color: event.status?.color || undefined }"
                variant="tonal"
              >
                {{ event.status.name }}
              </VChip>
            </button>
          </div>
        </VCardText>

        <VCardActions>
          <VSpacer />

          <VBtn
            color="default"
            @click="dayDialog = false"
          >
            {{ t('admin::admin.buttons.close') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VRow>
</template>

<style lang="scss" scoped>
.reservation-calendar__header {
  align-items: center;
  display: flex;
  gap: 16px;
  justify-content: space-between;
}

.reservation-calendar__controls {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  justify-content: flex-end;
}

.reservation-calendar__month {
  font-size: 1rem;
  font-weight: 600;
  min-inline-size: 150px;
  text-align: center;
}

.reservation-calendar__loader {
  align-items: center;
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  box-shadow: 0 8px 26px rgba(var(--v-shadow-key-umbra-color), 0.12);
  color: rgb(var(--v-theme-on-surface));
  display: flex;
  gap: 12px;
  padding: 16px 18px;
}

.reservation-calendar__body {
  border-radius: 8px;
  overflow: hidden;
  position: relative;
}

.reservation-calendar {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  overflow: hidden;
}

.reservation-calendar__weekday {
  background: rgb(var(--v-theme-surface));
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  color: rgba(var(--v-theme-on-surface), 0.7);
  font-size: 0.8125rem;
  font-weight: 600;
  padding: 12px;
  text-align: center;
}

.reservation-calendar__day {
  background: rgb(var(--v-theme-surface));
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-inline-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  min-block-size: 138px;
  padding: 10px;
}

.reservation-calendar__day:nth-child(7n + 7) {
  border-inline-end: 0;
}

.reservation-calendar__day--muted {
  background: rgba(var(--v-theme-on-surface), 0.025);
}

.reservation-calendar__day--muted .reservation-calendar__day-number,
.reservation-calendar__day--muted .reservation-calendar__empty {
  opacity: 0.45;
}

.reservation-calendar__day--today {
  box-shadow: inset 0 0 0 2px rgb(var(--v-theme-primary));
}

.reservation-calendar__day-number {
  align-items: center;
  block-size: 28px;
  display: flex;
  font-weight: 600;
  inline-size: 28px;
  justify-content: center;
  margin-block-end: 8px;
}

.reservation-calendar__day--today .reservation-calendar__day-number {
  background: rgb(var(--v-theme-primary));
  border-radius: 50%;
  color: rgb(var(--v-theme-on-primary));
}

.reservation-calendar__events {
  display: grid;
  gap: 6px;
}

.reservation-calendar__event {
  background: rgba(var(--v-theme-on-surface), 0.035);
  border: 0;
  border-inline-start: 3px solid rgb(var(--v-theme-primary));
  border-radius: 6px;
  color: rgb(var(--v-theme-on-surface));
  display: grid;
  gap: 2px;
  inline-size: 100%;
  min-inline-size: 0;
  padding: 7px 8px;
  text-align: start;
}

.reservation-calendar__event--clickable {
  cursor: pointer;
}

.reservation-calendar__event--clickable:hover {
  background: rgba(var(--v-theme-primary), 0.1);
}

.reservation-calendar__event:disabled,
.reservation-calendar__more:disabled {
  cursor: default;
  opacity: 0.65;
}

.reservation-calendar__event-title {
  font-size: 0.8125rem;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.reservation-calendar__event-meta {
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 0.75rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.reservation-calendar__empty {
  color: rgba(var(--v-theme-on-surface), 0.45);
  font-size: 0.75rem;
}

.reservation-calendar__more {
  background: rgba(var(--v-theme-primary), 0.09);
  border: 0;
  border-radius: 999px;
  color: rgb(var(--v-theme-primary));
  cursor: pointer;
  font-size: 0.75rem;
  font-weight: 600;
  inline-size: fit-content;
  padding: 4px 9px;
}

.reservation-calendar__more:hover {
  background: rgba(var(--v-theme-primary), 0.16);
}

.reservation-calendar__status-dot {
  border-radius: 999px;
  display: inline-block;
  flex: 0 0 10px;
  height: 10px;
  width: 10px;
}

.reservation-day-dialog__title {
  align-items: center;
  display: flex;
  gap: 12px;
  justify-content: space-between;
}

.reservation-day-dialog__event {
  background: rgba(var(--v-theme-on-surface), 0.025);
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 7px;
  color: rgb(var(--v-theme-on-surface));
  display: grid;
  gap: 2px;
  inline-size: 100%;
  padding: 9px 10px;
  text-align: start;
}

.reservation-day-dialog__event--clickable {
  cursor: pointer;
}

.reservation-day-dialog__event--clickable:hover {
  border-color: rgba(var(--v-theme-primary), 0.45);
}

.reservation-day-dialog__event-title {
  font-size: 0.875rem;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.reservation-day-dialog__event-meta {
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 0.75rem;
}

.reservation-day-dialog__list {
  display: grid;
  gap: 10px;
}

.reservation-day-dialog__event {
  align-items: center;
  border-inline-start: 4px solid rgb(var(--v-theme-primary));
  grid-template-columns: minmax(0, 1fr) auto;
}

@media (max-width: 960px) {
  .reservation-calendar {
    display: block;
  }

  .reservation-calendar__weekday {
    display: none;
  }

  .reservation-calendar__day {
    border-inline-end: 0;
    min-block-size: auto;
  }

  .reservation-calendar__header {
    align-items: flex-start;
    flex-direction: column;
  }

  .reservation-calendar__controls {
    justify-content: flex-start;
  }
}
</style>
