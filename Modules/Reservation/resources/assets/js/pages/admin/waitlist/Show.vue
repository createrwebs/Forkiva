<script lang="ts" setup>
import { computed, onBeforeMount, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { type RouteLocationRaw, useRoute, useRouter } from 'vue-router'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import PageStateWrapper from '@/modules/core/components/PageStateWrapper.vue'
import { useConfirmDialog } from '@/modules/core/composables/confirmDialog.ts'
import { useForm } from '@/modules/core/composables/form.ts'
import { useReservationWaitlist } from '@/modules/reservation/composables/reservationWaitlist.ts'
import ConvertDialog from './Dialogs/ConvertDialog.vue'
import Actions from './Partials/Show/Actions.vue'
import Details from './Partials/Show/Details.vue'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const { can } = useAuth()
const { getShowData, notifyReservationWaitlist, cancelReservationWaitlist } =
  useReservationWaitlist()

const loading = ref(false)
const isNotFound = ref(false)
const item = ref<Record<string, any> | null>(null)
const showConvertDialog = ref(false)
const actionForm = useForm({ reason: '' })
const activeAction = ref<'notify' | 'cancel' | 'convert' | null>(null)

const canShowEdit = computed(
  () => can('admin.reservation_waitlists.edit') && item.value?.status?.id !== 'cancelled',
)
const canShowReservation = computed(() => can('admin.reservations.show'))
const canNotifyWaitlist = computed(
  () =>
    can('admin.reservation_waitlists.notify') &&
    ['waiting', 'notified'].includes(item.value?.status?.id || ''),
)
const canCancelWaitlist = computed(
  () =>
    can('admin.reservation_waitlists.cancel') &&
    !['converted', 'cancelled', 'expired'].includes(item.value?.status?.id || ''),
)
const canConvertWaitlist = computed(
  () =>
    can('admin.reservation_waitlists.convert_to_reservation') &&
    !['converted', 'cancelled', 'expired'].includes(item.value?.status?.id || ''),
)
const hasActiveAction = computed(() => activeAction.value !== null)

async function loadData(withLoader = true) {
  if (withLoader) {
    loading.value = true
  }

  const response = await getShowData((route.params as Record<string, any>).id)
  if (response.status === 200) {
    item.value = response.data
  } else if (response.status === 404) {
    isNotFound.value = true
  }

  if (withLoader) {
    loading.value = false
  }
}

async function notify() {
  if (!item.value?.id || !canNotifyWaitlist.value || hasActiveAction.value) {
    return
  }

  const confirmed = await useConfirmDialog({
    message: t('reservation::frontend.notify_confirmation_message'),
    confirmButtonText: t('admin::admin.buttons.confirm'),
  })

  if (!confirmed) {
    return
  }

  activeAction.value = 'notify'

  try {
    if (await actionForm.submit(() => notifyReservationWaitlist(item.value!.id))) {
      await loadData(false)
    }
  } finally {
    activeAction.value = null
  }
}

async function cancel() {
  if (!item.value?.id || !canCancelWaitlist.value || hasActiveAction.value) {
    return
  }

  const confirmed = await useConfirmDialog({
    message: t('reservation::frontend.cancel_confirmation_message'),
    confirmButtonText: t('admin::admin.buttons.confirm'),
    confirmColor: 'error',
  })

  if (!confirmed) {
    return
  }

  activeAction.value = 'cancel'

  try {
    if (
      await actionForm.submit(() =>
        cancelReservationWaitlist(item.value!.id, item.value!.internal_notes),
      )
    ) {
      await loadData(false)
    }
  } finally {
    activeAction.value = null
  }
}

function onConvertSubmitting(value: boolean) {
  activeAction.value = value ? 'convert' : null
}

async function onConverted(reservation: Record<string, any>) {
  showConvertDialog.value = false
  activeAction.value = null

  if (canShowReservation.value && reservation?.id) {
    await router.push({
      name: 'admin.reservations.show',
      params: { id: reservation.id },
    } as unknown as RouteLocationRaw)
    return
  }

  await loadData(false)
}

onBeforeMount(loadData)
</script>

<template>
  <PageStateWrapper
    :loading="loading"
    :not-found="isNotFound"
  >
    <VRow v-if="item">
      <Actions
        :active-action="activeAction"
        :can-cancel-waitlist="canCancelWaitlist"
        :can-convert-waitlist="canConvertWaitlist"
        :can-notify-waitlist="canNotifyWaitlist"
        :can-show-edit="canShowEdit"
        :has-active-action="hasActiveAction"
        :item="item"
        @cancel="cancel"
        @convert="showConvertDialog = true"
        @notify="notify"
      />

      <Details :item="item" />
    </VRow>

    <ConvertDialog
      v-if="canConvertWaitlist && showConvertDialog && item"
      v-model="showConvertDialog"
      :item="item"
      @saved="onConverted"
      @submitting="onConvertSubmitting"
    />
  </PageStateWrapper>
</template>
