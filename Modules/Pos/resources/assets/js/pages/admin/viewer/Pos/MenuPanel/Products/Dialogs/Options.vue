<script lang="ts" setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const props = defineProps<{
  option: Record<string, any>
  errors: Record<string, string>
  modelValue?: Record<string, any>
}>()

const emit = defineEmits(['update:modelValue'])

const value = ref(
  props.modelValue?.[props.option.id] ??
    (props.option.type.id === 'multiple_select' || props.option.type.id === 'checkbox' ? [] : null),
)

watch(value, (val) => {
  const updated = { ...props.modelValue }
  if (val === '' || (Array.isArray(val) && val.length === 0)) {
    delete updated[props.option.id]
  } else {
    updated[props.option.id] = val
  }
  emit('update:modelValue', updated)
})

const items =
  props.option.values?.map((v: Record<string, any>) => {
    let price = ''
    if ((v.price?.amount || v.price) > 0) {
      price = `(${v.price_type === 'fixed' ? v.price.formatted : `${Number.parseFloat(v.price)}%`})`
    }
    return { id: v.id, name: `${v.label} ${price}` }
  }) || []
</script>

<template>
  <section
    class="pos-product-option"
    :aria-label="option.name"
  >
    <div class="pos-option-heading">
      <h3>{{ option.name }}</h3>
      <span
        class="pos-option-requirement"
        :class="{ 'pos-option-requirement--required': option.is_required }"
      >
        {{
          option.is_required ? t('option::options.table.is_required') : t('admin::admin.optional')
        }}
      </span>
    </div>
    <VSelect
      v-if="['select', 'multiple_select'].includes(option.type.id)"
      v-model="value"
      :aria-label="option.name"
      :chips="option.type.id === 'multiple_select'"
      :clearable="!option.is_required"
      :error="!!errors?.[`options.${option.id}`]"
      :error-messages="errors?.[`options.${option.id}`]"
      item-title="name"
      item-value="id"
      :items="items"
      :multiple="option.type.id === 'multiple_select'"
      density="compact"
      variant="outlined"
      hide-details="auto"
    />
    <VTextField
      v-if="option.type.id === 'text'"
      v-model="value"
      :aria-label="option.name"
      :clearable="!option.is_required"
      :error="!!errors?.[`options.${option.id}`]"
      :error-messages="errors?.[`options.${option.id}`]"
      density="compact"
      variant="outlined"
      hide-details="auto"
    />
    <VTextarea
      v-else-if="option.type.id === 'textarea'"
      v-model="value"
      :aria-label="option.name"
      auto-grow
      :clearable="!option.is_required"
      :error="!!errors?.[`options.${option.id}`]"
      :error-messages="errors?.[`options.${option.id}`]"
      rows="2"
      density="compact"
      variant="outlined"
      hide-details="auto"
    />
    <DatePicker
      v-else-if="option.type.id === 'date'"
      v-model="value"
      :clearable="!option.is_required"
      :error="!!errors?.[`options.${option.id}`]"
      :error-messages="errors?.[`options.${option.id}`]"
      :aria-label="option.name"
      density="compact"
      hide-details="auto"
      :min="new Date().toLocaleDateString('en-CA')"
    />
    <TimePicker
      v-else-if="option.type.id === 'time'"
      v-model="value"
      :clearable="!option.is_required"
      :error="!!errors?.[`options.${option.id}`]"
      :error-messages="errors?.[`options.${option.id}`]"
      :aria-label="option.name"
      density="compact"
      hide-details="auto"
    />
    <div v-else-if="option.type.id === 'checkbox'">
      <div
        class="pos-option-choices"
        role="group"
        :aria-label="option.name"
      >
        <VCheckbox
          v-for="v in items"
          :key="v.id"
          v-model="value"
          :error="!!errors?.[`options.${option.id}`]"
          :label="v.name"
          :value="v.id"
          color="primary"
          density="compact"
          hide-details
        />
      </div>
      <p
        v-if="errors?.[`options.${option.id}`]"
        class="pos-option-error"
        role="alert"
      >
        {{ errors[`options.${option.id}`] }}
      </p>
    </div>
    <VRadioGroup
      v-else-if="option.type.id === 'radio'"
      v-model="value"
      class="pos-option-radio"
      :aria-label="option.name"
      :error="!!errors?.[`options.${option.id}`]"
      :error-messages="errors?.[`options.${option.id}`]"
      density="compact"
      hide-details="auto"
    >
      <VRadio
        v-for="v in items"
        :key="v.id"
        :label="v.name"
        :value="v.id"
        color="primary"
      />
    </VRadioGroup>
  </section>
</template>
