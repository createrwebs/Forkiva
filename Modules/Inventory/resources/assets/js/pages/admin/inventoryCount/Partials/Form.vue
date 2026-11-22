<script lang="ts" setup>
import type { RouteLocationRaw } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import BaseForm from '@/modules/core/components/Form/BaseForm.vue'
import DatePicker from '@/modules/core/components/Form/DatePicker.vue'
import SearchSelect from '@/modules/core/components/Form/SearchSelect.vue'
import { useForm } from '@/modules/core/composables/form.ts'
import { useInventoryCount } from '@/modules/inventory/composables/inventoryCount.ts'

const props = defineProps<{
  item?: Record<string, any> | null
  action: 'create' | 'update'
}>()

const { t } = useI18n()
const { user } = useAuth()
const router = useRouter()
const { getInventoryCountFormMeta, storeInventoryCount, updateInventoryCount } = useInventoryCount()
const meta = ref<Record<string, any>>({ branches: [], ingredients: [] })
const countItemsDisabled = computed(() => !form.state.branch_id)

const form = useForm({
  branch_id:
    props.action === 'create'
      ? user?.assigned_to_branch
        ? user.branch_id
        : null
      : props.item?.branch?.id,
  count_date: props.item?.count_date || new Date().toISOString().slice(0, 10),
  notes: props.item?.notes || null,
  items: props.item?.items?.length
    ? props.item.items.map((item: Record<string, any>) => ({
        ingredient_id: item.ingredient?.id,
        counted_quantity: item.counted_quantity,
      }))
    : [{ ingredient_id: null, counted_quantity: null }],
})

function ingredient(item: Record<string, any>): Record<string, any> | null {
  return (
    meta.value.ingredients.find(
      (candidate: Record<string, any>) => Number(candidate.id) === Number(item.ingredient_id),
    ) || null
  )
}

function addItem(): void {
  form.state.items.push({ ingredient_id: null, counted_quantity: null })
}

function removeItem(index: number): void {
  if (form.state.items.length === 1) return
  form.state.items.splice(index, 1)
}

async function loadMeta(branchId?: number | null): Promise<void> {
  const response = (await getInventoryCountFormMeta(branchId)).data.body
  meta.value.branches = response.branches || meta.value.branches
  meta.value.ingredients = response.ingredients || []
}

watch(
  () => form.state.branch_id,
  (branchId) => {
    if (props.action !== 'create') return
    form.state.items = [{ ingredient_id: null, counted_quantity: null }]
    meta.value.ingredients = []
    if (branchId) void loadMeta(Number(branchId))
  },
)

async function submit(): Promise<void> {
  if (
    await form.submit(() =>
      props.action === 'create'
        ? storeInventoryCount(form.state)
        : updateInventoryCount(props.item?.id, form.state),
    )
  ) {
    await router.push({ name: 'admin.inventory_counts.index' } as unknown as RouteLocationRaw)
  }
}

onBeforeMount(async () => {
  await loadMeta()
  if (form.state.branch_id) await loadMeta(Number(form.state.branch_id))
})
</script>

