<script lang="ts" setup>
import type { useQintrix } from '@/modules/printer/composables/qintrix.ts'
import { useI18n } from 'vue-i18n'
import TableEnum from '@/modules/core/components/Table/Partials/TableEnum.vue'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'
import { convertHexToRgba } from '@/modules/core/utils/color.ts'
import OrderActions from './OrderActions.vue'

const props = withDefaults(
  defineProps<{
    order: Record<string, any>
    branchId: number | null
    qintrix: ReturnType<typeof useQintrix>
    isTableViewer?: boolean
    isBillingMerged?: boolean
    collapsible?: boolean
    expanded?: boolean
  }>(),
  {
    isBillingMerged: false,
    isTableViewer: false,
    collapsible: false,
    expanded: true,
  },
)

defineEmits<{
  (e: 'refresh' | 'toggle'): void
  (
    e: 'store-payment' | 'refund-order' | 'cancel-order' | 'view-order' | 'print-order',
    value: string | number,
  ): void
  (e: 'init-order', response: Record<string, any>): void
}>()

const { t } = useI18n()
const detailsId = useId()
const showDetails = computed(() => !props.collapsible || props.expanded)
const showAllItems = ref(false)
const productNames = computed<string[]>(() => {
  const summary = String(props.order.products.names || '')
  const names = summary.split(',').map((name) => name.trim())
  // The legacy summary joins names with commas. Preserve it if a name itself contains a comma.
  return names.length === props.order.products.count ? names : summary ? [summary] : []
})
const visibleProducts = computed(() =>
  showAllItems.value ? productNames.value : productNames.value.slice(0, 4),
)
watch(
  () => props.order.id,
  () => {
    showAllItems.value = false
  },
)
</script>

