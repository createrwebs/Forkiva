<script lang="ts" setup>
import type { RouteLocationRaw } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import BaseForm from '@/modules/core/components/Form/BaseForm.vue'
import DatePicker from '@/modules/core/components/Form/DatePicker.vue'
import SearchSelect from '@/modules/core/components/Form/SearchSelect.vue'
import { useForm } from '@/modules/core/composables/form.ts'
import { useStockTransfer } from '@/modules/inventory/composables/stockTransfer.ts'

const props = defineProps<{
  item?: Record<string, any> | null
  action: 'create' | 'update'
}>()

const { t } = useI18n()
const { user } = useAuth()
const router = useRouter()
const { getStockTransferFormMeta, storeStockTransfer, updateStockTransfer } = useStockTransfer()
const loadingMeta = ref(false)
const meta = ref<Record<string, any>>({
  branches: [],
  source_ingredients: [],
  destination_ingredients: [],
})
let metaRequestSequence = 0
const form = useForm({
  source_branch_id:
    props.item?.source_branch?.id || (user?.assigned_to_branch ? user.branch_id : null),
  destination_branch_id: props.item?.destination_branch?.id || null,
  expected_at: props.item?.expected_at || null,
  notes: props.item?.notes || null,
  items: props.item?.items?.length
    ? props.item.items.map((line: Record<string, any>) => ({
        source_ingredient_id: line.source_ingredient?.id,
        destination_ingredient_id: line.destination_ingredient?.id,
        quantity: line.requested_quantity,
      }))
    : [{ source_ingredient_id: null, destination_ingredient_id: null, quantity: null }],
})

const destinationBranches = computed(() => {
  const source = meta.value.branches.find(
    (branch: Record<string, any>) => Number(branch.id) === Number(form.state.source_branch_id),
  )
  return meta.value.branches.filter(
    (branch: Record<string, any>) =>
      Number(branch.id) !== Number(form.state.source_branch_id) &&
      (!source || branch.currency === source.currency),
  )
})

function sourceIngredient(line: Record<string, any>): Record<string, any> | null {
  return (
    meta.value.source_ingredients.find(
      (ingredient: Record<string, any>) =>
        Number(ingredient.id) === Number(line.source_ingredient_id),
    ) || null
  )
}

function destinationIngredients(line: Record<string, any>): Record<string, any>[] {
  const unitId = sourceIngredient(line)?.unit_id
  return meta.value.destination_ingredients.filter(
    (ingredient: Record<string, any>) => !unitId || Number(ingredient.unit_id) === Number(unitId),
  )
}

function mergeIngredientOptions(
  key: 'source_ingredients' | 'destination_ingredients',
  items: Record<string, any>[],
): void {
  const merged = new Map(
    [...meta.value[key], ...items].map((ingredient: Record<string, any>) => [
      Number(ingredient.id),
      ingredient,
    ]),
  )
  meta.value[key] = Array.from(merged.values())
}

function lineStockAvailabilityError(line: Record<string, any>): string {
  const ingredient = sourceIngredient(line)
  if (!ingredient || !line.quantity) return ''

  const quantity = Number(line.quantity)
  const available = Number(ingredient.current_stock)

  if (Number.isFinite(quantity) && Number.isFinite(available) && quantity > available) {
    return t('inventory::messages.stock_transfer_insufficient_stock')
  }

  return ''
}

function lineQuantityError(index: number, line: Record<string, any>): string {
  return form.errors.value?.[`items.${index}.quantity`] || lineStockAvailabilityError(line)
}

const hasStockAvailabilityError = computed(() =>
  form.state.items.some((line: Record<string, any>) => !!lineStockAvailabilityError(line)),
)

function addItem(): void {
  form.state.items.push({
    source_ingredient_id: null,
    destination_ingredient_id: null,
    quantity: null,
  })
}

function removeItem(index: number): void {
  if (form.state.items.length === 1) return
  form.state.items.splice(index, 1)
}

async function loadMeta(): Promise<void> {
  const sequence = ++metaRequestSequence
  loadingMeta.value = true
  try {
    const response = await getStockTransferFormMeta(
      form.state.source_branch_id,
      form.state.destination_branch_id,
      form.state.items
        .map((line: Record<string, any>) => Number(line.source_ingredient_id))
        .filter((id: number) => Number.isFinite(id) && id > 0),
      form.state.items
        .map((line: Record<string, any>) => Number(line.destination_ingredient_id))
        .filter((id: number) => Number.isFinite(id) && id > 0),
    )
    if (sequence !== metaRequestSequence) return
    meta.value = response.data.body
  } finally {
    if (sequence === metaRequestSequence) loadingMeta.value = false
  }
}

