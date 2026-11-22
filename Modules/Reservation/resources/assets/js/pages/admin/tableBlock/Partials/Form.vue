<script lang="ts" setup>
import { onBeforeMount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { type RouteLocationRaw, useRouter } from 'vue-router'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import BaseForm from '@/modules/core/components/Form/BaseForm.vue'
import DateTimePicker from '@/modules/core/components/Form/DateTimePicker.vue'
import { useForm } from '@/modules/core/composables/form.ts'
import { useReservation } from '@/modules/reservation/composables/reservation.ts'
import { useReservationTableBlock } from '@/modules/reservation/composables/reservationTableBlock.ts'

const props = defineProps<{
  item?: Record<string, any> | null
  action: 'update' | 'create'
}>()

const { t } = useI18n()
const { user } = useAuth()
const router = useRouter()
const { getReservationFormMeta } = useReservation()
const { storeReservationTableBlock, updateReservationTableBlock } = useReservationTableBlock()

const form = useForm({
  branch_id: user?.assigned_to_branch ? user.branch_id : props.item?.branch?.id,
  floor_id: props.item?.table?.floor?.id || null,
  zone_id: props.item?.table?.zone?.id || null,
  table_id: props.item?.table?.id,
  title: props.item?.title || {},
  reason: props.item?.reason || '',
  start_at: props.item?.start_at || '',
  end_at: props.item?.end_at || '',
})

const meta = ref<Record<string, any>>({ branches: [], floors: [], zones: [], tables: [] })

async function submit() {
  const payload = { ...form.state }
  if (props.action === 'update') {
    delete payload.branch_id
  }

  if (
    !form.loading.value &&
    (await form.submit(() =>
      props.action === 'create'
        ? storeReservationTableBlock(payload)
        : updateReservationTableBlock(props.item?.id, payload),
    ))
  ) {
    await router.push({
      name: 'admin.reservation_table_blocks.index',
    } as unknown as RouteLocationRaw)
  }
}

async function loadBaseMeta() {
  const response = (await getReservationFormMeta()).data.body
  meta.value = {
    ...meta.value,
    ...response,
  }
}

async function loadBranchMeta(
  branchId?: number | null,
  floorId?: number | null,
  zoneId?: number | null,
) {
  if (!branchId) {
    meta.value = {
      ...meta.value,
      floors: [],
      zones: [],
      tables: [],
    }
    return
  }

  const response = (await getReservationFormMeta(branchId, floorId, zoneId)).data.body
  meta.value = {
    ...meta.value,
    ...response,
  }
}

onBeforeMount(async () => {
  await loadBaseMeta()
  await loadBranchMeta(form.state.branch_id, form.state.floor_id, form.state.zone_id)
})

watch(
  () => form.state.branch_id,
  (branchId) => {
    form.state.floor_id = null
    form.state.zone_id = null
    form.state.table_id = null
    loadBranchMeta(branchId)
  },
)

watch(
  () => form.state.floor_id,
  (floorId) => {
    form.state.zone_id = null
    form.state.table_id = null
    if (form.state.branch_id && floorId) {
      loadBranchMeta(form.state.branch_id, floorId)
    }
  },
)

watch(
  () => form.state.zone_id,
  (zoneId) => {
    form.state.table_id = null
    if (form.state.branch_id && form.state.floor_id && zoneId) {
      loadBranchMeta(form.state.branch_id, form.state.floor_id, zoneId)
    }
  },
)
</script>

<template>
  <BaseForm
    v-slot="{ currentLanguage }"
    :action="action"
    has-multiple-language
    :loading="form.loading.value"
    resource="reservation_table_blocks"
    @submit="submit"
  >
    <VRow>
      <VCol
        cols="12"
        lg="8"
      >
        <VCard>
          <VCardTitle class="d-flex justify-space-between align-center mb-2">
            <div class="d-flex align-center">
              <VIcon
                class="me-2"
                icon="tabler-info-circle"
                size="20"
              />
              <span>{{ t('reservation::frontend.table_block_information') }}</span>
            </div>
          </VCardTitle>

          <VCardText>
            <VRow>
              <VCol cols="12">
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
                      :label="t('reservation::attributes.reservation_table_blocks.branch_id')"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="6"
                  >
                    <VTextField
                      v-model="form.state.title[currentLanguage.id]"
                      :error="!!form.errors.value?.[`title.${currentLanguage.id}`]"
                      :error-messages="form.errors.value?.[`title.${currentLanguage.id}`]"
                      :label="
                        t('reservation::attributes.reservation_table_blocks.title') +
                        ` ( ${currentLanguage.name} )`
                      "
                    />
                  </VCol>
                </VRow>
              </VCol>

              <VCol
                cols="12"
                md="4"
              >
                <VSelect
                  v-model="form.state.floor_id"
                  :disabled="!form.state.branch_id"
                  :error="!!form.errors.value?.floor_id"
                  :error-messages="form.errors.value?.floor_id"
                  item-title="name"
                  item-value="id"
                  :items="meta.floors"
                  :label="t('seatingplan::attributes.tables.floor_id')"
                />
              </VCol>

              <VCol
                cols="12"
                md="4"
              >
                <VSelect
                  v-model="form.state.zone_id"
                  :disabled="!form.state.floor_id"
                  :error="!!form.errors.value?.zone_id"
                  :error-messages="form.errors.value?.zone_id"
                  item-title="name"
                  item-value="id"
                  :items="meta.zones"
                  :label="t('seatingplan::attributes.tables.zone_id')"
                />
              </VCol>

              <VCol
                cols="12"
                md="4"
              >
                <VSelect
                  v-model="form.state.table_id"
                  :disabled="!form.state.branch_id"
                  :error="!!form.errors.value?.table_id"
                  :error-messages="form.errors.value?.table_id"
                  item-title="name"
                  item-value="id"
                  :items="meta.tables"
                  :label="t('reservation::attributes.reservation_table_blocks.table_id')"
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <DateTimePicker
                  v-model="form.state.start_at"
                  :error="!!form.errors.value?.start_at"
                  :error-messages="form.errors.value?.start_at"
                  :label="t('reservation::attributes.reservation_table_blocks.start_at')"
                  required
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <DateTimePicker
                  v-model="form.state.end_at"
                  :error="!!form.errors.value?.end_at"
                  :error-messages="form.errors.value?.end_at"
                  :label="t('reservation::attributes.reservation_table_blocks.end_at')"
                  required
                />
              </VCol>

              <VCol cols="12">
                <VTextarea
                  v-model="form.state.reason"
                  :error="!!form.errors.value?.reason"
                  :error-messages="form.errors.value?.reason"
                  :label="t('reservation::attributes.reservation_table_blocks.reason')"
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
