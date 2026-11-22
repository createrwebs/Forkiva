<script lang="ts" setup>
import type {
  KitchenSettings,
  KitchenSettingsMeta,
  KitchenSettingsResponse,
} from '@/modules/setting/contracts/Settings.ts'
import { useI18n } from 'vue-i18n'
import { useForm } from '@/modules/core/composables/form.ts'
import { useSetting } from '@/modules/setting/composables/setting.ts'

onMounted(async () => {
  const response: KitchenSettingsResponse | false = await getSettings('kitchen')
  if (response !== false) {
    meta.value = response.meta
    Object.assign(form.state, response.settings)
  }
})

const { getSettings, store, update } = useSetting()
const { t } = useI18n()

const meta = ref<KitchenSettingsMeta>({
  modes: [],
  sounds: [],
})

const form = useForm<KitchenSettings>({
  auto_refresh_enabled: false,
  auto_refresh_mode: null,
  auto_refresh_interval: null,
  auto_refresh_pause_on_idle: false,
  auto_refresh_idle_timeout: null,
  kitchen_show_served_orders_enabled: true,
  kitchen_show_served_orders_minutes: 5,
  kitchen_sound_enabled: true,
  kitchen_new_order_sound: 'chime',
  kitchen_order_updated_sound: 'double_beep',
  kitchen_sound_volume: 70,
})
const kitchenSoundVolume = computed({
  get: () => form.state.kitchen_sound_volume ?? 0,
  set: (value) => {
    form.state.kitchen_sound_volume = Number(value)
  },
})

function submit() {
  form.submit(() => update('kitchen', form.state))
}

watch(
  () => form.state.auto_refresh_enabled,
  (newValue) => {
    if (!newValue) {
      form.state.auto_refresh_mode = null
      form.state.auto_refresh_interval = null
      form.state.auto_refresh_pause_on_idle = false
      form.state.auto_refresh_idle_timeout = null
    }
  },
)

watch(
  () => form.state.kitchen_show_served_orders_enabled,
  (newValue) => {
    if (!newValue) {
      form.state.kitchen_show_served_orders_minutes = null

      return
    }

    if (!form.state.kitchen_show_served_orders_minutes) {
      form.state.kitchen_show_served_orders_minutes = 5
    }
  },
)