watch(
  () => form.state.source_branch_id,
  (value, oldValue) => {
    if (props.action === 'create' && oldValue !== undefined && value !== oldValue) {
      form.state.destination_branch_id = null
      form.state.items = [
        {
          source_ingredient_id: null,
          destination_ingredient_id: null,
          quantity: null,
        },
      ]
    }
    void loadMeta()
  },
)

watch(
  () => form.state.destination_branch_id,
  (value, oldValue) => {
    if (props.action === 'create' && oldValue !== undefined && value !== oldValue) {
      for (const line of form.state.items) {
        line.destination_ingredient_id = null
      }
    }
    if (value) {
      void loadMeta()
    } else {
      meta.value.destination_ingredients = []
    }
  },
)

watch(
  () => form.state.items.map((line: Record<string, any>) => line.source_ingredient_id),
  (values, oldValues) => {
    for (const [index, value] of values.entries()) {
      if (oldValues?.[index] !== undefined && value !== oldValues[index]) {
        form.state.items[index].destination_ingredient_id = null
      }
    }
  },
)

async function submit(): Promise<void> {
  const saved = await form.submit(() =>
    props.action === 'create'
      ? storeStockTransfer(form.state)
      : updateStockTransfer(props.item?.id, form.state),
  )
  if (!saved) return

  await router.push({
    name: props.action === 'create' ? 'admin.stock_transfers.index' : 'admin.stock_transfers.show',
    params: props.action === 'update' ? { id: props.item?.id } : undefined,
  } as unknown as RouteLocationRaw)
}

onBeforeMount(loadMeta)
</script>

