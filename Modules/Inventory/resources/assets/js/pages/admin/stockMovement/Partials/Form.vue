<script lang="ts" setup>
import { computed, onBeforeMount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { type RouteLocationRaw, useRouter } from 'vue-router'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import BaseForm from '@/modules/core/components/Form/BaseForm.vue'
import { useForm } from '@/modules/core/composables/form.ts'
import { generateUuid } from '@/modules/core/utils/uuid.ts'
import { useStockMovement } from '@/modules/inventory/composables/stockMovement.ts'

const props = defineProps<{
  action: 'create'
}>()

const { t } = useI18n()
const { user } = useAuth()
const { getFormMeta, store } = useStockMovement()
const router = useRouter()

type FormMeta = {
  branches: Record<string, any>[]
  types: Record<string, any>[]
  ingredients: Record<string, any>[]
}

const form = useForm({
  branch_id: user?.assigned_to_branch ? user.branch_id : null,
  ingredient_id: null,
  type: null,
  quantity: null,
  note: null,
  idempotency_key: generateUuid(),
})

const meta = ref<FormMeta>({ branches: [], types: [], ingredients: [] })
const selectedIngredient = computed(() =>
  meta.value.ingredients.find(
    (ingredient: Record<string, any>) => ingredient.id === form.state.ingredient_id,
  ),
)
const quantitySuffix = computed(() => selectedIngredient.value?.symbol || undefined)

async function submit() {
  if (!form.loading.value && (await form.submit(() => store(form.state)))) {
    await router.push({ name: 'admin.stock_movements.index' } as unknown as RouteLocationRaw)
  }
}

onBeforeMount(() => {
  loadFormData()
  if (form.state.branch_id) {
    loadFormData(form.state.branch_id)
  }
})

watch(
  () => form.state.branch_id,
  (newValue) => {
    form.state.ingredient_id = null
    meta.value.ingredients = []
    loadFormData(newValue || undefined)
  },
)

async function loadFormData(branchId?: number) {
  try {
    const response = (await getFormMeta(branchId)).data.body
    meta.value.branches = response.branches || meta.value.branches
    meta.value.types = response.types || meta.value.types
    if (branchId) {
      meta.value.ingredients = response.ingredients
    }
  } catch {
    /* Empty */
  }
}
</script>

<template>
  <BaseForm
    :action="props.action"
    :loading="form.loading.value"
    resource="stock-movements"
    @submit="submit"
  >
    <VRow>
      <VCol cols="12">
        <VCard>
          <VCardTitle class="d-flex justify-space-between align-center mb-2">
            <div class="d-flex align-center">
              <VIcon
                class="me-2"
                icon="tabler-info-circle"
                size="20"
              />

              <span>
                {{ t('inventory::stock_movements.form.cards.stock_movement_information') }}
              </span>
            </div>
          </VCardTitle>

          <VCardText>
            <VRow>
              <VCol
                v-if="!user?.assigned_to_branch"
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="form.state.branch_id"
                  :error="!!form.errors.value?.branch_id"
                  :error-messages="form.errors.value?.branch_id"
                  item-title="name"
                  item-value="id"
                  :items="meta.branches"
                  :label="t('inventory::attributes.stock_movements.branch_id')"
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="form.state.type"
                  :error="!!form.errors.value?.type"
                  :error-messages="form.errors.value?.type"
                  item-title="name"
                  item-value="id"
                  :items="meta.types"
                  :label="t('inventory::attributes.stock_movements.type')"
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="form.state.ingredient_id"
                  :error="!!form.errors.value?.ingredient_id"
                  :error-messages="form.errors.value?.ingredient_id"
                  item-title="name"
                  item-value="id"
                  :items="meta.ingredients"
                  :label="t('inventory::attributes.stock_movements.ingredient_id')"
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="form.state.quantity"
                  :error="!!form.errors.value?.quantity"
                  :error-messages="form.errors.value?.quantity"
                  :label="t('inventory::attributes.stock_movements.quantity')"
                  :suffix="quantitySuffix"
                />
              </VCol>

              <VCol cols="12">
                <VTextarea
                  v-model="form.state.note"
                  auto-grow
                  clearable
                  :counter="1000"
                  :error="!!form.errors.value?.note"
                  :error-messages="form.errors.value?.note"
                  :label="t('inventory::attributes.stock_movements.note')"
                  rows="4"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </BaseForm>
</template>
