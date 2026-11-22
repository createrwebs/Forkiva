<script lang="ts" setup>
import type {
  AppearanceSettingsMeta,
  AppearanceSettingsResponse,
} from '@/modules/setting/contracts/Settings.ts'
import type { ThemeMode } from '@/modules/core/contracts/AppState.ts'
import { useI18n } from 'vue-i18n'
import ColorPicker from '@/modules/core/components/Form/ColorPicker.vue'
import PageLoader from '@/modules/core/components/PageLoader.vue'
import { useForm } from '@/modules/core/composables/form.ts'
import { useSetting } from '@/modules/setting/composables/setting.ts'

const { getSettings, store, update } = useSetting()
const { t } = useI18n()
const defaultAppearance = {
  default_theme_mode: 'light' as ThemeMode,
  theme_primary_color: '#F57C00',
  theme_secondary_color: '#043A63',
  theme_success_color: '#2ecc71',
  theme_info_color: '#03C3EC',
  theme_warning_color: '#f1c40f',
  theme_error_color: '#e74c3c',
  theme_dark_primary_color: '#F6B456',
  theme_dark_secondary_color: '#3F6C92',
  theme_dark_success_color: '#58C39C',
  theme_dark_info_color: '#7EB9F0',
  theme_dark_warning_color: '#F2B24D',
  theme_dark_error_color: '#FF7B7B',
}

const meta = ref<AppearanceSettingsMeta>({
  theme_modes: [],
})

const form = useForm<{
  default_theme_mode: ThemeMode
  theme_primary_color: string
  theme_secondary_color: string
  theme_success_color: string
  theme_info_color: string
  theme_warning_color: string
  theme_error_color: string
  theme_dark_primary_color: string
  theme_dark_secondary_color: string
  theme_dark_success_color: string
  theme_dark_info_color: string
  theme_dark_warning_color: string
  theme_dark_error_color: string
}>(defaultAppearance)

onBeforeMount(async () => {
  const response: AppearanceSettingsResponse | false = await getSettings('appearance')
  if (response !== false) {
    form.state.default_theme_mode = response.settings.default_theme_mode
    form.state.theme_primary_color = response.settings.theme_primary_color
    form.state.theme_secondary_color = response.settings.theme_secondary_color
    form.state.theme_success_color = response.settings.theme_success_color
    form.state.theme_info_color = response.settings.theme_info_color
    form.state.theme_warning_color = response.settings.theme_warning_color
    form.state.theme_error_color = response.settings.theme_error_color
    form.state.theme_dark_primary_color =
      response.settings.theme_dark_primary_color || response.settings.theme_primary_color
    form.state.theme_dark_secondary_color =
      response.settings.theme_dark_secondary_color || response.settings.theme_secondary_color
    form.state.theme_dark_success_color =
      response.settings.theme_dark_success_color || response.settings.theme_success_color
    form.state.theme_dark_info_color =
      response.settings.theme_dark_info_color || response.settings.theme_info_color
    form.state.theme_dark_warning_color =
      response.settings.theme_dark_warning_color || response.settings.theme_warning_color
    form.state.theme_dark_error_color =
      response.settings.theme_dark_error_color || response.settings.theme_error_color
    meta.value = response.meta
  }
})

function submit() {
  form.submit(() => update('appearance', form.state))
}

function resetToDefaults() {
  form.state.default_theme_mode = defaultAppearance.default_theme_mode
  form.state.theme_primary_color = defaultAppearance.theme_primary_color
  form.state.theme_secondary_color = defaultAppearance.theme_secondary_color
  form.state.theme_success_color = defaultAppearance.theme_success_color
  form.state.theme_info_color = defaultAppearance.theme_info_color
  form.state.theme_warning_color = defaultAppearance.theme_warning_color
  form.state.theme_error_color = defaultAppearance.theme_error_color
  form.state.theme_dark_primary_color = defaultAppearance.theme_dark_primary_color
  form.state.theme_dark_secondary_color = defaultAppearance.theme_dark_secondary_color
  form.state.theme_dark_success_color = defaultAppearance.theme_dark_success_color
  form.state.theme_dark_info_color = defaultAppearance.theme_dark_info_color
  form.state.theme_dark_warning_color = defaultAppearance.theme_dark_warning_color
  form.state.theme_dark_error_color = defaultAppearance.theme_dark_error_color
}
</script>

