<script lang="ts" setup>
import type {
  PosSettings,
  PosSettingsMeta,
  PosSettingsResponse,
} from '@/modules/setting/contracts/Settings'
import { createTonePlayer } from '@/modules/core/audio/tonePlayer'
import { useForm } from '@/modules/core/composables/form'
import { POS_SOUND_DEFAULTS, PosSoundAction } from '@/modules/pos/enums/posSound'
import { useSetting } from '@/modules/setting/composables/setting'
import { useI18n } from 'vue-i18n'

const actions: Array<{ id: PosSoundAction; icon: string; color: string }> = [
  { id: PosSoundAction.Interaction, icon: 'tabler-hand-click', color: 'primary' },
  { id: PosSoundAction.ItemAdded, icon: 'tabler-shopping-cart-plus', color: 'success' },
  { id: PosSoundAction.ItemUpdated, icon: 'tabler-adjustments-horizontal', color: 'info' },
  { id: PosSoundAction.ItemRemoved, icon: 'tabler-trash', color: 'error' },
  { id: PosSoundAction.OrderSubmitted, icon: 'tabler-chef-hat', color: 'secondary' },
  { id: PosSoundAction.PaymentCompleted, icon: 'tabler-cash', color: 'success' },
  { id: PosSoundAction.Cancelled, icon: 'tabler-ban', color: 'warning' },
  { id: PosSoundAction.Error, icon: 'tabler-alert-triangle', color: 'error' },
]

const { t } = useI18n()
const { getSettings, store, update } = useSetting()
const player = createTonePlayer()
const meta = ref<PosSettingsMeta>({ sounds: [] })
const form = useForm<PosSettings>({
  pos_sound_enabled: true,
  pos_sound_volume: 65,
  ...Object.fromEntries(
    actions.flatMap(({ id }) => [
      [`pos_sound_${id}_enabled`, true],
      [`pos_sound_${id}_sound`, POS_SOUND_DEFAULTS[id]],
    ]),
  ),
} as PosSettings)

const volume = computed({
  get: () => Number(form.state.pos_sound_volume),
  set: (value) => (form.state.pos_sound_volume = Number(value)),
})

onMounted(async () => {
  const response: PosSettingsResponse | false = await getSettings('pos')
  if (response !== false) {
    meta.value = response.meta
    Object.assign(form.state, response.settings)
  }
})

onUnmounted(player.close)

function enabledKey(action: PosSoundAction): `pos_sound_${PosSoundAction}_enabled` {
  return `pos_sound_${action}_enabled`
}

function soundKey(action: PosSoundAction): `pos_sound_${PosSoundAction}_sound` {
  return `pos_sound_${action}_sound`
}

function preview(action: PosSoundAction) {
  player.play(form.state[soundKey(action)], volume.value)
}

function submit() {
  form.submit(() => update('pos', form.state))
}
</script>