<template>
  <BaseForm
    :action="action"
    :loading="form.loading.value"
    resource="inventory_counts"
    @submit="submit"
  >
    <VRow>
      <VCol
        cols="12"
        lg="4"
      >
        <VCard class="inventory-count-form-card">
          <VCardTitle class="d-flex align-center">
            <VIcon
              class="me-2"
              icon="tabler-clipboard-list"
              size="20"
            />
            {{ t(`inventory::inventory_counts.form.${action}`) }}
          </VCardTitle>

          <VCardText class="pa-5">
            <VRow>
              <VCol cols="12">
                <VSelect
                  v-if="action === 'create' && !user?.assigned_to_branch"
                  v-model="form.state.branch_id"
                  :error="!!form.errors.value?.branch_id"
                  :error-messages="form.errors.value?.branch_id"
                  item-title="name"
                  item-value="id"
                  :items="meta.branches"
                  :label="t('inventory::attributes.inventory_counts.branch_id')"
                />

                <VTextField
                  v-else
                  :label="t('inventory::attributes.inventory_counts.branch_id')"
                  :model-value="item?.branch?.name || ''"
                  readonly
                />
              </VCol>

              <VCol cols="12">
                <DatePicker
                  v-model="form.state.count_date"
                  :error="!!form.errors.value?.count_date"
                  :error-messages="form.errors.value?.count_date"
                  :label="t('inventory::attributes.inventory_counts.count_date')"
                />
              </VCol>

              <VCol cols="12">
                <VTextarea
                  v-model="form.state.notes"
                  :error="!!form.errors.value?.notes"
                  :error-messages="form.errors.value?.notes"
                  :label="t('inventory::attributes.inventory_counts.notes')"
                  rows="4"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        lg="8"
      >
        <VCard class="inventory-count-items-card">
          <VCardTitle class="d-flex align-center">
            <div class="d-flex align-center">
              <VIcon
                class="me-2"
                icon="tabler-list-check"
                size="20"
              />
              {{ t('inventory::inventory_counts.form.items') }}
            </div>
          </VCardTitle>

          <VCardText class="inventory-count-items">
            <div class="inventory-count-items-table">
              <table>
                <thead>
                  <tr>
                    <th class="inventory-count-items-table__number-cell">#</th>

                    <th>
                      {{ t('inventory::attributes.inventory_counts.items.*.ingredient_id') }}
                    </th>
                    <th>
                      {{ t('inventory::inventory_counts.form.expected_quantity') }}
                    </th>

                    <th>
                      {{ t('inventory::attributes.inventory_counts.items.*.counted_quantity') }}
                    </th>

                    <th class="inventory-count-items-table__action-cell">
                      {{ t('admin::admin.table.actions') }}
                    </th>
                  </tr>
                </thead>

                <tbody>
                  <tr
                    v-for="(countItem, index) in form.state.items"
                    :key="index"
                    class="inventory-count-items-table__row"
                  >
                    <td class="inventory-count-items-table__number-cell">
                      {{ Number(index) + 1 }}
                    </td>

                    <td
                      class="inventory-count-items-table__ingredient-cell"
                      style="width: 40%"
                    >
                      <SearchSelect
                        v-model="countItem.ingredient_id"
                        class="inventory-count-items-table__ingredient"
                        :disabled="countItemsDisabled"
                        :error="!!form.errors.value?.[`items.${index}.ingredient_id`]"
                        :error-messages="form.errors.value?.[`items.${index}.ingredient_id`]"
                        item-title="name"
                        item-value="id"
                        :items="meta.ingredients"
                        :label="t('inventory::attributes.inventory_counts.items.*.ingredient_id')"
                      />
                    </td>

                    <td style="width: 20%">
                      <div
                        v-if="ingredient(countItem)"
                        class="inventory-count-items-table__system-quantity"
                      >
                        <span>
                          {{ ingredient(countItem)?.current_stock }}
                        </span>
                        <small>{{ ingredient(countItem)?.unit }}</small>
                      </div>

                      <span
                        v-else
                        class="text-medium-emphasis"
                      >
                        -
                      </span>
                    </td>

                    <td style="width: 30%">
                      <VTextField
                        v-model="countItem.counted_quantity"
                        v-decimal-en
                        density="comfortable"
                        :disabled="countItemsDisabled || !countItem.ingredient_id"
                        :error="!!form.errors.value?.[`items.${index}.counted_quantity`]"
                        :error-messages="form.errors.value?.[`items.${index}.counted_quantity`]"
                        hide-details="auto"
                        :label="
                          t('inventory::attributes.inventory_counts.items.*.counted_quantity')
                        "
                        :suffix="ingredient(countItem)?.unit || undefined"
                      />
                    </td>

                    <td
                      class="inventory-count-items-table__action-cell"
                      style="width: 10%"
                    >
                      <VTooltip :text="t('inventory::inventory_counts.form.remove_item')">
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

          <VCardActions class="inventory-count-items-card__actions">
            <VTooltip :text="t('inventory::inventory_counts.form.add_item')">
              <template #activator="{ props: tooltipProps }">
                <span
                  class="d-inline-flex"
                  v-bind="tooltipProps"
                >
                  <VBtn
                    color="primary"
                    :disabled="countItemsDisabled"
                    prepend-icon="tabler-plus"
                    variant="tonal"
                    @click="addItem"
                  >
                    {{ t('inventory::inventory_counts.form.add_item') }}
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
.inventory-count-items {
  padding: 0 20px 20px;
  overflow: visible;
}

.inventory-count-items-table {
  overflow: visible;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}

.inventory-count-items-table table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}

.inventory-count-items-table th {
  padding: 14px 16px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: transparent;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.8125rem;
  font-weight: 700;
  text-align: start;
  white-space: nowrap;
}

.inventory-count-items-table td {
  position: relative;
  padding: 12px 16px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  vertical-align: top;
}

.inventory-count-items-table tbody tr:last-child td {
  border-bottom: 0;
}

.inventory-count-items-table__row:focus-within {
  position: relative;
  z-index: 40;
}

.inventory-count-items-table__number-cell {
  width: 64px;
  color: rgb(var(--v-theme-on-surface));
  font-weight: 700;
}

.inventory-count-items-table__ingredient-cell {
  min-width: 200px;
  overflow: visible;
}

.inventory-count-items-table__ingredient {
  position: relative;
}

.inventory-count-items-table__ingredient:focus-within {
  z-index: 50;
}

.inventory-count-items-table__system-quantity {
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

.inventory-count-items-table__system-quantity span {
  font-weight: 700;
}

.inventory-count-items-table__system-quantity small {
  color: rgb(var(--v-theme-on-surface-variant));
  font-size: 0.75rem;
}

.inventory-count-items-table__action-cell {
  width: 88px;
  text-align: center;
}

.inventory-count-items-card__actions {
  justify-content: flex-end;
  padding: 0 20px 20px;
}

.inventory-count-items-card,
.inventory-count-items-card :deep(.v-card-text) {
  overflow: visible;
}

.inventory-count-items-card :deep(.search-select__panel) {
  z-index: 1000;
}
</style>