<template>
  <BaseForm
    :action="action"
    :disabled="hasStockAvailabilityError"
    :loading="form.loading.value"
    resource="stock_transfers"
    @submit="submit"
  >
    <VRow>
      <VCol
        cols="12"
        lg="3"
      >
        <VCard>
          <VCardTitle class="d-flex align-center">
            <VIcon
              class="me-2"
              icon="tabler-transfer"
              size="20"
            />
            {{ t('inventory::stock_transfers.form.details') }}
          </VCardTitle>

          <VCardText>
            <VRow>
              <VCol
                v-if="action === 'create' && !user?.assigned_to_branch"
                cols="12"
              >
                <VSelect
                  v-model="form.state.source_branch_id"
                  :disabled="user?.assigned_to_branch"
                  :error-messages="form.errors.value?.source_branch_id"
                  item-title="name"
                  item-value="id"
                  :items="meta.branches"
                  :label="t('inventory::attributes.stock_transfers.source_branch_id')"
                  :loading="loadingMeta"
                />
              </VCol>

              <VCol cols="12">
                <VSelect
                  v-model="form.state.destination_branch_id"
                  :disabled="action === 'update' || !form.state.source_branch_id"
                  :error-messages="form.errors.value?.destination_branch_id"
                  item-title="name"
                  item-value="id"
                  :items="destinationBranches"
                  :label="t('inventory::attributes.stock_transfers.destination_branch_id')"
                  :loading="loadingMeta"
                />
              </VCol>

              <VCol cols="12">
                <DatePicker
                  v-model="form.state.expected_at"
                  :error-messages="form.errors.value?.expected_at"
                  :label="t('inventory::attributes.stock_transfers.expected_at')"
                />
              </VCol>

              <VCol cols="12">
                <VTextarea
                  v-model="form.state.notes"
                  :error-messages="form.errors.value?.notes"
                  :label="t('inventory::attributes.stock_transfers.notes')"
                  rows="4"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        lg="9"
      >
        <VCard class="stock-transfer-items-card">
          <VCardTitle class="d-flex align-center">
            <div class="d-flex align-center">
              <VIcon
                class="me-2"
                icon="tabler-package-export"
                size="20"
              />
              {{ t('inventory::stock_transfers.form.items') }}
            </div>
          </VCardTitle>

          <VCardText class="stock-transfer-items">
            <VAlert
              v-if="form.errors.value?.items"
              class="mb-4"
              color="error"
              density="compact"
              variant="tonal"
            >
              {{ form.errors.value.items }}
            </VAlert>

            <div class="stock-transfer-items-table">
              <table>
                <thead>
                  <tr>
                    <th class="stock-transfer-items-table__number-cell">#</th>

                    <th>
                      {{ t('inventory::stock_transfers.show.source_ingredient') }}
                    </th>
                    <th>
                      {{ t('inventory::stock_transfers.form.available') }}
                    </th>
                    <th>
                      {{ t('inventory::stock_transfers.show.destination_ingredient') }}
                    </th>
                    <th>
                      {{ t('inventory::attributes.stock_transfers.items.*.quantity') }}
                    </th>

                    <th class="stock-transfer-items-table__action-cell">
                      {{ t('admin::admin.table.actions') }}
                    </th>
                  </tr>
                </thead>

                <tbody>
                  <tr
                    v-for="(line, index) in form.state.items"
                    :key="index"
                    class="stock-transfer-items-table__row"
                  >
                    <td class="stock-transfer-items-table__number-cell">
                      {{ Number(index) + 1 }}
                    </td>

                    <td class="stock-transfer-items-table__ingredient-cell">
                      <SearchSelect
                        v-model="line.source_ingredient_id"
                        class="stock-transfer-items-table__ingredient"
                        :disabled="!form.state.destination_branch_id"
                        :error="!!form.errors.value?.[`items.${index}.source_ingredient_id`]"
                        :error-messages="form.errors.value?.[`items.${index}.source_ingredient_id`]"
                        item-title="name"
                        item-value="id"
                        :items="meta.source_ingredients"
                        :label="t('inventory::stock_transfers.show.source_ingredient')"
                        query-param-path="search"
                        :request-params="{
                          branch_id: form.state.source_branch_id,
                          role: 'source',
                        }"
                        url="/v1/stock-transfers/form/ingredients"
                        @loaded="(items) => mergeIngredientOptions('source_ingredients', items)"
                      />
                    </td>

                    <td class="stock-transfer-items-table__available-cell">
                      <div
                        v-if="sourceIngredient(line)"
                        class="stock-transfer-items-table__available"
                      >
                        <span>
                          {{ sourceIngredient(line)?.current_stock }}
                        </span>
                        <small>{{ sourceIngredient(line)?.unit }}</small>
                      </div>

                      <span
                        v-else
                        class="text-medium-emphasis"
                      >
                        -
                      </span>
                    </td>

                    <td class="stock-transfer-items-table__ingredient-cell">
                      <SearchSelect
                        v-model="line.destination_ingredient_id"
                        class="stock-transfer-items-table__ingredient"
                        :disabled="!line.source_ingredient_id"
                        :error="!!form.errors.value?.[`items.${index}.destination_ingredient_id`]"
                        :error-messages="
                          form.errors.value?.[`items.${index}.destination_ingredient_id`]
                        "
                        item-title="name"
                        item-value="id"
                        :items="destinationIngredients(line)"
                        :label="t('inventory::stock_transfers.show.destination_ingredient')"
                        query-param-path="search"
                        :request-params="{
                          branch_id: form.state.destination_branch_id,
                          source_branch_id: form.state.source_branch_id,
                          role: 'destination',
                          unit_id: sourceIngredient(line)?.unit_id,
                        }"
                        url="/v1/stock-transfers/form/ingredients"
                        @loaded="
                          (items) => mergeIngredientOptions('destination_ingredients', items)
                        "
                      />
                    </td>

                    <td class="stock-transfer-items-table__quantity-cell">
                      <VTextField
                        v-model="line.quantity"
                        v-decimal-en
                        density="comfortable"
                        :disabled="!line.destination_ingredient_id"
                        :error="!!lineQuantityError(Number(index), line)"
                        :error-messages="lineQuantityError(Number(index), line)"
                        hide-details="auto"
                        :label="t('inventory::attributes.stock_transfers.items.*.quantity')"
                        :suffix="sourceIngredient(line)?.unit || undefined"
                      />
                    </td>

                    <td class="stock-transfer-items-table__action-cell">
                      <VTooltip :text="t('inventory::stock_transfers.form.remove_item')">
                        <template #activator="{ props: tooltipProps }">
                          <span
                            class="d-inline-flex"
                            v-bind="tooltipProps"
                          >
                            <VBtn
                              color="error"
                              :disabled="form.state.items.length === 1"
                              icon="tabler-trash"
                              size="small"
                              variant="text"
                              @click="removeItem(Number(index))"
                            />
                          </span>
                        </template>
                      </VTooltip>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </VCardText>

          <VCardActions class="stock-transfer-items-card__actions">
            <VTooltip :text="t('inventory::stock_transfers.form.add_item')">
              <template #activator="{ props: tooltipProps }">
                <span
                  class="d-inline-flex"
                  v-bind="tooltipProps"
                >
                  <VBtn
                    color="primary"
                    :disabled="!form.state.source_branch_id || !form.state.destination_branch_id"
                    prepend-icon="tabler-plus"
                    variant="tonal"
                    @click="addItem"
                  >
                    {{ t('inventory::stock_transfers.form.add_item') }}
                  </VBtn>
                </span>
              </template>
            </VTooltip>
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>
  </BaseForm>
