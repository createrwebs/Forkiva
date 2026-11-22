<script lang="ts" setup>
import type { ServiceField, ServiceItem } from '@/modules/service/contracts/Service.ts'
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useForm } from '@/modules/core/composables/form.ts'
import { useService } from '@/modules/service/composables/service.ts'

const props = defineProps<{
  modelValue: boolean
  service: ServiceItem | null
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'saved', value: ServiceItem): void
}>()

const { t } = useI18n()
const { updateServiceSettings } = useService()
const form = useForm<Record<string, any>>({})

const dialogModel = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})

const selectedService = computed(() => props.service)
const settingsEnabled = computed(() => {
  const hasEnabledField = selectedService.value?.settings.some((field) => field.key === 'enabled')

  return hasEnabledField ? Boolean(fieldValue('enabled')) : true
})

watch(
  () => props.service,
  (service) => {
    if (!service) {
      resetForm({})
      return
    }

    resetForm(service.values || {})
  },
  { immediate: true },
)

function resetForm(values: Record<string, any>) {
  for (const key of Object.keys(form.state)) delete form.state[key]
  Object.assign(form.state, JSON.parse(JSON.stringify(values)))
}

function close() {
  emit('update:modelValue', false)
}

function fieldValue(path: string): any {
  return path.split('.').reduce((value: any, key: string) => value?.[key], form.state)
}

function setFieldValue(path: string, value: any) {
  const parts = path.split('.')
  const last = parts.pop()
  if (!last) {
    return
  }

  let target = form.state
  for (const part of parts) {
    if (!target[part] || typeof target[part] !== 'object') {
      target[part] = {}
    }
    target = target[part]
  }

  target[last] = value
}

function conditionalFields(field: ServiceField): ServiceField[] {
  if (!settingsEnabled.value) {
    return []
  }

  const selected = fieldValue(field.key)
  if (!selected || !field.conditional_fields) {
    return []
  }

  return field.conditional_fields[String(selected)] || []
}

function errorFor(path: string): string {
  return form.errors.value?.[path] || ''
}

function inputType(field: ServiceField): string {
  if (field.type === 'integer') {
    return 'number'
  }

  return field.is_sensitive ? 'password' : 'text'
}

function fieldDisabled(field: ServiceField): boolean {
  return !settingsEnabled.value && field.key !== 'enabled'
}

function toggleActivation() {
  if (!activationField.value) {
    return
  }

  setFieldValue(activationField.value.key, !settingsEnabled.value)
}

const activationField = computed(() =>
  selectedService.value?.settings.find((field) => field.key === 'enabled'),
)
const settingsFields = computed(
  () => selectedService.value?.settings.filter((field) => field.key !== 'enabled') || [],
)

async function submit() {
  const service = selectedService.value
  if (!service || form.loading.value) {
    return
  }

  const response = await form.submit(
    () => updateServiceSettings(service.category, service.key, form.state),
    true,
  )

  if (response && typeof response !== 'boolean') {
    emit('saved', response.data.body)
    close()
  }
}
</script>

