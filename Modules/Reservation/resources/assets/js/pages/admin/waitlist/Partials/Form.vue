<script lang="ts" setup>
import { onBeforeMount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { type RouteLocationRaw, useRouter } from 'vue-router'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import BaseForm from '@/modules/core/components/Form/BaseForm.vue'
import DatePicker from '@/modules/core/components/Form/DatePicker.vue'
import TimePicker from '@/modules/core/components/Form/TimePicker.vue'
import { useForm } from '@/modules/core/composables/form.ts'
import { useReservationWaitlist } from '@/modules/reservation/composables/reservationWaitlist.ts'

const props = defineProps<{
  item?: Record<string, any> | null
  action: 'update' | 'create'
}>()

const { t } = useI18n()
const { user } = useAuth()
const router = useRouter()
const { storeReservationWaitlist, updateReservationWaitlist, getReservationWaitlistFormMeta } =
  useReservationWaitlist()

const form = useForm({
  branch_id: user?.assigned_to_branch ? user.branch_id : props.item?.branch?.id,
  customer_id: props.item?.customer?.id || null,
  phone_country_iso_code:
    props.item?.phone_country_iso_code || props.item?.customer?.phone_country_iso_code || 'JO',
  customer_name: props.item?.customer_name || '',
  customer_phone: props.item?.customer_national_phone || props.item?.customer_phone || '',
  customer_email: props.item?.customer_email || '',
  guest_count: props.item?.guest_count || 2,
  estimated_wait_minutes: props.item?.estimated_wait_minutes ?? null,
  preferred_date: props.item?.preferred_date || '',
  preferred_start_time: props.item?.preferred_start_time || '',
  preferred_end_time: props.item?.preferred_end_time || '',
  occasion: props.item?.occasion || '',
  special_requests: props.item?.special_requests || '',
  internal_notes: props.item?.internal_notes || '',
})
const meta = ref<Record<string, any>>({ branches: [], countries: [], customers: [], tables: [] })

async function submit() {
  const payload = { ...form.state }
  if (props.action === 'update') {
    delete payload.branch_id
  }

  if (
    !form.loading.value &&
    (await form.submit(() =>
      props.action === 'create'
        ? storeReservationWaitlist(payload)
        : updateReservationWaitlist(props.item?.id, payload),
    ))
  ) {
    await router.push({
      name: 'admin.reservation_waitlists.index',
    } as unknown as RouteLocationRaw)
  }
}

async function loadBaseMeta() {
  const response = (await getReservationWaitlistFormMeta()).data.body
  meta.value = {
    ...meta.value,
    ...response,
  }
}

async function loadBranchMeta(branchId?: number | null) {
  if (!branchId && !user?.assigned_to_branch) {
    meta.value = {
      ...meta.value,
      customers: [],
      tables: [],
    }
    return
  }

  const response = (await getReservationWaitlistFormMeta(branchId)).data.body
  meta.value = {
    ...meta.value,
    ...response,
  }
}

onBeforeMount(async () => {
  await loadBaseMeta()
  await loadBranchMeta(form.state.branch_id)
})

watch(
  () => form.state.branch_id,
  (branchId) => {
    form.state.customer_id = null
    loadBranchMeta(branchId)
  },
)

watch(
  () => form.state.customer_id,
  (customerId) => {
    if (!customerId) {
      return
    }

    const customer = meta.value.customers.find(
      (item: Record<string, any>) => item.id === customerId,
    )
    if (!customer) {
      return
    }

    form.state.customer_name = customer.name || form.state.customer_name
    form.state.customer_phone =
      customer.national_phone || customer.phone || form.state.customer_phone
    form.state.customer_email = customer.email || form.state.customer_email
    form.state.phone_country_iso_code =
      customer.phone_country_iso_code || form.state.phone_country_iso_code
  },
)
</script>

<template>
  <BaseForm
    :action="action"
    :loading="form.loading.value"
    resource="reservation_waitlists"
    @submit="submit"
  >
    <VRow>
      <VCol
        cols="12"
        lg="8"
      >
        <VRow>
          <VCol cols="12">
            <VCard>
              <VCardTitle class="d-flex align-center">
                <VIcon
                  class="me-2"
                  icon="tabler-user-circle"
                  size="20"
                />
                <span>{{ t('reservation::frontend.waitlist_information') }}</span>
              </VCardTitle>

              <VCardText>
                <VRow>
                  <VCol
                    v-if="action === 'create' && !user?.assigned_to_branch"
                    cols="12"
                    md="6"
                  >
                    <VSelect
                      v-model="form.state.branch_id"
                      :error="!!form.errors.value?.branch_id"
                      :error-messages="form.errors.value?.branch_id"
                      item-title="name"
                      item-value="id"
                      :items="meta.branches"
                      :label="t('reservation::attributes.reservation_waitlists.branch_id')"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="6"
                  >
                    <VSelect
                      v-model="form.state.customer_id"
                      clearable
                      :error="!!form.errors.value?.customer_id"
                      :error-messages="form.errors.value?.customer_id"
                      item-title="name"
                      item-value="id"
                      :items="meta.customers"
                      :label="t('reservation::attributes.reservation_waitlists.customer_id')"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="6"
                  >
                    <VTextField
                      v-model="form.state.customer_name"
                      :error="!!form.errors.value?.customer_name"
                      :error-messages="form.errors.value?.customer_name"
                      :label="t('reservation::attributes.reservation_waitlists.customer_name')"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="6"
                  >
                    <VTextField
                      v-model="form.state.customer_email"
                      :error="!!form.errors.value?.customer_email"
                      :error-messages="form.errors.value?.customer_email"
                      :label="t('reservation::attributes.reservation_waitlists.customer_email')"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <VSelect
                      v-model="form.state.phone_country_iso_code"
                      :error="!!form.errors.value?.phone_country_iso_code"
                      :error-messages="form.errors.value?.phone_country_iso_code"
                      item-title="name"
                      item-value="id"
                      :items="meta.countries"
                      :label="
                        t('reservation::attributes.reservation_waitlists.phone_country_iso_code')
                      "
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="8"
                  >
                    <VTextField
                      v-model="form.state.customer_phone"
                      :error="!!form.errors.value?.customer_phone"
                      :error-messages="form.errors.value?.customer_phone"
                      :label="t('reservation::attributes.reservation_waitlists.customer_phone')"
                    />
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </VCol>

          <VCol cols="12">
            <VCard>
              <VCardTitle class="d-flex align-center">
                <VIcon
                  class="me-2"
                  icon="tabler-calendar-time"
                  size="20"
                />
                <span>{{ t('reservation::frontend.waitlist_preferences') }}</span>
              </VCardTitle>

              <VCardText>
                <VRow>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <VTextField
                      v-model.number="form.state.guest_count"
                      :error="!!form.errors.value?.guest_count"
                      :error-messages="form.errors.value?.guest_count"
                      :label="t('reservation::attributes.reservation_waitlists.guest_count')"
                      type="number"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="6"
                  >
                    <VTextField
                      v-model="form.state.occasion"
                      :error="!!form.errors.value?.occasion"
                      :error-messages="form.errors.value?.occasion"
                      :label="t('reservation::attributes.reservation_waitlists.occasion')"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="6"
                  >
                    <VTextField
                      v-model.number="form.state.estimated_wait_minutes"
                      :error="!!form.errors.value?.estimated_wait_minutes"
                      :error-messages="form.errors.value?.estimated_wait_minutes"
                      :label="
                        t('reservation::attributes.reservation_waitlists.estimated_wait_minutes')
                      "
                      type="number"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="6"
                  >
                    <DatePicker
                      v-model="form.state.preferred_date"
                      :error="!!form.errors.value?.preferred_date"
                      :error-messages="form.errors.value?.preferred_date"
                      :label="t('reservation::attributes.reservation_waitlists.preferred_date')"
                      required
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="6"
                  >
                    <TimePicker
                      v-model="form.state.preferred_start_time"
                      clearable
                      :error="!!form.errors.value?.preferred_start_time"
                      :error-messages="form.errors.value?.preferred_start_time"
                      :label="
                        t('reservation::attributes.reservation_waitlists.preferred_start_time')
                      "
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="6"
                  >
                    <TimePicker
                      v-model="form.state.preferred_end_time"
                      clearable
                      :error="!!form.errors.value?.preferred_end_time"
                      :error-messages="form.errors.value?.preferred_end_time"
                      :label="t('reservation::attributes.reservation_waitlists.preferred_end_time')"
                    />
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCol>

      <VCol
        cols="12"
        lg="4"
      >
        <VCard>
          <VCardTitle class="d-flex align-center">
            <VIcon
              class="me-2"
              icon="tabler-notes"
              size="20"
            />
            <span>{{ t('reservation::frontend.notes') }}</span>
          </VCardTitle>

          <VCardText>
            <VRow>
              <VCol cols="12">
                <VTextarea
                  v-model="form.state.special_requests"
                  :error="!!form.errors.value?.special_requests"
                  :error-messages="form.errors.value?.special_requests"
                  :label="t('reservation::attributes.reservation_waitlists.special_requests')"
                  rows="3"
                />
              </VCol>

              <VCol cols="12">
                <VTextarea
                  v-model="form.state.internal_notes"
                  :error="!!form.errors.value?.internal_notes"
                  :error-messages="form.errors.value?.internal_notes"
                  :label="t('reservation::attributes.reservation_waitlists.internal_notes')"
                  rows="3"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </BaseForm>
</template>