</template>

<style lang="scss" scoped>
.stock-transfer-items {
  padding: 0 20px 20px;
  overflow: visible;
}

.stock-transfer-items-table {
  overflow: visible;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}

.stock-transfer-items-table table {
  width: 100%;
  min-width: 920px;
  border-collapse: separate;
  border-spacing: 0;
}

.stock-transfer-items-table th {
  padding: 14px 16px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: transparent;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.8125rem;
  font-weight: 700;
  text-align: start;
  white-space: nowrap;
}

.stock-transfer-items-table td {
  position: relative;
  padding: 12px 16px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  vertical-align: top;
}

.stock-transfer-items-table tbody tr:last-child td {
  border-bottom: 0;
}

.stock-transfer-items-table__row:focus-within {
  position: relative;
  z-index: 40;
}

.stock-transfer-items-table__number-cell {
  width: 56px;
  color: rgb(var(--v-theme-on-surface));
  font-weight: 700;
}

.stock-transfer-items-table__ingredient-cell {
  width: 28%;
  min-width: 220px;
  overflow: visible;
}

.stock-transfer-items-table__ingredient {
  position: relative;
}

.stock-transfer-items-table__ingredient:focus-within {
  z-index: 50;
}

.stock-transfer-items-table__available-cell {
  width: 14%;
  min-width: 130px;
}

.stock-transfer-items-table__available {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 36px;
  padding: 6px 12px;
  border-radius: 999px;
  color: rgb(var(--v-theme-on-surface));
  background: rgba(var(--v-theme-primary), 0.08);
  font-size: 0.875rem;
  line-height: 1.2;
}

.stock-transfer-items-table__available span {
  font-weight: 700;
}

.stock-transfer-items-table__available small {
  color: rgb(var(--v-theme-on-surface-variant));
  font-size: 0.75rem;
}

.stock-transfer-items-table__quantity-cell {
  width: 20%;
  min-width: 180px;
}

.stock-transfer-items-table__action-cell {
  width: 88px;
  text-align: center;
}

.stock-transfer-items-card__actions {
  justify-content: flex-end;
  padding: 0 20px 20px;
}

.stock-transfer-items-card,
.stock-transfer-items-card :deep(.v-card-text) {
  overflow: visible;
}

.stock-transfer-items-card :deep(.search-select__panel) {
  z-index: 1000;
}

@media (max-width: 959px) {
  .stock-transfer-items-table table {
    min-width: 0;
  }

  .stock-transfer-items-table thead {
    display: none;
  }

  .stock-transfer-items-table tbody,
  .stock-transfer-items-table tr,
  .stock-transfer-items-table td {
    display: block;
    width: 100%;
  }

  .stock-transfer-items-table__row + .stock-transfer-items-table__row {
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }

  .stock-transfer-items-table td {
    min-width: 0;
    padding: 8px 16px;
    border-bottom: 0;
  }

  .stock-transfer-items-table__number-cell {
    padding-top: 16px !important;
  }

  .stock-transfer-items-table__ingredient-cell,
  .stock-transfer-items-table__available-cell,
  .stock-transfer-items-table__quantity-cell,
  .stock-transfer-items-table__action-cell {
    width: 100%;
  }

  .stock-transfer-items-table__action-cell {
    padding-bottom: 16px !important;
    text-align: end;
  }
}
</style>
