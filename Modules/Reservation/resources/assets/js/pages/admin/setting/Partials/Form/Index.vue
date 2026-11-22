<script lang="ts" setup>
import { onBeforeMount, ref } from 'vue'
import { type RouteLocationRaw, useRouter } from 'vue-router'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import BaseForm from '@/modules/core/components/Form/BaseForm.vue'
import { useForm } from '@/modules/core/composables/form.ts'
import { useReservationSetting } from '@/modules/reservation/composables/reservationSetting.ts'
import Automation from './Automation.vue'
import BookingPolicy from './BookingPolicy.vue'
import DepositWaitlist from './DepositWaitlist.vue'
import Policy from './Policy.vue'
import WorkingHours from './WorkingHours.vue'

type DayKey = 'monday' | 'tuesday' | 'wednesday' | 'thursday' | 'friday' | 'saturday' | 'sunday'
type WorkingHourValue = { enabled: boolean; open: string; close: string }
type WorkingHoursState = Record<DayKey, WorkingHourValue>

const props = defineProps<{
  item?: Record<string, any> | null
  action: 'update' | 'create'
}>()

const { user } = useAuth()
const router = useRouter()
const { getReservationSettingFormMeta, storeReservationSetting, updateReservationSetting } =
  useReservationSetting()

const defaultHours: WorkingHoursState = {
  monday: { enabled: true, open: '09:00 AM', close: '11:00 PM' },
  tuesday: { enabled: true, open: '09:00 AM', close: '11:00 PM' },
  wednesday: { enabled: true, open: '09:00 AM', close: '11:00 PM' },
  thursday: { enabled: true, open: '09:00 AM', close: '11:00 PM' },
  friday: { enabled: true, open: '09:00 AM', close: '11:00 PM' },
  saturday: { enabled: true, open: '09:00 AM', close: '11:00 PM' },
  sunday: { enabled: true, open: '09:00 AM', close: '11:00 PM' },
}

const form = useForm({
  branch_id: user?.assigned_to_branch ? user.branch_id : props.item?.branch?.id,
  reservations_enabled: props.item?.reservations_enabled ?? true,
  auto_confirm_enabled: props.item?.auto_confirm_enabled ?? false,
  default_duration_minutes: props.item?.default_duration_minutes || 90,
  min_duration_minutes: props.item?.min_duration_minutes || 30,
  max_duration_minutes: props.item?.max_duration_minutes || 240,
  buffer_minutes: props.item?.buffer_minutes || 15,
  slot_interval_minutes: props.item?.slot_interval_minutes || 15,
  min_guests: props.item?.min_guests || 1,
  max_guests: props.item?.max_guests || 30,
  large_party_threshold: props.item?.large_party_threshold || 8,
  large_party_requires_approval: props.item?.large_party_requires_approval ?? true,
  min_advance_minutes: props.item?.min_advance_minutes || 60,
  max_advance_days: props.item?.max_advance_days || 30,
  allow_walk_in: props.item?.allow_walk_in ?? true,
  pending_blocks_capacity: props.item?.pending_blocks_capacity ?? true,
  require_table_on_create: props.item?.require_table_on_create ?? false,
  require_table_on_confirm: props.item?.require_table_on_confirm ?? false,
  require_table_on_arrival: props.item?.require_table_on_arrival ?? false,
  require_table_on_seating: props.item?.require_table_on_seating ?? true,
  cancellation_allowed: props.item?.cancellation_allowed ?? true,
  require_cancellation_reason: props.item?.require_cancellation_reason ?? true,
  cancellation_deadline_minutes: props.item?.cancellation_deadline_minutes || null,
  grace_period_minutes: props.item?.grace_period_minutes || 15,
  auto_no_show_enabled: props.item?.auto_no_show_enabled ?? false,
  pending_expiry_minutes: props.item?.pending_expiry_minutes || 0,
  deposit_enabled: props.item?.deposit_enabled ?? false,
  deposit_required_for_large_party: props.item?.deposit_required_for_large_party ?? false,
  deposit_type: 'fixed',
  deposit_amount: props.item?.deposit_amount || 0,
  deposit_refundable: props.item?.deposit_refundable ?? true,
  deposit_payment_deadline_minutes: props.item?.deposit_payment_deadline_minutes || null,
  reminder_enabled: props.item?.reminder_enabled ?? true,
  reminder_before_minutes: props.item?.reminder_before_minutes || 120,
  waitlist_enabled: props.item?.waitlist_enabled ?? true,
  waitlist_expiry_minutes: props.item?.waitlist_expiry_minutes || null,
  working_hours: defaultHours,
})

const meta = ref<Record<string, any>>({ branches: [], working_hours: defaultHours })
const days: DayKey[] = [
  'monday',
  'tuesday',
  'wednesday',
  'thursday',
  'friday',
  'saturday',
  'sunday',
]
function normalizeWorkingHours(
  workingHours?: Partial<Record<DayKey, Partial<WorkingHourValue>>> | null,
): WorkingHoursState {
  return days.reduce((acc, day) => {
    const source = workingHours?.[day] ?? {}
    const fallback = defaultHours[day]

    acc[day] = {
      enabled: Boolean(source.enabled ?? fallback.enabled),
      open: source.open ?? fallback.open,
      close: source.close ?? fallback.close,
    }

    return acc
  }, {} as WorkingHoursState)
}

form.state.working_hours = normalizeWorkingHours(props.item?.working_hours)

async function submit() {
  const payload = { ...form.state }
  if (props.action === 'update') {
    delete payload.branch_id
  }

  if (
    !form.loading.value &&
    (await form.submit(() =>
      props.action === 'create'
        ? storeReservationSetting(payload)
        : updateReservationSetting(props.item?.id, payload),
    ))
  ) {
    await router.push({
      name: 'admin.reservation_settings.index',
    } as unknown as RouteLocationRaw)
  }
}

onBeforeMount(async () => {
  const response = (await getReservationSettingFormMeta()).data.body
  meta.value = response
  if (response.working_hours) {
    form.state.working_hours = normalizeWorkingHours(
      props.item?.working_hours ?? response.working_hours,
    )
  }
})
</script>

<template>
  <BaseForm
    :action="action"
    :loading="form.loading.value"
    resource="reservation_settings"
    @submit="submit"
  >
    <VRow>
      <VCol
        cols="12"
        md="6"
      >
        <VRow>
          <VCol cols="12">
            <Policy
              :action="action"
              :form="form"
              :meta="meta"
            />
          </VCol>

          <VCol cols="12">
            <WorkingHours
              :days="days"
              :form="form"
            />
          </VCol>
        </VRow>
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <VCol cols="12">
          <BookingPolicy :form="form" />
        </VCol>

        <VCol cols="12">
          <Automation :form="form" />
        </VCol>

        <VCol cols="12">
          <DepositWaitlist :form="form" />
        </VCol>
      </VCol>
    </VRow>
  </BaseForm>
</template>
