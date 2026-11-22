<script lang="ts" setup>
import { computed, onBeforeMount, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import TimePicker from '@/modules/core/components/Form/TimePicker.vue'
import { useForm } from '@/modules/core/composables/form.ts'
import { useReservationWaitlist } from '@/modules/reservation/composables/reservationWaitlist.ts'

const props = defineProps<{
  item: Record<string, any>
}>()

const emit = defineEmits<{
  saved: [reservation: Record<string, any>]
}>()

const { t } = useI18n()
const { can } = useAuth()
const { convertReservationWaitlist, getReservationWaitlistFormMeta } = useReservationWaitlist()

const meta = ref<Record<string, any>>({ tables: [] })
const form = useForm({
  start_time: props.item?.preferred_start_time || '',
  table_ids: [] as number[],
})

const canConvertWaitlist = computed(() => can('admin.reservation_waitlists.convert_to_reservation'))

async function loadMeta() {
  if (!props.item?.branch?.id) {
    meta.value.tables = []
    return
  }

  const response = (await getReservationWaitlistFormMeta(props.item.branch.id)).data.body
  meta.value.tables = response.tables || []
}

async function submit() {
  if (!canConvertWaitlist.value) {
    return
  }

  const response = await form.submit(
    () => convertReservationWaitlist(props.item.id, form.state),
    true,
  )

  if (response && typeof response === 'object') {
    emit('saved', response.data.body)
  }
}

onBeforeMount(loadMeta)

defineExpose({
  submit,
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <TimePicker
        v-model="form.state.start_time"
        :error="!!form.errors.value?.start_time"
        :error-messages="form.errors.value?.start_time"
        :label="t('reservation::attributes.reservations.start_time')"
        required
      />
    </VCol>

    <VCol cols="12">
      <VSelect
        v-model="form.state.table_ids"
        chips
        :error="!!form.errors.value?.table_ids"
        :error-messages="form.errors.value?.table_ids"
        item-title="name"
        item-value="id"
        :items="meta.tables"
        :label="t('reservation::attributes.reservations.table_ids')"
        multiple
      />
    </VCol>
  </VRow>
</template>
