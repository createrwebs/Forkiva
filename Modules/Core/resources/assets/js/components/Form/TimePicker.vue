<script lang="ts" setup>
import { format, parse } from 'date-fns'

const props = withDefaults(
  defineProps<{
    modelValue?: string | null
    label?: string
    ariaLabel?: string
    density?: 'default' | 'comfortable' | 'compact'
    hideDetails?: boolean | 'auto'
    error?: boolean
    clearable?: boolean
    disabled?: boolean
    errorMessages?: string
    min?: string
    max?: string
    use24h?: boolean
    required?: boolean
  }>(),
  {
    clearable: false,
  },
)

const emit = defineEmits(['update:modelValue'])

const menu = ref(false)

const internalValue = ref<string | null>(null)

function normalizeToInternalValue(value?: string | null): string | null {
  if (!value) {
    return null
  }

  const rawValue = String(value).trim()

  if (/^\d{2}:\d{2}$/.test(rawValue)) {
    return rawValue
  }

  const parsedDate = parse(rawValue, 'hh:mm a', new Date())

  if (Number.isNaN(parsedDate.getTime())) {
    return null
  }

  return format(parsedDate, 'HH:mm')
}

function updateValue(val: string | null) {
  if (!val) {
    // user cleared the picker
    emit('update:modelValue', '')
    internalValue.value = null
    menu.value = false
    return
  }

  internalValue.value = val

  // val from VTimePicker is usually 'HH:mm'
  const [hStr, mStr] = val.split(':')
  const hours = Number(hStr)
  const minutes = Number(mStr)

  const date = new Date()
  date.setHours(hours, minutes, 0, 0)

  const formatted = props.use24h ? format(date, 'HH:mm') : format(date, 'hh:mm a')

  emit('update:modelValue', formatted)
  menu.value = false
}

function clearValue() {
  if (props.disabled) {
    return
  }

  internalValue.value = null
  emit('update:modelValue', '')
}

watch(
  () => props.modelValue,
  (value) => {
    internalValue.value = normalizeToInternalValue(value)
  },
  { immediate: true },
)
</script>

<template>
  <VMenu
    v-model="menu"
    :close-on-content-click="false"
    :disabled="props.disabled"
    offset-y
    transition="scale-transition"
  >
    <template #activator="{ props: menuActivatorProps }">
      <VTextField
        :aria-label="props.ariaLabel || props.label"
        :density="props.density"
        :hide-details="props.hideDetails"
        :clearable="clearable"
        :disabled="props.disabled"
        :error="props.error"
        :error-messages="props.errorMessages"
        :model-value="props.modelValue"
        prepend-inner-icon="tabler-clock"
        readonly
        v-bind="menuActivatorProps"
        @click:clear="clearValue"
      >
        <template
          v-if="props.label || props.required"
          #label
        >
          {{ props.label }}&nbsp;
          <span
            v-if="props.required"
            class="text-error"
          >
            *
          </span>
        </template>
      </VTextField>
    </template>

    <VTimePicker
      v-model="internalValue"
      :disabled="props.disabled"
      :format="props.use24h ? '24hr' : 'ampm'"
      :max="props.max"
      :min="props.min"
      @update:model-value="updateValue"
    />
  </VMenu>
</template>
