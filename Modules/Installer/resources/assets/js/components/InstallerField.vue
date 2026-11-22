<script setup lang="ts">
withDefaults(
  defineProps<{
    label: string
    name: string
    type?: string
    hint?: string
    errors?: string[]
    autocomplete?: string
    maxlength?: number
    min?: number
    max?: number
    readonly?: boolean
    required?: boolean
    placeholder?: string
  }>(),
  { type: 'text', required: true },
)
const model = defineModel<string>({ required: true, set: (value) => String(value) })
</script>

<template>
  <div class="field">
    <label :for="name">
      {{ label }}
      <span
        v-if="!required"
        class="optional"
      >
        Optional
      </span>
    </label>
    <input
      :id="name"
      v-model="model"
      :name="name"
      :type="type"
      :required="required"
      :readonly="readonly"
      :autocomplete="autocomplete"
      :maxlength="maxlength"
      :min="min"
      :max="max"
      :placeholder="placeholder"
      :aria-invalid="!!errors?.length"
      :aria-describedby="`${name}-help`"
    />
    <div :id="`${name}-help`">
      <p
        v-for="message in errors"
        :key="message"
        class="field-error"
      >
        {{ message }}
      </p>
      <p
        v-if="hint"
        class="field-hint"
      >
        {{ hint }}
      </p>
    </div>
  </div>
</template>
