<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { form, meta } = defineProps<{
  form: any
  meta: Record<string, any>
}>()
const emit = defineEmits<{
  'update:mode': [value: string]
  'update:ingredient': [value: number | null]
  'update:quantity': [value: string | number | null]
}>()

const { t } = useI18n()
const usesProductionOutput = computed(
  () => form.state.inventory_consumption_mode === 'production_output',
)
const selectedIngredient = computed(() =>
  meta.ingredients.find(
    (ingredient: Record<string, any>) => ingredient.id === form.state.produced_ingredient_id,
  ),
)
</script>

<template>
  <VCard class="mt-3">
    <VCardTitle class="d-flex align-center mb-2">
      <VIcon
        class="me-2"
        icon="tabler-package-export"
        size="20"
      />
      <span>{{ t('product::products.form.cards.inventory_consumption') }}</span>
    </VCardTitle>

    <VCardText>
      <VAlert
        class="mb-4"
        color="info"
        icon="tabler-info-circle"
        variant="tonal"
      >
        {{ t('product::products.form.inventory_consumption_help') }}
      </VAlert>

      <VRow>
        <VCol
          cols="12"
          :md="usesProductionOutput ? 4 : 12"
        >
          <VSelect
            :error="!!form.errors.value?.inventory_consumption_mode"
            :error-messages="form.errors.value?.inventory_consumption_mode"
            item-title="name"
            item-value="id"
            :items="meta.inventoryConsumptionModes"
            :label="t('product::attributes.products.inventory_consumption_mode')"
            :model-value="form.state.inventory_consumption_mode"
            @update:model-value="emit('update:mode', $event)"
          />
        </VCol>

        <template v-if="usesProductionOutput">
          <VCol
            cols="12"
            md="5"
          >
            <VSelect
              :error="!!form.errors.value?.produced_ingredient_id"
              :error-messages="form.errors.value?.produced_ingredient_id"
              item-title="name"
              item-value="id"
              :items="meta.ingredients"
              :label="t('product::attributes.products.produced_ingredient_id')"
              :model-value="form.state.produced_ingredient_id"
              @update:model-value="emit('update:ingredient', $event)"
            />
          </VCol>

          <VCol
            cols="12"
            md="3"
          >
            <VTextField
              :error="!!form.errors.value?.produced_ingredient_quantity"
              :error-messages="form.errors.value?.produced_ingredient_quantity"
              :label="t('product::attributes.products.produced_ingredient_quantity')"
              min="0.000001"
              :model-value="form.state.produced_ingredient_quantity"
              step="0.000001"
              @update:model-value="emit('update:quantity', $event)"
            >
              <template
                v-if="selectedIngredient?.symbol"
                #append-inner
              >
                {{ selectedIngredient.symbol }}
              </template>
            </VTextField>
          </VCol>

          <VCol cols="12">
            <div class="text-body-2 text-medium-emphasis">
              {{ t('product::products.form.production_output_help') }}
            </div>
          </VCol>
        </template>
      </VRow>
    </VCardText>
  </VCard>
</template>
