<script lang="ts" setup>
import { format } from 'date-fns'
import { ref, watch } from 'vue'

const props = defineProps<{
  modelValue?: string | null
  label?: string
  ariaLabel?: string
  placeholder?: string
  error?: boolean
  clearable?: boolean
  errorMessages?: string | string[]
  density?: 'default' | 'comfortable' | 'compact'
  hideDetails?: boolean | 'auto'
  min?: string
  max?: string
  required?: boolean
  disabled?: boolean
  loading?: boolean
}>()

const emit = defineEmits(['update:modelValue'])

const menu = ref(false)
const internalValue = ref(props.modelValue)

watch(
  () => props.modelValue,
  (val) => {
    internalValue.value = val
  },
)

function updateValue(val: string | null) {
  if (val) {
    const formatted = format(new Date(val), 'yyyy-MM-dd')
    emit('update:modelValue', formatted)
    menu.value = false
  }
}

function clearValue() {
  internalValue.value = null
  emit('update:modelValue', '')
}
</script>

<template>
  <VMenu
    v-model="menu"
    :close-on-content-click="false"
    offset-y
    transition="scale-transition"
  >
    <template #activator="{ props: menuActivatorProps }">
      <VTextField
        :aria-label="ariaLabel || label"
        :density="density"
        :disabled="disabled"
        :error="error"
        :error-messages="errorMessages"
        :loading="loading"
        :model-value="props.modelValue"
        :placeholder="placeholder"
        clearable
        prepend-inner-icon="tabler-calendar"
        readonly
        v-bind="menuActivatorProps"
        @click:clear="clearValue"
      >
        <template
          v-if="label"
          #label
        >
          {{ props.label }}&nbsp;
          <span
            v-if="required"
            class="text-error"
          >
            *
          </span>
        </template>
      </VTextField>
    </template>

    <VDatePicker
      v-model="internalValue"
      :max="max"
      :min="min"
      color="primary"
      show-adjacent-months
      @update:model-value="updateValue"
    />
  </VMenu>
</template>