<template>
  <VCard
    class="mt-2"
    :class="{ 'table-order': isTableViewer, 'table-order--expanded': isTableViewer && showDetails }"
  >
    <VCardText :class="{ 'pa-3': !isTableViewer, 'pa-1': isTableViewer }">
      <template v-if="isTableViewer">
        <component
          :is="collapsible ? 'button' : 'div'"
          class="table-order-header"
          :class="{ 'table-order-header--interactive': collapsible }"
          :type="collapsible ? 'button' : undefined"
          :aria-expanded="collapsible ? showDetails : undefined"
          :aria-controls="collapsible ? detailsId : undefined"
          @click="collapsible && $emit('toggle')"
        >
          <span class="table-order-overview">
            <span class="table-order-title-row">
              <strong class="table-order-number">
                {{
                  t('pos::pos_viewer.table_details.order_number', { number: order.order_number })
                }}
              </strong>
              <TableStatus :item="order" />
            </span>
            <span class="table-order-customer-line">
              <span class="table-order-customer">{{ order.customer_name }}</span>
              <span class="table-order-meta">
                <span>{{ t('pos::pos_viewer.table_details.items', order.products.count) }}</span>
                <span>{{ order.time }}</span>
                <span v-if="order.source_badge">{{ order.source_badge.name }}</span>
              </span>
            </span>
          </span>
          <span class="table-order-amount">
            <strong class="table-order-total">{{ order.total.formatted }}</strong>
            <TableEnum
              column="payment_status"
              :item="order"
            />
            <span
              v-if="order.payment_status.id === 'partially_paid'"
              class="table-order-due"
            >
              {{ t('pos::pos_viewer.due_amount') }}: {{ order.due_amount.formatted }}
            </span>
          </span>
          <span
            v-if="collapsible"
            class="table-order-disclosure"
          >
            <VIcon
              :icon="showDetails ? 'tabler-chevron-up' : 'tabler-chevron-down'"
              size="20"
              aria-hidden="true"
            />
          </span>
        </component>
      </template>
      <div
        v-else
        class="pb-3 ga-2 d-flex align-center justify-content-between w-100"
        :class="{ 'border-b-dashed': !isTableViewer }"
      >
        <div
          v-if="!isTableViewer"
          class="order-type d-flex align-center justify-center flex-column"
        >
          <div
            class="order-type-icon"
            :style="{ backgroundColor: convertHexToRgba(order.type.color, 0.1) }"
          >
            <VIcon
              :color="order.type.color"
              :icon="order.type.icon"
              size="35"
            />
          </div>

          <span class="order-type-name">{{ order.type.name }}</span>
        </div>

        <div
          class="order-details"
          :style="[isTableViewer ? 'width:100%' : '']"
        >
          <div class="d-flex justify-space-between">
            <span class="date">{{ order.time }}</span>

            <div class="total-container">
              <template v-if="order.payment_status.id === 'partially_paid'">
                <span class="total">
                  {{ order.due_amount.formatted }}
                </span>

                <span class="total-slash">/</span>
              </template>

              <span class="total">
                {{ order.total.formatted }}
              </span>
            </div>
          </div>

          <div class="d-flex mt-2 align-center justify-space-between">
            <div class="d-flex align-center gap-2 flex-wrap">
              <span class="order-number">
                #{{ order.order_number }} -
                {{ order.customer_name }}
              </span>

              <TableEnum
                v-if="order.source_badge"
                column="source_badge"
                :item="order"
              />
            </div>

            <div class="">
              <TableStatus :item="order" />
              &nbsp;
              <TableEnum
                column="payment_status"
                :item="order"
              />
            </div>
          </div>

          <div class="products">
            {{ t('pos::pos_viewer.count_of_items', { count: order.products.count }) }}:
            {{ order.products.names }}
          </div>

          <div
            v-if="order.scheduled_at"
            class="d-flex justify-end align-center"
          >
            <span class="scheduled-at-value">{{ order.scheduled_at }}</span>
          </div>
        </div>
      </div>

      <div :class="{ 'table-order-body': isTableViewer }">
        <div
          v-if="isTableViewer"
          :id="detailsId"
          v-show="showDetails"
          class="table-order-products"
        >
          <div class="table-order-products-heading">
            <h4>{{ t('pos::pos_viewer.table_details.order_items') }}</h4>
            <button
              v-if="productNames.length > 4"
              type="button"
              class="table-order-items-toggle"
              :aria-expanded="showAllItems"
              :aria-controls="`${detailsId}-items`"
              @click="showAllItems = !showAllItems"
            >
              {{
                showAllItems
                  ? t('pos::pos_viewer.table_details.show_less')
                  : t('pos::pos_viewer.table_details.show_all_items', {
                      count: order.products.count,
                    })
              }}
            </button>
          </div>
          <ul :id="`${detailsId}-items`">
            <li
              v-for="(name, index) in visibleProducts"
              :key="index"
            >
              {{ name }}
            </li>
          </ul>
          <div
            v-if="order.scheduled_at"
            class="table-order-scheduled"
          >
            <VIcon
              icon="tabler-calendar-time"
              size="16"
              aria-hidden="true"
            />
            {{ order.scheduled_at }}
          </div>
        </div>
        <OrderActions
          :branch-id="branchId"
          :is-billing-merged="isBillingMerged"
          :is-table-viewer="isTableViewer"
          :order="order"
          :qintrix="qintrix"
          @cancel-order="(orderId: number | string) => $emit('cancel-order', orderId)"
          @init-order="(response: Record<string, any>) => $emit('init-order', response)"
          @print-order="(orderId: number | string) => $emit('print-order', orderId)"
          @refresh="$emit('refresh')"
          @refund-order="(orderId: number | string) => $emit('refund-order', orderId)"
          @store-payment="(orderId: number | string) => $emit('store-payment', orderId)"
          @view-order="(orderId: number | string) => $emit('view-order', orderId)"
        />
      </div>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.table-order.v-card {
  margin: 0 !important;
  border: 1px dashed var(--pos-border);
  border-radius: 10px;
  box-shadow: none;
  background: var(--pos-surface);
  overflow: hidden;
}
.table-order--expanded.v-card {
  border-color: color-mix(in srgb, var(--pos-accent) 40%, var(--pos-border));
}
.table-order :deep(.v-card-text) {
  padding: 0 !important;
}
.table-order-header {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  inline-size: 100%;
  padding: 12px 14px;
  border: 0;
  text-align: start;
  font: inherit;
  color: var(--pos-text);
  background: transparent;
}
.table-order-header--interactive {
  cursor: pointer;
}
.table-order-header--interactive:hover {
  background: var(--pos-soft);
}
.table-order-header--interactive[aria-expanded='true'] {
  background: var(--pos-accent-soft);
}
.table-order-header--interactive:focus-visible {
  outline: 2px solid var(--pos-accent);
  outline-offset: -3px;
}
.table-order-overview {
  display: grid;
  gap: 5px;
  flex: 1;
  min-inline-size: 0;
}
.table-order-title-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}
.table-order-number {
  font-size: 16px;
  line-height: 1.4;
  font-weight: 700;
}
.table-order-customer-line {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 5px 12px;
}
.table-order-customer {
  font-size: 14px;
  font-weight: 500;
  line-height: 1.4;
  overflow-wrap: anywhere;
}
.table-order-meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px 10px;
  font-size: 12px;
  line-height: 1.5;
  color: var(--pos-muted);
}
.table-order-meta > span + span {
  border-inline-start: 1px solid var(--pos-border);
  padding-inline-start: 10px;
}
.table-order-amount {
  display: flex;
  align-items: flex-end;
  flex-direction: column;
  gap: 7px;
  max-inline-size: 45%;
  text-align: end;
}
.table-order-total {
  color: var(--pos-accent);
  font-size: 20px;
  line-height: 1.3;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}