<template>
  <VDialog
    v-model="dialogModel"
    max-width="760"
    persistent
    scrollable
    class="service-settings-dialog"
  >
    <VForm
      v-if="selectedService"
      @submit.prevent="submit"
    >
      <VCard class="service-settings-dialog__card">
        <VCardTitle class="service-settings-dialog__header">
          <VAvatar
            color="primary"
            rounded="lg"
            size="40"
            variant="tonal"
          >
            <VIcon
              :icon="selectedService.icon || 'tabler-plug-connected'"
              size="20"
            />
          </VAvatar>
          <div class="service-settings-dialog__header-copy">
            <div class="service-settings-dialog__title">
              {{ selectedService.name }}
            </div>
            <div
              v-if="selectedService.description"
              class="service-settings-dialog__description"
            >
              {{ selectedService.description }}
            </div>
          </div>
        </VCardTitle>

        <VCardText class="service-settings-dialog__body">
          <VAlert
            v-if="form.errors.value.general"
            class="mb-4"
            color="error"
            variant="tonal"
          >
            {{ form.errors.value.general }}
          </VAlert>

          <div
            v-if="activationField"
            class="service-settings-dialog__activation"
            role="button"
            tabindex="0"
            @click="toggleActivation"
            @keydown.enter.prevent="toggleActivation"
            @keydown.space.prevent="toggleActivation"
          >
            <div class="service-settings-dialog__activation-copy">
              <div class="service-settings-dialog__activation-title">
                {{ activationField.label }}
              </div>
              <div class="service-settings-dialog__activation-status">
                {{ settingsEnabled ? t('admin::admin.active') : t('admin::admin.inactive') }}
              </div>
            </div>

            <div
              class="service-settings-dialog__activation-switch"
              @click.stop
            >
              <VSwitch
                inset
                hide-details
                color="primary"
                :error="!!errorFor(activationField.key)"
                :error-messages="errorFor(activationField.key)"
                :model-value="fieldValue(activationField.key)"
                @update:model-value="setFieldValue(activationField.key, $event)"
              />
            </div>
          </div>

          <VRow class="service-settings-dialog__grid">
            <template
              v-for="field in settingsFields"
              :key="field.key"
            >
              <VCol
                cols="12"
                :md="field.type === 'textarea' ? 12 : 6"
              >
                <VSwitch
                  v-if="field.type === 'boolean'"
                  color="primary"
                  :disabled="fieldDisabled(field)"
                  :error="!!errorFor(field.key)"
                  :error-messages="errorFor(field.key)"
                  :label="field.label"
                  :model-value="fieldValue(field.key)"
                  @update:model-value="setFieldValue(field.key, $event)"
                />

                <VSelect
                  v-else-if="field.type === 'dropdown'"
                  :disabled="fieldDisabled(field)"
                  :error="!!errorFor(field.key)"
                  :error-messages="errorFor(field.key)"
                  item-title="name"
                  item-value="id"
                  :items="field.options || []"
                  :label="field.label"
                  :model-value="fieldValue(field.key)"
                  @update:model-value="setFieldValue(field.key, $event)"
                />

                <VTextarea
                  v-else-if="field.type === 'textarea'"
                  auto-grow
                  :disabled="fieldDisabled(field)"
                  :error="!!errorFor(field.key)"
                  :error-messages="errorFor(field.key)"
                  :label="field.label"
                  :model-value="fieldValue(field.key)"
                  rows="5"
                  @update:model-value="setFieldValue(field.key, $event)"
                />

                <VTextField
                  v-else
                  :disabled="fieldDisabled(field)"
                  :error="!!errorFor(field.key)"
                  :error-messages="errorFor(field.key)"
                  :label="field.label"
                  :model-value="fieldValue(field.key)"
                  :type="inputType(field)"
                  @update:model-value="
                    setFieldValue(field.key, field.type === 'integer' ? Number($event) : $event)
                  "
                />
              </VCol>

              <template
                v-for="conditionalField in conditionalFields(field)"
                :key="`${field.key}-${conditionalField.key}`"
              >
                <VCol
                  cols="12"
                  :md="conditionalField.type === 'textarea' ? 12 : 6"
                >
                  <VSwitch
                    v-if="conditionalField.type === 'boolean'"
                    color="primary"
                    :error="!!errorFor(`${fieldValue(field.key)}.${conditionalField.key}`)"
                    :error-messages="errorFor(`${fieldValue(field.key)}.${conditionalField.key}`)"
                    :label="conditionalField.label"
                    :model-value="fieldValue(`${fieldValue(field.key)}.${conditionalField.key}`)"
                    @update:model-value="
                      setFieldValue(`${fieldValue(field.key)}.${conditionalField.key}`, $event)
                    "
                  />

                  <VSelect
                    v-else-if="conditionalField.type === 'dropdown'"
                    :error="!!errorFor(`${fieldValue(field.key)}.${conditionalField.key}`)"
                    :error-messages="errorFor(`${fieldValue(field.key)}.${conditionalField.key}`)"
                    item-title="name"
                    item-value="id"
                    :items="conditionalField.options || []"
                    :label="conditionalField.label"
                    :model-value="fieldValue(`${fieldValue(field.key)}.${conditionalField.key}`)"
                    @update:model-value="
                      setFieldValue(`${fieldValue(field.key)}.${conditionalField.key}`, $event)
                    "
                  />

                  <VTextarea
                    v-else-if="conditionalField.type === 'textarea'"
                    auto-grow
                    :error="!!errorFor(`${fieldValue(field.key)}.${conditionalField.key}`)"
                    :error-messages="errorFor(`${fieldValue(field.key)}.${conditionalField.key}`)"
                    :label="conditionalField.label"
                    :model-value="fieldValue(`${fieldValue(field.key)}.${conditionalField.key}`)"
                    rows="5"
                    @update:model-value="
                      setFieldValue(`${fieldValue(field.key)}.${conditionalField.key}`, $event)
                    "
                  />

                  <VTextField
                    v-else
                    :error="!!errorFor(`${fieldValue(field.key)}.${conditionalField.key}`)"
                    :error-messages="errorFor(`${fieldValue(field.key)}.${conditionalField.key}`)"
                    :label="conditionalField.label"
                    :model-value="fieldValue(`${fieldValue(field.key)}.${conditionalField.key}`)"
                    :type="inputType(conditionalField)"
                    @update:model-value="
                      setFieldValue(
                        `${fieldValue(field.key)}.${conditionalField.key}`,
                        conditionalField.type === 'integer' ? Number($event) : $event,
                      )
                    "
                  />
                </VCol>
              </template>
            </template>
          </VRow>
        </VCardText>

        <div class="service-settings-dialog__footer">
          <div class="service-settings-dialog__footer-actions">
            <VBtn
              color="default"
              variant="text"
              :disabled="form.loading.value"
              @click="close"
            >
              {{ t('admin::admin.buttons.cancel') }}
            </VBtn>
            <VBtn
              color="primary"
              rounded="lg"
              :disabled="form.loading.value"
              :loading="form.loading.value"
              @click="submit"
            >
              <VIcon
                icon="tabler-device-floppy"
                start
              />
              {{ t('admin::admin.buttons.save') }}
            </VBtn>
          </div>
        </div>
      </VCard>
    </VForm>
  </VDialog>
