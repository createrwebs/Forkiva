<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
const props = defineProps<{ modelValue: string; disabled?: boolean; error?: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const { t } = useI18n()
const digits = ref<string[]>(Array(6).fill(''))
const inputs: (HTMLInputElement | null)[] = []
watch(
  () => props.modelValue,
  (value) => {
    if (value !== digits.value.join(''))
      digits.value = Array.from({ length: 6 }, (_, index) => value[index] ?? '')
  },
  { immediate: true },
)
function normalize(value: string) {
  return value
    .replace(/[٠-٩]/g, (digit) => String(digit.charCodeAt(0) - 1632))
    .replace(/[۰-۹]/g, (digit) => String(digit.charCodeAt(0) - 1776))
    .replace(/\D/g, '')
}
function update(index: number, value: string) {
  if (props.disabled) return
  const code = normalize(value).slice(0, 6 - index)
  if (!code) digits.value[index] = ''
  else
    [...code].forEach((digit, offset) => {
      digits.value[index + offset] = digit
    })
  emit('update:modelValue', digits.value.join(''))
  if (code) inputs[Math.min(index + code.length, 5)]?.focus()
}
function input(index: number, event: Event) {
  const target = event.target as HTMLInputElement
  update(index, target.value)
  target.value = digits.value[index] ?? ''
}
function paste(index: number, event: ClipboardEvent) {
  event.preventDefault()
  update(index, event.clipboardData?.getData('text') ?? '')
}
function keydown(index: number, event: KeyboardEvent) {
  if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
    event.preventDefault()
    digits.value[index - 1] = ''
    emit('update:modelValue', digits.value.join(''))
    inputs[index - 1]?.focus()
  } else if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
    event.preventDefault()
    inputs[Math.max(0, Math.min(5, index + (event.key === 'ArrowLeft' ? -1 : 1)))]?.focus()
  }
}
</script>

<template>
  <div>
    <div
      class="recovery-digits"
      role="group"
      :aria-label="t('user::auth.recovery_code')"
      dir="ltr"
    >
      <input
        v-for="(_, index) in digits"
        :key="index"
        :ref="
          (el) => {
            inputs[index] = el as HTMLInputElement | null
          }
        "
        :value="digits[index]"
        type="text"
        inputmode="numeric"
        pattern="[0-9]*"
        :autocomplete="index === 0 ? 'one-time-code' : 'off'"
        :maxlength="index === 0 ? 6 : 1"
        :aria-label="t('user::auth.recovery_digit', { number: index + 1 })"
        :aria-invalid="!!error"
        :aria-describedby="error ? 'recovery-code-error' : undefined"
        :disabled="disabled"
        :autofocus="index === 0"
        @input="input(index, $event)"
        @paste="paste(index, $event)"
        @keydown="keydown(index, $event)"
        @focus="($event.target as HTMLInputElement).select()"
      />
    </div>
    <p
      v-if="error"
      id="recovery-code-error"
      class="recovery-digits__error"
      role="alert"
    >
      {{ error }}
    </p>
  </div>
</template>

<style scoped lang="scss">
.recovery-digits {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 0.5rem;
}
.recovery-digits input {
  inline-size: 100%;
  min-inline-size: 0;
  block-size: 3rem;
  border: 1px solid #c4ccd8;
  border-radius: 0.5rem;
  background: #fff;
  color: #202b3c;
  font-family: inherit;
  font-size: 1.25rem;
  font-weight: 650;
  text-align: center;
}
.recovery-digits input:focus {
  outline: 2px solid #d96b0c;
  outline-offset: 2px;
}
.recovery-digits input[aria-invalid='true'] {
  border-color: #b42318;
}
.recovery-digits input:disabled {
  opacity: 0.6;
}
.recovery-digits__error {
  margin: 0.5rem 0 0;
  color: #b42318;
  font-size: 0.8rem;
}
</style>