<template>
  <PageLoader :loading="store.loading" />
  <VForm
    v-if="!store.loading"
    @submit.prevent="submit"
  >
    <VRow>
      <VCol cols="12">
        <VSelect
          v-model="form.state.default_theme_mode"
          :error="!!form.errors.value?.default_theme_mode"
          :error-messages="form.errors.value?.default_theme_mode"
          :items="meta.theme_modes"
          :label="t('setting::attributes.settings.default_theme_mode')"
          item-title="name"
          item-value="id"
        />
      </VCol>

      <VCol cols="12">
        <h3 class="text-h6 mb-1">
          {{ t('setting::attributes.settings.theme_light_colors') }}
        </h3>
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <ColorPicker
          v-model="form.state.theme_primary_color"
          :error="!!form.errors.value?.theme_primary_color"
          :error-messages="form.errors.value?.theme_primary_color"
          :label="t('setting::attributes.settings.theme_primary_color')"
        />
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <ColorPicker
          v-model="form.state.theme_secondary_color"
          :error="!!form.errors.value?.theme_secondary_color"
          :error-messages="form.errors.value?.theme_secondary_color"
          :label="t('setting::attributes.settings.theme_secondary_color')"
        />
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <ColorPicker
          v-model="form.state.theme_success_color"
          :error="!!form.errors.value?.theme_success_color"
          :error-messages="form.errors.value?.theme_success_color"
          :label="t('setting::attributes.settings.theme_success_color')"
        />
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <ColorPicker
          v-model="form.state.theme_info_color"
          :error="!!form.errors.value?.theme_info_color"
          :error-messages="form.errors.value?.theme_info_color"
          :label="t('setting::attributes.settings.theme_info_color')"
        />
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <ColorPicker
          v-model="form.state.theme_warning_color"
          :error="!!form.errors.value?.theme_warning_color"
          :error-messages="form.errors.value?.theme_warning_color"
          :label="t('setting::attributes.settings.theme_warning_color')"
        />
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <ColorPicker
          v-model="form.state.theme_error_color"
          :error="!!form.errors.value?.theme_error_color"
          :error-messages="form.errors.value?.theme_error_color"
          :label="t('setting::attributes.settings.theme_error_color')"
        />
      </VCol>

      <VCol cols="12">
        <VDivider class="my-2" />
        <h3 class="text-h6 mb-1">
          {{ t('setting::attributes.settings.theme_dark_colors') }}
        </h3>
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <ColorPicker
          v-model="form.state.theme_dark_primary_color"
          :error="!!form.errors.value?.theme_dark_primary_color"
          :error-messages="form.errors.value?.theme_dark_primary_color"
          :label="t('setting::attributes.settings.theme_dark_primary_color')"
        />
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <ColorPicker
          v-model="form.state.theme_dark_secondary_color"
          :error="!!form.errors.value?.theme_dark_secondary_color"
          :error-messages="form.errors.value?.theme_dark_secondary_color"
          :label="t('setting::attributes.settings.theme_dark_secondary_color')"
        />
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <ColorPicker
          v-model="form.state.theme_dark_success_color"
          :error="!!form.errors.value?.theme_dark_success_color"
          :error-messages="form.errors.value?.theme_dark_success_color"
          :label="t('setting::attributes.settings.theme_dark_success_color')"
        />
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <ColorPicker
          v-model="form.state.theme_dark_info_color"
          :error="!!form.errors.value?.theme_dark_info_color"
          :error-messages="form.errors.value?.theme_dark_info_color"
          :label="t('setting::attributes.settings.theme_dark_info_color')"
        />
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <ColorPicker
          v-model="form.state.theme_dark_warning_color"
          :error="!!form.errors.value?.theme_dark_warning_color"
          :error-messages="form.errors.value?.theme_dark_warning_color"
          :label="t('setting::attributes.settings.theme_dark_warning_color')"
        />
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <ColorPicker
          v-model="form.state.theme_dark_error_color"
          :error="!!form.errors.value?.theme_dark_error_color"
          :error-messages="form.errors.value?.theme_dark_error_color"
          :label="t('setting::attributes.settings.theme_dark_error_color')"
        />
      </VCol>
    </VRow>

    <VRow>
      <VCol
        cols="12"
        class="d-flex gap-3 flex-wrap"
      >
        <VBtn
          :disabled="form.loading.value"
          color="secondary"
          variant="outlined"
          @click="resetToDefaults"
        >
          <VIcon
            icon="tabler-refresh"
            start
          />
          {{ t('admin::admin.buttons.reset') }}
        </VBtn>
        <VBtn
          :disabled="form.loading.value"
          :loading="form.loading.value"
          type="submit"
        >
          <VIcon
            icon="tabler-palette"
            start
          />
          {{ t('admin::admin.buttons.save') }}
        </VBtn>
      </VCol>
    </VRow>
  </VForm>
</template>