<template>
  <PageLoader :loading="store.loading" />

  <VForm
    v-if="!store.loading"
    class="pos-sound-settings"
    @submit.prevent="submit"
  >
    <VCard
      class="pos-sound-master"
      elevation="0"
      variant="flat"
    >
      <VCardText class="pos-sound-master__body">
        <div class="pos-sound-master__intro">
          <span class="pos-sound-master__icon">
            <VIcon
              :icon="form.state.pos_sound_enabled ? 'tabler-volume' : 'tabler-volume-off'"
              size="30"
            />
          </span>
          <div class="pos-sound-master__content">
            <h2>{{ t('setting::settings.pos_sound_title') }}</h2>
            <p>{{ t('setting::settings.pos_sound_description') }}</p>
          </div>
        </div>

        <div class="pos-sound-master__controls">
          <div class="pos-sound-master__toggle-row">
            <div class="pos-sound-master__toggle-label">
              <VIcon
                :icon="form.state.pos_sound_enabled ? 'tabler-volume' : 'tabler-volume-off'"
                size="20"
              />
              <VLabel>{{ t('setting::attributes.settings.pos_sound_enabled') }}</VLabel>
            </div>
            <VSwitch
              v-model="form.state.pos_sound_enabled"
              :aria-label="t('setting::attributes.settings.pos_sound_enabled')"
              color="primary"
              hide-details
              inset
            />
          </div>

          <div class="pos-sound-volume">
            <div class="pos-sound-volume__label">
              <VLabel>{{ t('setting::attributes.settings.pos_sound_volume') }}</VLabel>
              <span class="pos-sound-volume__value">{{ volume }}%</span>
            </div>
            <VSlider
              v-model="volume"
              :disabled="!form.state.pos_sound_enabled"
              :error="!!form.errors.value?.pos_sound_volume"
              :error-messages="form.errors.value?.pos_sound_volume"
              :max="100"
              :min="0"
              :step="5"
              class="pos-sound-volume__slider"
              color="primary"
              thumb-label
            />
            <p class="text-body-2 text-medium-emphasis mb-0">
              {{ t('setting::settings.pos_sound_volume_hint') }}
            </p>
          </div>
        </div>
      </VCardText>
    </VCard>

    <div class="pos-sound-section-heading">
      <h3>{{ t('setting::settings.pos_sound_actions_title') }}</h3>
      <p>{{ t('setting::settings.pos_sound_actions_description') }}</p>
    </div>

    <div class="pos-sound-actions">
      <VCard
        v-for="action in actions"
        :key="action.id"
        :class="{ 'pos-sound-action--disabled': !form.state[enabledKey(action.id)] }"
        class="pos-sound-action"
        variant="outlined"
      >
        <VCardText>
          <div class="pos-sound-action__header">
            <span
              :class="`pos-sound-action__icon--${action.color}`"
              class="pos-sound-action__icon"
            >
              <VIcon
                :icon="action.icon"
                size="22"
              />
            </span>
            <div class="pos-sound-action__copy">
              <h4>{{ t(`setting::settings.pos_sound_actions.${action.id}.title`) }}</h4>
              <p>{{ t(`setting::settings.pos_sound_actions.${action.id}.description`) }}</p>
            </div>
            <VSwitch
              v-model="form.state[enabledKey(action.id)]"
              :aria-label="t(`setting::settings.pos_sound_actions.${action.id}.title`)"
              :disabled="!form.state.pos_sound_enabled"
              color="primary"
              hide-details
              inset
            />
          </div>

          <div class="pos-sound-action__controls">
            <VSelect
              v-model="form.state[soundKey(action.id)]"
              :disabled="!form.state.pos_sound_enabled || !form.state[enabledKey(action.id)]"
              :error="!!form.errors.value?.[soundKey(action.id)]"
              :error-messages="form.errors.value?.[soundKey(action.id)]"
              :items="meta.sounds"
              density="comfortable"
              hide-details="auto"
              item-title="name"
              item-value="id"
            />
            <VBtn
              :aria-label="`${t('setting::settings.pos_sound_preview')}: ${t(`setting::settings.pos_sound_actions.${action.id}.title`)}`"
              :disabled="!form.state.pos_sound_enabled || !form.state[enabledKey(action.id)]"
              color="primary"
              icon="tabler-player-play"
              variant="tonal"
              @click="preview(action.id)"
            />
          </div>
        </VCardText>
      </VCard>
    </div>

    <div class="pos-sound-save">
      <VBtn
        :disabled="form.loading.value"
        :loading="form.loading.value"
        color="primary"
        prepend-icon="tabler-device-floppy"
        type="submit"
      >
        {{ t('admin::admin.buttons.save') }}
      </VBtn>
    </div>
  </VForm>
</template>

<style lang="scss" scoped>
.pos-sound-settings {
  max-width: 980px;
}

.pos-sound-master {
  overflow: hidden;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 20px;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 16px 40px rgba(var(--v-theme-on-surface), 0.08);
}

.pos-sound-master__body {
  display: grid;
  grid-template-columns: minmax(280px, 0.82fr) minmax(0, 1.5fr);
  padding: 0 !important;
}

.pos-sound-action__header {
  display: flex;
  align-items: center;
  gap: 16px;
}

