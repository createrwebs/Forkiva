<script lang="ts" setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTheme } from 'vuetify'
import { getContrast } from 'vuetify/lib/util/colorUtils.js'

const props = defineProps<{
  modelValue: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'apply', value: number): void
}>()

const { t } = useI18n()
const theme = useTheme()
const primaryText = computed(() => {
  const primary = theme.current.value.colors.primary
  return getContrast(primary, '#000000') > getContrast(primary, '#ffffff') ? '#000000' : '#ffffff'
})

const dialogModel = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})

const expression = ref('0')
const preview = ref<number | null>(0)
const hasError = ref(false)

const buttons = [
  { value: '7', label: '7' },
  { value: '8', label: '8' },
  { value: '9', label: '9' },
  {
    value: '/',
    label: '÷',
  },
  { value: '4', label: '4' },
  { value: '5', label: '5' },
  { value: '6', label: '6' },
  {
    value: '*',
    label: '×',
  },
  { value: '1', label: '1' },
  { value: '2', label: '2' },
  { value: '3', label: '3' },
  {
    value: '-',
    label: '−',
  },
  { value: '0', label: '0' },
  { value: '.', label: '.' },
  { value: 'C', label: 'C' },
  {
    value: '+',
    label: '+',
  },
]

const sanitizedExpression = computed(() => expression.value.replace(/\s+/g, ''))
const expressionLabel = computed(() => expression.value.replace(/\*/g, '×').replace(/\//g, '÷'))

function evaluateExpression(): number | null {
  const expr = sanitizedExpression.value
  if (!/^[0-9+\-*/.()]+$/.test(expr)) {
    return null
  }

  try {
    const result = new Function(`"use strict"; return (${expr});`)()
    if (typeof result !== 'number' || Number.isNaN(result) || !Number.isFinite(result)) {
      return null
    }
    return result
  } catch {
    return null
  }
}

watch(expression, () => {
  const result = evaluateExpression()
  hasError.value = result === null || result === undefined
  preview.value = result
})

const isSubmitDisabled = computed(() => {
  const result = evaluateExpression()
  return result === null || result === undefined || result < 0
})

watch(
  () => props.modelValue,
  (opened) => {
    if (opened) {
      expression.value = '0'
      preview.value = 0
      hasError.value = false
    }
  },
)

function append(value: string) {
  if (value === 'C') {
    expression.value = '0'
    hasError.value = false
    preview.value = 0
    return
  }

  if (value === '.' && /(^|[+\-*/])[^+\-*/]*\./.test(expression.value)) {
    return
  }

  const next =
    expression.value === '0' && /[0-9.]/.test(value)
      ? value === '.'
        ? '0.'
        : value
      : `${expression.value}${value}`
  expression.value = next
}

function apply() {
  const result = evaluateExpression()
  if (result === null || result === undefined) {
    hasError.value = true
    return
  }

  emit('apply', result)
  dialogModel.value = false
}
</script>

<template>
  <VDialog
    v-model="dialogModel"
    :aria-label="t('order::orders.calculator')"
    max-width="400"
  >
    <VCard
      :style="{ '--calc-primary-text': primaryText }"
      class="calculator-card"
    >
      <VCardText class="calc-content">
        <div
          :class="{ 'calc-error': hasError }"
          aria-live="polite"
          class="calc-display"
          dir="ltr"
        >
          <div class="calc-expression">{{ expressionLabel }}</div>
          <div
            :class="{ 'calc-preview-error': hasError }"
            class="calc-preview"
          >
            {{ hasError ? t('order::orders.invalid_expression') : preview }}
          </div>
        </div>

        <div class="calc-grid">
          <VBtn
            v-for="button in buttons"
            :key="button.value"
            :class="{
              'calc-operator': ['+', '-', '*', '/'].includes(button.value),
              'calc-clear': button.value === 'C',
            }"
            class="calc-btn"
            color="default"
            variant="flat"
            @click="append(button.value)"
          >
            {{ button.label }}
          </VBtn>
        </div>
      </VCardText>
      <VCardActions class="calc-actions">
        <VBtn
          class="calc-cancel"
          color="default"
          size="large"
          variant="outlined"
          @click="dialogModel = false"
        >
          {{ t('admin::admin.buttons.cancel') }}
        </VBtn>
        <VBtn
          :disabled="isSubmitDisabled"
          class="calc-apply"
          color="primary"
          size="large"
          variant="flat"
          @click="apply"
        >
          <VIcon
            class="me-2"
            icon="tabler-check"
            size="18"
          />
          {{ t('order::orders.submit_result') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.calculator-card {
  --calc-outline: rgba(var(--v-theme-on-surface), 0.12);
  border: 1px solid var(--calc-outline);
  border-radius: 22px !important;
  overflow-y: auto;
  color: rgb(var(--v-theme-on-surface));
}

.calc-content {
  padding: 20px !important;
}

.calc-display {
  border: 1px solid var(--calc-outline);
  border-radius: 14px;
  padding: 14px;
  min-height: 94px;
  text-align: right;
  background: rgba(var(--v-theme-on-surface), 0.045);
}

.calc-error {
  border-color: rgba(var(--v-theme-error), 0.5);
}

.calc-expression {
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 15px;
  font-weight: 600;
  min-height: 22px;
  overflow-wrap: anywhere;
}

.calc-preview {
  font-size: 40px;
  line-height: 1.2;
  font-weight: 750;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
  margin-top: 4px;
}

.calc-preview-error {
  color: rgb(var(--v-theme-error));
  font-size: 14px;
}

.calc-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
  margin-top: 14px;
  direction: ltr;
}

.calc-btn {
  height: 56px !important;
  border-radius: 12px !important;
  border: 1px solid var(--calc-outline);
  background: rgba(var(--v-theme-on-surface), 0.035) !important;
  color: rgb(var(--v-theme-on-surface)) !important;
  font-size: 25px;
  font-weight: 600;
  min-width: 0;
  padding: 0;
  letter-spacing: 0;
}

.calc-btn :deep(.v-btn__overlay) {
  opacity: 0;
}

.calc-btn:hover :deep(.v-btn__overlay),
.calc-btn:focus-visible :deep(.v-btn__overlay) {
  opacity: 0.06;
}

.calc-btn:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

.calc-operator {
  background: rgba(var(--v-theme-primary), 0.12) !important;
  border-color: rgba(var(--v-theme-primary), 0.22);
  color: rgb(var(--v-theme-primary)) !important;
  font-size: 30px;
}

.calc-clear {
  background: rgba(var(--v-theme-error), 0.1) !important;
  border-color: rgba(var(--v-theme-error), 0.2);
  color: rgb(var(--v-theme-error)) !important;
}

.calc-actions {
  padding: 0 20px 20px !important;
  display: grid;
  grid-template-columns: 1fr 1.8fr;
  gap: 10px;
}

.calc-actions .v-btn {
  height: 50px;
  margin: 0 !important;
  border-radius: 12px !important;
  font-size: 14px;
  font-weight: 650;
  letter-spacing: 0;
  text-transform: none;
  min-width: 0;
}

.calc-cancel {
  border-color: rgba(var(--v-theme-on-surface), 0.25);
}

.calc-apply.v-btn--disabled {
  opacity: 1;
  background: rgba(var(--v-theme-on-surface), 0.08) !important;
  color: rgba(var(--v-theme-on-surface), 0.6) !important;
}

.calc-apply.v-btn--disabled :deep(.v-btn__overlay) {
  opacity: 0;
}

@media (max-width: 380px) {
  .calc-content {
    padding-inline: 16px !important;
  }
  .calc-actions {
    padding-inline: 16px !important;
  }
}
</style>
