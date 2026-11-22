<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import DatePicker from '@/modules/core/components/Form/DatePicker.vue'
import PageStateWrapper from '@/modules/core/components/PageStateWrapper.vue'
import { useGiftCardBreakage } from '@/modules/giftcard/composables/giftCardBreakage.ts'
import GiftCardBreakageDetails from '@/modules/giftcard/pages/admin/giftCardBreakage/Partials/GiftCardBreakageDetails.vue'
import GiftCardBreakageHeader from '@/modules/giftcard/pages/admin/giftCardBreakage/Partials/GiftCardBreakageHeader.vue'
import {
  availableBreakageActions,
  giftCardActionGeneralError,
  useRetainedIdempotencyKey,
} from '@/modules/giftcard/utils/giftCardLifecycle.ts'

const { t } = useI18n()
const { can } = useAuth()
const route = useRoute()
const service = useGiftCardBreakage()
const idempotency = useRetainedIdempotencyKey()

const loading = ref(true)
const isNotFound = ref(false)
const actionLoading = ref(false)
const item = ref<any>(null)
const dialog = ref<'review' | 'reverse' | null>(null)
const errors = ref<Record<string, string[]>>({})
const generalError = ref('')
const actionForm = reactive({
  economic_date: new Date().toISOString().slice(0, 10),
  review_note: '',
  reason: '',
})

const availableActions = computed(() => availableBreakageActions(item.value?.status?.id || '', can))

function openDialog(action: 'review' | 'reverse') {
  errors.value = {}
  generalError.value = ''
  actionForm.review_note = ''
  actionForm.reason = ''
  dialog.value = action
}

function closeDialog() {
  if (!actionLoading.value) dialog.value = null
}

async function load() {
  loading.value = true
  const response = await service.getShowData(String(route.params.id))

  if (response.status === 200) item.value = response.data
  else if (response.status === 404) isNotFound.value = true

  loading.value = false
}

async function postBatch() {
  actionLoading.value = true
  const response = await service.post(item.value.id)
  actionLoading.value = false

  if (response.status >= 200 && response.status < 300) await load()
}

async function submitDialog() {
  actionLoading.value = true
  errors.value = {}
  generalError.value = ''
  const response =
    dialog.value === 'review'
      ? await service.review(item.value.id, {
          economic_date: actionForm.economic_date,
          review_note: actionForm.review_note,
        })
      : await service.reverse(item.value.id, {
          economic_date: actionForm.economic_date,
          reason: actionForm.reason,
          idempotency_key: idempotency.key.value,
        })
  actionLoading.value = false

  if (response.status >= 200 && response.status < 300) {
    idempotency.regenerateAfterSuccess()
    dialog.value = null
    await load()
  } else {
    errors.value = response.errors || {}
    generalError.value = giftCardActionGeneralError(
      errors.value,
      response.message,
      t('core::errors.an_unexpected_error_occurred'),
    )
  }
}

onBeforeMount(load)
</script>

<template>
  <PageStateWrapper
    :loading="loading"
    :not-found="isNotFound"
  >
    <template v-if="item">
      <GiftCardBreakageHeader
        :actions="availableActions"
        :item="item"
        :loading="actionLoading"
        @post="postBatch"
        @reverse="openDialog('reverse')"
        @review="openDialog('review')"
      />

      <GiftCardBreakageDetails :item="item" />
    </template>

    <VDialog
      max-width="680"
      :model-value="Boolean(dialog)"
      persistent
    >
      <VCard>
        <VCardTitle class="border-b">
          {{ dialog ? t(`giftcard::gift_card_breakage_batches.actions.${dialog}`) : '' }}
        </VCardTitle>

        <VCardText class="pt-6">
          <VAlert
            v-if="generalError"
            class="mb-4"
            closable
            type="error"
            variant="tonal"
            @click:close="generalError = ''"
          >
            {{ generalError }}
          </VAlert>

          <VRow>
            <VCol cols="12">
              <DatePicker
                v-model="actionForm.economic_date"
                :error-messages="errors.economic_date"
                :label="t('giftcard::gift_card_breakage_batches.table.economic_date')"
              />
            </VCol>

            <VCol cols="12">
              <VTextarea
                v-if="dialog === 'review'"
                v-model="actionForm.review_note"
                :error-messages="errors.review_note"
                :label="t('giftcard::gift_card_breakage_batches.review_note')"
                rows="3"
              />

              <VTextarea
                v-else
                v-model="actionForm.reason"
                :error-messages="errors.reason"
                :label="t('giftcard::gift_card_transactions.actions.reason')"
                rows="3"
              />
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="justify-end pa-4">
          <VBtn
            :disabled="actionLoading"
            variant="text"
            @click="closeDialog"
          >
            {{ t('admin::admin.buttons.cancel') }}
          </VBtn>

          <VBtn
            :color="dialog === 'reverse' ? 'warning' : 'primary'"
            :loading="actionLoading"
            @click="submitDialog"
          >
            {{
              dialog === 'reverse'
                ? t('giftcard::gift_card_breakage_batches.actions.reverse')
                : t('admin::admin.buttons.save')
            }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </PageStateWrapper>
</template>