.table-order-due {
  font-size: 11px;
  line-height: 1.4;
  color: var(--pos-muted);
  font-variant-numeric: tabular-nums;
}
.table-order-header :deep(.v-chip) {
  border-radius: 5px;
  font-size: 11px;
}
.table-order-header :deep(.v-chip .v-icon) {
  display: none;
}
.table-order-disclosure {
  display: flex;
  align-self: center;
  color: var(--pos-muted);
}
.table-order-body {
  padding: 0 14px 14px;
}
.table-order-products {
  padding-block: 12px;
  border-block-start: 1px solid var(--pos-border);
}
.table-order-products-heading {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  margin-block-end: 8px;
}
.table-order-products h4 {
  margin: 0;
  font-size: 12px;
  font-weight: 600;
  color: var(--pos-muted);
}
.table-order-products ul {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 6px 16px;
  list-style: none;
  margin: 0;
  padding: 0;
}
.table-order-products li {
  display: flex;
  align-items: baseline;
  gap: 8px;
  font-size: 13px;
  line-height: 1.5;
  color: var(--pos-text);
  overflow-wrap: anywhere;
}
.table-order-products li::before {
  content: '';
  flex: 0 0 4px;
  block-size: 4px;
  border-radius: 50%;
  background: var(--pos-muted);
}
.table-order-items-toggle {
  padding: 4px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
  color: var(--pos-accent);
}
.table-order-items-toggle:focus-visible {
  outline: 2px solid var(--pos-accent);
}
.table-order-scheduled {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--pos-muted);
  margin-block-start: 10px;
}
@media (max-width: 540px) {
  .table-order-header {
    gap: 8px;
    padding: 12px;
  }
  .table-order-number {
    font-size: 15px;
  }
  .table-order-customer {
    font-size: 13px;
  }
  .table-order-total {
    font-size: 18px;
  }
  .table-order-amount {
    max-inline-size: 43%;
  }
  .table-order-body {
    padding: 0 12px 12px;
  }
  .table-order-products ul {
    grid-template-columns: minmax(0, 1fr);
  }
  .table-order-disclosure {
    margin-inline-end: -4px;
  }
}
.date {
  color: rgba(var(--v-theme-on-surface), 0.4);
  font-size: 0.8rem;
}

.products {
  width: 50%;
  font-size: 0.7rem;
  color: rgba(var(--v-theme-on-surface), 0.4);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.4;
}

.total {
  font-weight: 700;
  font-size: 0.9rem;
}

.total-slash {
  color: rgba(var(--v-theme-on-surface), 0.5);
}

.order-number {
  font-size: 0.9rem;
  font-weight: bold;
}

.order-type {
  width: 15%;
}

.order-details {
  width: 85%;
}

.order-type-name {
  font-weight: 700;
  font-size: 0.7rem;
}

.order-type-icon {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  display: flex;
  justify-content: center;
  align-items: center;
}

.scheduled-at-value {
  font-size: 0.85rem;
  font-weight: 700;
}
</style>