</template>

<style lang="scss" scoped>
.service-settings-dialog__card {
  border-radius: 18px;
}

.service-settings-dialog__header {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 22px 24px 18px;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}

.service-settings-dialog__header-copy {
  min-inline-size: 0;
}

.service-settings-dialog__title {
  color: rgb(var(--v-theme-on-surface));
  font-size: 1.06rem;
  font-weight: 800;
  line-height: 1.25;
}

.service-settings-dialog__description {
  margin-block-start: 4px;
  color: rgba(var(--v-theme-on-surface), 0.66);
  font-size: 0.9rem;
  line-height: 1.45;
}

.service-settings-dialog__body {
  padding: 24px;
}

.service-settings-dialog__activation {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 0 16px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  border-width: 0 0 1px;
  cursor: pointer;
  margin-block-end: 16px;
}

.service-settings-dialog__activation-copy {
  display: grid;
  gap: 2px;
  min-inline-size: 0;
}

.service-settings-dialog__activation-title {
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.96rem;
  font-weight: 760;
  line-height: 1.25;
}

.service-settings-dialog__activation-status {
  color: rgba(var(--v-theme-on-surface), 0.56);
  font-size: 0.8rem;
  line-height: 1.3;
}

.service-settings-dialog__activation-switch {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.service-settings-dialog__grid {
  margin-block: 0;
}

.service-settings-dialog__footer {
  display: flex;
  justify-content: flex-end;
  padding: 16px 24px 22px;
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  background: rgba(var(--v-theme-on-surface), 0.015);
}

.service-settings-dialog__footer-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

@media (max-width: 767px) {
  .service-settings-dialog__header,
  .service-settings-dialog__body,
  .service-settings-dialog__footer {
    padding-inline: 18px;
  }

  .service-settings-dialog__footer {
    align-items: stretch;
    flex-direction: column;
  }

  .service-settings-dialog__activation-switch {
    width: 100%;
    justify-content: flex-start;
  }

  .service-settings-dialog__footer-actions {
    inline-size: 100%;
    justify-content: space-between;
  }
}
</style>
