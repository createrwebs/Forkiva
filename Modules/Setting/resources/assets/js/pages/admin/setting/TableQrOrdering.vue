<script lang="ts" setup>
import type {
  TableQrOrderingSettings,
  TableQrOrderingSettingsMeta,
  TableQrOrderingSettingsResponse,
} from '@/modules/setting/contracts/Settings.ts'
import { useI18n } from 'vue-i18n'
import { useForm } from '@/modules/core/composables/form.ts'
import { useSetting } from '@/modules/setting/composables/setting.ts'

onMounted(async () => {
  const response: TableQrOrderingSettingsResponse | false = await getSettings('table_qr_ordering')
  if (response !== false) {
    meta.value = response.meta
    Object.assign(form.state, response.settings)
  }
})

const { getSettings, store, update } = useSetting()
const { t } = useI18n()

const meta = ref<TableQrOrderingSettingsMeta>({
  table_qr_register_session_strategies: [],
})

const form = useForm<TableQrOrderingSettings>({
  table_qr_ordering_enabled: false,
  table_qr_auto_send_to_kitchen: true,
  table_qr_require_guest_count: false,
  table_qr_allow_order_notes: true,
  table_qr_allow_discount_code: false,
  table_qr_register_session_strategy: 'latest_opened',
  table_qr_cart_expires_minutes: 120,
})

function submit() {
  form.submit(() => update('table_qr_ordering', form.state))
}
</script>

<template>
  <PageLoader :loading="store.loading" />

  <VForm
    v-if="!store.loading"
    @submit.prevent="submit"
  >
    <VRow>
      <VCol
        cols="12"
        md="8"
      >
        <VRow>
          <VCol cols="12">
            <div class="text-subtitle-1 font-weight-medium mb-2">
              {{ t('setting::settings.table_qr_ordering_settings') }}
            </div>

            <div class="text-body-2 text-medium-emphasis">
              {{ t('setting::settings.table_qr_ordering_description') }}
            </div>
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VSwitch
              v-model="form.state.table_qr_ordering_enabled"
              color="primary"
              :error="!!form.errors.value?.table_qr_ordering_enabled"
              :error-messages="form.errors.value?.table_qr_ordering_enabled"
              hide-details="auto"
              :label="t('setting::attributes.settings.table_qr_ordering_enabled')"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VSwitch
              v-model="form.state.table_qr_auto_send_to_kitchen"
              color="primary"
              :error="!!form.errors.value?.table_qr_auto_send_to_kitchen"
              :error-messages="form.errors.value?.table_qr_auto_send_to_kitchen"
              hide-details="auto"
              :label="t('setting::attributes.settings.table_qr_auto_send_to_kitchen')"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VSwitch
              v-model="form.state.table_qr_require_guest_count"
              color="primary"
              :error="!!form.errors.value?.table_qr_require_guest_count"
              :error-messages="form.errors.value?.table_qr_require_guest_count"
              hide-details="auto"
              :label="t('setting::attributes.settings.table_qr_require_guest_count')"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VSwitch
              v-model="form.state.table_qr_allow_order_notes"
              color="primary"
              :error="!!form.errors.value?.table_qr_allow_order_notes"
              :error-messages="form.errors.value?.table_qr_allow_order_notes"
              hide-details="auto"
              :label="t('setting::attributes.settings.table_qr_allow_order_notes')"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VSwitch
              v-model="form.state.table_qr_allow_discount_code"
              color="primary"
              :error="!!form.errors.value?.table_qr_allow_discount_code"
              :error-messages="form.errors.value?.table_qr_allow_discount_code"
              hide-details="auto"
              :label="t('setting::attributes.settings.table_qr_allow_discount_code')"
            />
          </VCol>

          <VCol cols="12">
            <VSelect
              v-model="form.state.table_qr_register_session_strategy"
              :error="!!form.errors.value?.table_qr_register_session_strategy"
              :error-messages="form.errors.value?.table_qr_register_session_strategy"
              item-title="name"
              item-value="id"
              :items="meta.table_qr_register_session_strategies"
              :label="t('setting::attributes.settings.table_qr_register_session_strategy')"
            />
          </VCol>

          <VCol cols="12">
            <VTextField
              v-model="form.state.table_qr_cart_expires_minutes"
              v-integer-en
              :error="!!form.errors.value?.table_qr_cart_expires_minutes"
              :error-messages="form.errors.value?.table_qr_cart_expires_minutes"
              :hint="t('setting::settings.table_qr_cart_expiry_hint')"
              :label="t('setting::attributes.settings.table_qr_cart_expires_minutes')"
              persistent-hint
            />
          </VCol>
        </VRow>
      </VCol>
    </VRow>

    <VRow>
      <VCol cols="12">
        <VBtn
          :disabled="form.loading.value"
          :loading="form.loading.value"
          type="submit"
        >
          <VIcon
            icon="tabler-qrcode"
            start
          />
          {{ t('admin::admin.buttons.save') }}
        </VBtn>
      </VCol>
    </VRow>
  </VForm>
</template>