.pos-sound-master__intro {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: center;
  gap: 22px;
  overflow: hidden;
  min-height: 260px;
  padding: 34px;
  background: linear-gradient(145deg, rgb(var(--v-theme-primary)), #df6500);
  color: rgb(var(--v-theme-on-primary));
}

.pos-sound-master__intro::after {
  position: absolute;
  inset-block-end: -72px;
  inset-inline-end: -54px;
  width: 190px;
  height: 190px;
  border: 34px solid rgba(var(--v-theme-on-primary), 0.08);
  border-radius: 50%;
  content: '';
  pointer-events: none;
}

.pos-sound-section-heading h3,
.pos-sound-action__copy h4 {
  margin: 0;
  color: rgb(var(--v-theme-on-surface));
}

.pos-sound-master__intro h2 {
  margin: 0;
  color: inherit;
  font-size: 1.55rem;
  line-height: 1.2;
}

.pos-sound-section-heading p,
.pos-sound-action__copy p {
  margin: 4px 0 0;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}

.pos-sound-master__intro p {
  position: relative;
  z-index: 1;
  max-width: 310px;
  margin: 10px 0 0;
  color: rgba(var(--v-theme-on-primary), 0.82);
  font-size: 0.95rem;
  line-height: 1.65;
}

.pos-sound-action__copy {
  flex: 1;
}

.pos-sound-master__icon,
.pos-sound-action__icon {
  display: grid;
  flex: 0 0 auto;
  place-items: center;
  width: 48px;
  height: 48px;
  border-radius: 14px;
}

.pos-sound-master__icon {
  position: relative;
  z-index: 1;
  width: 58px;
  height: 58px;
  border: 1px solid rgba(var(--v-theme-on-primary), 0.18);
  background: rgba(var(--v-theme-on-primary), 0.14);
  color: rgb(var(--v-theme-on-primary));
  box-shadow: 0 10px 24px rgba(91, 40, 0, 0.16);
}

.pos-sound-master__controls {
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 30px 32px;
}

.pos-sound-master__toggle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding-bottom: 20px;
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}

.pos-sound-master__toggle-label {
  display: flex;
  align-items: center;
  gap: 10px;
  color: rgb(var(--v-theme-primary));
}

.pos-sound-master__toggle-label .v-label {
  color: rgb(var(--v-theme-on-surface));
  font-weight: 600;
  opacity: 1;
}

.pos-sound-action__icon {
  background: rgba(var(--pos-sound-action-color), 0.12);
  color: rgb(var(--pos-sound-action-color));
}

.pos-sound-action__icon--primary {
  --pos-sound-action-color: var(--v-theme-primary);
}

.pos-sound-action__icon--secondary {
  --pos-sound-action-color: var(--v-theme-secondary);
}

.pos-sound-action__icon--success {
  --pos-sound-action-color: var(--v-theme-success);
}

.pos-sound-action__icon--info {
  --pos-sound-action-color: var(--v-theme-info);
}

.pos-sound-action__icon--warning {
  --pos-sound-action-color: var(--v-theme-warning);
}

.pos-sound-action__icon--error {
  --pos-sound-action-color: var(--v-theme-error);
}

.pos-sound-volume {
  margin-top: 22px;
}

.pos-sound-volume__label {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 8px;
}

.pos-sound-volume__label .v-label {
  color: rgb(var(--v-theme-on-surface));
  font-weight: 600;
  opacity: 1;
}

.pos-sound-volume__value {
  min-width: 54px;
  font-weight: 700;
  color: rgb(var(--v-theme-primary));
  text-align: end;
}

.pos-sound-volume__slider {
  margin-inline: 2px;
}

.pos-sound-section-heading {
  margin: 28px 0 16px;
}

.pos-sound-actions {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.pos-sound-action {
  border-color: rgba(var(--v-theme-on-surface), 0.18);
  border-style: dashed;
  background: rgb(var(--v-theme-surface));
  transition:
    border-color 160ms ease,
    opacity 160ms ease;
}

.pos-sound-action:not(.pos-sound-action--disabled) {
  border-color: rgba(var(--v-theme-primary), 0.34);
}

.pos-sound-action--disabled {
  opacity: 0.68;
}

.pos-sound-action__copy h4 {
  font-size: 0.975rem;
}

.pos-sound-action__copy p {
  font-size: 0.825rem;
  line-height: 1.45;
}

.pos-sound-action__controls {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: start;
  gap: 10px;
  margin-top: 18px;
}

.pos-sound-save {
  margin-top: 24px;
}

@media (max-width: 700px) {
  .pos-sound-actions {
    grid-template-columns: 1fr;
  }

  .pos-sound-master__body {
    display: grid;
    grid-template-columns: 1fr;
  }

  .pos-sound-master__intro {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    min-height: 0;
    padding: 24px 22px;
  }

  .pos-sound-master__icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
  }

  .pos-sound-master__intro h2 {
    font-size: 1.15rem;
  }

  .pos-sound-master__intro p {
    font-size: 0.875rem;
    line-height: 1.55;
  }

  .pos-sound-master__controls {
    padding: 22px;
  }

  .pos-sound-volume {
    margin-top: 18px;
  }

  .pos-sound-action__header {
    align-items: flex-start;
  }

  .pos-sound-action__icon {
    width: 42px;
    height: 42px;
  }
}
</style>
