<script lang="ts" setup>
import { computed, ref } from 'vue'

const props = withDefaults(
  defineProps<{
    accept?: string | string[] | null
    actionLabel?: string
    disabled?: boolean
    error?: boolean
    errorMessages?: string | string[] | null
    hint?: string
    title?: string
  }>(),
  {
    accept: null,
    actionLabel: 'Choose file',
    disabled: false,
    error: false,
    errorMessages: null,
    hint: '',
    title: 'Upload file',
  },
)

const model = defineModel<File | null>({ default: null })
const inputRef = ref<HTMLInputElement | null>(null)
const isDragging = ref(false)

const acceptAttr = computed(() => {
  if (!props.accept) return undefined

  return Array.isArray(props.accept) ? props.accept.join(',') : props.accept
})
const normalizedErrors = computed(() => {
  if (Array.isArray(props.errorMessages)) return props.errorMessages
  if (props.errorMessages) return [props.errorMessages]

  return []
})
const fileExtension = computed(() => {
  const fileName = model.value?.name || ''
  const extension = fileName.includes('.') ? fileName.split('.').pop() : ''

  return extension ? extension.toUpperCase() : 'FILE'
})
const fileSize = computed(() => {
  const bytes = model.value?.size
  if (!Number.isFinite(bytes)) return '-'
  if ((bytes as number) < 1024) return `${bytes} B`
  if ((bytes as number) < 1024 * 1024) return `${((bytes as number) / 1024).toFixed(1)} KB`

  return `${((bytes as number) / (1024 * 1024)).toFixed(1)} MB`
})

function openPicker() {
  if (props.disabled) return

  inputRef.value?.click()
}

function applyFile(file: File | null) {
  if (props.disabled || !file) return

  model.value = file
}

function onFileChange(event: Event) {
  const target = event.target as HTMLInputElement | null

  applyFile(target?.files?.[0] || null)
  if (target) target.value = ''
}

function onDragOver(event: DragEvent) {
  if (props.disabled) return

  event.preventDefault()
  isDragging.value = true
}

function onDragLeave() {
  isDragging.value = false
}

function onDrop(event: DragEvent) {
  if (props.disabled) return

  event.preventDefault()
  isDragging.value = false
  applyFile(event.dataTransfer?.files?.[0] || null)
}

function clearFile() {
  if (props.disabled) return

  model.value = null
  if (inputRef.value) inputRef.value.value = ''
}
</script>

<template>
  <div>
    <div
      class="file-picker"
      :class="{
        'file-picker--active': isDragging,
        'file-picker--disabled': disabled,
        'file-picker--error': error || normalizedErrors.length > 0,
        'file-picker--selected': !!model,
      }"
      role="button"
      tabindex="0"
      @click="openPicker"
      @dragleave="onDragLeave"
      @dragover="onDragOver"
      @drop="onDrop"
      @keydown.enter.prevent="openPicker"
      @keydown.space.prevent="openPicker"
    >
      <input
        ref="inputRef"
        :accept="acceptAttr"
        class="d-none"
        :disabled="disabled"
        type="file"
        @change="onFileChange"
      />

      <div class="file-picker__drop">
        <div class="file-picker__icon">
          <VIcon
            icon="tabler-cloud-upload"
            size="30"
          />
        </div>

        <div class="file-picker__title">
          {{ title }}
        </div>

        <div class="file-picker__hint">
          {{ hint }}
        </div>

        <VBtn
          class="file-picker__action"
          color="primary"
          size="small"
          variant="tonal"
        >
          {{ actionLabel }}
        </VBtn>
      </div>

      <div
        v-if="model"
        class="file-picker__selected"
      >
        <div class="file-picker__file-mark">
          {{ fileExtension }}
        </div>

        <div class="file-picker__file-body">
          <div class="file-picker__file-name">
            {{ model.name }}
          </div>
          <div class="file-picker__file-size">
            {{ fileSize }}
          </div>
        </div>

        <VBtn
          color="error"
          icon
          size="small"
          variant="text"
          @click.stop="clearFile"
        >
          <VIcon
            icon="tabler-x"
            size="18"
          />
        </VBtn>
      </div>
    </div>

    <p
      v-for="(message, index) in normalizedErrors"
      :key="index"
      class="file-picker__error-message"
    >
      {{ message }}
    </p>
  </div>
</template>

<style lang="scss" scoped>
.file-picker {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-height: 210px;
  padding: 12px;
  overflow: hidden;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  border-radius: 8px;
  background: rgb(var(--v-theme-surface));
  cursor: pointer;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease;
}

.file-picker:hover,
.file-picker--active {
  border-color: rgba(var(--v-theme-primary), 0.5);
  box-shadow: 0 8px 20px rgba(15, 23, 42, 0.07);
  transform: translateY(-1px);
}

.file-picker--error {
  border-color: rgba(var(--v-theme-error), 0.78);
}

.file-picker--disabled {
  cursor: not-allowed;
  opacity: 0.65;
}

.file-picker__drop {
  display: flex;
  flex: 1;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 150px;
  padding: 18px 14px;
  border: 1px dashed rgba(var(--v-theme-primary), 0.38);
  border-radius: 8px;
  background: linear-gradient(
    180deg,
    rgba(var(--v-theme-primary), 0.045),
    rgba(var(--v-theme-primary), 0.015)
  );
  text-align: center;
}

.file-picker__icon {
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  margin-bottom: 12px;
  border-radius: 8px;
  background: rgba(var(--v-theme-primary), 0.1);
  color: rgb(var(--v-theme-primary));
}

.file-picker__title {
  color: rgba(var(--v-theme-on-surface), 0.9);
  font-size: 0.92rem;
  font-weight: 700;
  line-height: 1.35;
}

.file-picker__hint {
  max-width: 240px;
  margin-top: 6px;
  color: rgba(var(--v-theme-on-surface), 0.58);
  font-size: 0.78rem;
  line-height: 1.45;
}

.file-picker__action {
  margin-top: 14px;
}

.file-picker__selected {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px;
  border: 1px solid rgba(var(--v-theme-primary), 0.16);
  border-radius: 8px;
  background: rgba(var(--v-theme-primary), 0.045);
}

.file-picker__file-mark {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  flex: 0 0 44px;
  border-radius: 8px;
  background: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0;
}

.file-picker__file-body {
  min-width: 0;
  flex: 1;
}

.file-picker__file-name {
  overflow: hidden;
  color: rgba(var(--v-theme-on-surface), 0.9);
  font-size: 0.84rem;
  font-weight: 700;
  line-height: 1.35;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.file-picker__file-size {
  margin-top: 2px;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.74rem;
}

.file-picker__error-message {
  margin: 6px 0 0;
  color: rgb(var(--v-theme-error));
  font-size: 0.75rem;
}
</style>