watch(
  () => form.state.kitchen_sound_enabled,
  (newValue) => {
    if (!newValue) {
      form.state.kitchen_new_order_sound = null
      form.state.kitchen_order_updated_sound = null
      form.state.kitchen_sound_volume = null

      return
    }

    form.state.kitchen_new_order_sound ||= 'chime'
    form.state.kitchen_order_updated_sound ||= 'double_beep'
    form.state.kitchen_sound_volume ||= 70
  },
)
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
              {{ t('setting::settings.auto_refresh_settings') }}
            </div>
          </VCol>

          <VCol cols="12">
            <VCheckbox
              v-model="form.state.auto_refresh_enabled"
              :label="t('setting::attributes.settings.auto_refresh_enabled')"
            />
          </VCol>

          <VCol cols="12">
            <VSelect
              v-if="form.state.auto_refresh_enabled"
              v-model="form.state.auto_refresh_mode"
              :error="!!form.errors.value?.auto_refresh_mode"
              :error-messages="form.errors.value?.auto_refresh_mode"
              item-title="name"
              item-value="id"
              :items="meta.modes"
              :label="t('setting::attributes.settings.auto_refresh_mode')"
            />
          </VCol>

          <template
            v-if="
              form.state.auto_refresh_enabled && form.state.auto_refresh_mode === 'smart_polling'
            "
          >
            <VCol cols="12">
              <VTextField
                v-model="form.state.auto_refresh_interval"
                v-integer-en
                :error="!!form.errors.value?.auto_refresh_interval"
                :error-messages="form.errors.value?.auto_refresh_interval"
                :label="t('setting::attributes.settings.auto_refresh_interval')"
              />
            </VCol>

            <VCol
              cols="12"
              md="4"
            >
              <VCheckbox
                v-model="form.state.auto_refresh_pause_on_idle"
                :label="t('setting::attributes.settings.auto_refresh_pause_on_idle')"
              />
            </VCol>

            <VCol
              v-if="form.state.auto_refresh_pause_on_idle"
              cols="12"
            >
              <VTextField
                v-model="form.state.auto_refresh_idle_timeout"
                v-integer-en
                :error="!!form.errors.value?.auto_refresh_idle_timeout"
                :error-messages="form.errors.value?.auto_refresh_idle_timeout"
                :label="t('setting::attributes.settings.auto_refresh_idle_timeout')"
              />
            </VCol>
          </template>

          <VCol cols="12">
            <VDivider class="my-2" />
          </VCol>

          <VCol cols="12">
            <div class="text-subtitle-1 font-weight-medium mb-2">
              {{ t('setting::settings.served_orders_visibility_settings') }}
            </div>

            <div class="text-body-2 text-medium-emphasis">
              {{ t('setting::settings.served_orders_visibility_description') }}
            </div>
          </VCol>

          <VCol cols="12">
            <VCheckbox
              v-model="form.state.kitchen_show_served_orders_enabled"
              :hint="t('setting::settings.served_orders_visibility_toggle_hint')"
              :label="t('setting::attributes.settings.kitchen_show_served_orders_enabled')"
              persistent-hint
            />
          </VCol>

          <VCol
            v-if="form.state.kitchen_show_served_orders_enabled"
            cols="12"
          >
            <VTextField
              v-model="form.state.kitchen_show_served_orders_minutes"
              v-integer-en
              :error="!!form.errors.value?.kitchen_show_served_orders_minutes"
              :error-messages="form.errors.value?.kitchen_show_served_orders_minutes"
              :hint="t('setting::settings.served_orders_visibility_minutes_hint')"
              :label="t('setting::attributes.settings.kitchen_show_served_orders_minutes')"
              persistent-hint
            />
          </VCol>

          <VCol cols="12">
            <VDivider class="my-2" />
          </VCol>

          <VCol cols="12">
            <div class="text-subtitle-1 font-weight-medium mb-2">
              {{ t('setting::settings.kitchen_sound_settings') }}
            </div>

            <div class="text-body-2 text-medium-emphasis">
              {{ t('setting::settings.kitchen_sound_description') }}
            </div>
          </VCol>

          <VCol cols="12">
            <VCheckbox
              v-model="form.state.kitchen_sound_enabled"
              :hint="t('setting::settings.kitchen_sound_toggle_hint')"
              :label="t('setting::attributes.settings.kitchen_sound_enabled')"
              persistent-hint
            />
          </VCol>

          <template v-if="form.state.kitchen_sound_enabled">
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="form.state.kitchen_new_order_sound"
                :error="!!form.errors.value?.kitchen_new_order_sound"
                :error-messages="form.errors.value?.kitchen_new_order_sound"
                item-title="name"
                item-value="id"
                :items="meta.sounds"
                :label="t('setting::attributes.settings.kitchen_new_order_sound')"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="form.state.kitchen_order_updated_sound"
                :error="!!form.errors.value?.kitchen_order_updated_sound"
                :error-messages="form.errors.value?.kitchen_order_updated_sound"
                item-title="name"
                item-value="id"
                :items="meta.sounds"
                :label="t('setting::attributes.settings.kitchen_order_updated_sound')"
              />
            </VCol>

            <VCol cols="12">
              <div class="d-flex align-center justify-space-between mb-2">
                <VLabel class="text-body-2">
                  {{ t('setting::attributes.settings.kitchen_sound_volume') }}
                </VLabel>

                <VChip
                  color="primary"
                  size="small"
                  variant="tonal"
                >
                  {{ kitchenSoundVolume }}%
                </VChip>
              </div>

              <VSlider
                v-model="kitchenSoundVolume"
                color="primary"
                :error="!!form.errors.value?.kitchen_sound_volume"
                :error-messages="form.errors.value?.kitchen_sound_volume"
                :max="100"
                :messages="t('setting::settings.kitchen_sound_volume_hint')"
                :min="0"
                :step="5"
                thumb-label="always"
                track-color="surface-variant"
              />
            </VCol>
          </template>
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
            icon="tabler-chef-hat"
            start
          />
          {{ t('admin::admin.buttons.save') }}
        </VBtn>
      </VCol>
    </VRow>
  </VForm>
</template>
