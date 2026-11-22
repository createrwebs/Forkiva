<script lang="ts" setup>
import type { AxiosError } from 'axios'
import { computed, onBeforeUnmount, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useKitchenViewer } from '@/modules/pos/composables/kitchenViewer'
import type { KitchenOrder } from '@/modules/pos/contracts/kitchen'
import {
  elapsedLabel,
  isDelayed,
  kitchenState,
  nextOrderAction,
} from '@/modules/pos/utils/kitchenBoard'
import OrderProduct from './OrderProduct.vue'
import StatusBadge from './StatusBadge.vue'

const props = defineProps<{
  effect?: 'new' | 'updated' | null
  order: KitchenOrder
  now: number
  delayMinutes: number
  disabled: boolean
}>()
const emit = defineEmits<{
  'order-updated': [payload: Record<string, unknown>]
  processing: [active: boolean]
  'action-failed': []
}>()
const { t } = useI18n()
const toast = useToast()
const { moveToNextStatus } = useKitchenViewer()
const processing = ref(false)
const processingItem = ref<number | null>(null)
const actionError = ref(false)
let disposed = false
const state = computed(() => kitchenState(props.order))
const delayed = computed(() => isDelayed(props.order, props.now, props.delayMinutes))
const action = computed(() => nextOrderAction(props.order))
const items = computed(() =>
  props.order.products.filter((item) => !['cancelled', 'refunded'].includes(item.status.id)),
)
const itemCount = computed(() => items.value.reduce((sum, item) => sum + Number(item.quantity), 0))
const readyCount = computed(() =>
  items.value
    .filter((item) => ['ready', 'served'].includes(item.status.id))
    .reduce((sum, item) => sum + Number(item.quantity), 0),
)
const actionLabel = computed(() =>
  action.value ? t(`pos::kds.order_actions.${action.value.stage}`) : '',
)
async function advance(ids: number[], itemId: number | null = null) {
  if (processing.value || props.disabled || !ids.length) return
  processing.value = true
  processingItem.value = itemId
  actionError.value = false
  emit('processing', true)
  try {
    const response = await moveToNextStatus(props.order.id, ids)
    if (!disposed) emit('order-updated', response.data.body)
  } catch (error) {
    if (disposed) return
    actionError.value = true
    emit('action-failed')
    toast.error(
      (error as AxiosError<{ message?: string }>).response?.data?.message ||
        t('core::errors.an_unexpected_error_occurred'),
    )
  } finally {
    if (!disposed) {
      processing.value = false
      processingItem.value = null
      emit('processing', false)
    }
  }
}
onBeforeUnmount(() => {
  disposed = true
  if (processing.value) emit('processing', false)
})
</script>

<template>
  <article
    class="kds-ticket"
    :class="[`kds-state--${delayed ? 'delayed' : state}`, effect && `kds-ticket--${effect}`]"
    :aria-label="`${t('pos::pos_viewer.order')} ${order.order_number}`"
    :data-order-id="order.id"
  >
    <header class="kds-ticket__top">
      <div class="kds-ticket__header">
        <div class="kds-ticket__identity">
          <h3>#{{ order.order_number }}</h3>
          <span
            class="kds-type-badge"
            :class="`kds-type--${order.type.id}`"
          >
            <VIcon
              :icon="order.type.icon || 'tabler-receipt'"
              size="15"
            />
            {{ order.type.name }}
          </span>
        </div>
        <div class="kds-ticket__timer">
          <span class="kds-ticket__timer-label">
            <VIcon
              icon="tabler-clock"
              size="13"
            />
            {{
              t(
                ['ready', 'completed'].includes(state) && order.prepared_at
                  ? 'pos::kds.prepared_in'
                  : 'pos::kds.elapsed',
              )
            }}
          </span>
          <strong>
            {{ elapsedLabel(order, now, (days) => t('pos::kds.days_elapsed', { days })) }}
          </strong>
        </div>
      </div>
      <div
        v-if="order.customer?.name || order.table?.name"
        class="kds-ticket__details"
      >
        <div
          v-if="order.customer?.name"
          class="kds-ticket__customer"
        >
          <VIcon
            icon="tabler-user"
            size="15"
          />
          <span>{{ order.customer.name }}</span>
        </div>
        <span
          v-if="order.table?.name"
          class="kds-table-badge"
        >
          <VIcon
            icon="tabler-armchair"
            size="15"
          />
          {{ order.table.name }}
        </span>
      </div>
      <div class="kds-ticket__status">
        <div class="kds-ticket__states">
          <StatusBadge
            :status="state"
            :label="t(`pos::kds.states.${state}`)"
          />
          <StatusBadge
            v-if="delayed"
            status="delayed"
            :label="t('pos::kds.states.delayed')"
          />
        </div>
        <span
          v-if="effect"
          class="kds-change-label"
          :class="`kds-change-label--${effect}`"
          role="status"
        >
          <VIcon
            :icon="effect === 'new' ? 'tabler-bell-ringing' : 'tabler-refresh'"
            size="15"
          />
          {{
            t(
              `pos::pos_viewer.kitchen_order_alerts.${effect === 'new' ? 'new_order' : 'updated_order'}`,
            )
          }}
        </span>
        <span
          v-else
          class="kds-ticket__estimate"
        >
          {{
            order.prep_time_minutes
              ? t('pos::kds.target', { minutes: order.prep_time_minutes })
              : t('pos::kds.no_estimate')
          }}
        </span>
      </div>
      <div
        class="kds-progress"
        role="progressbar"
        :aria-label="t('pos::kds.item_progress')"
        :aria-valuenow="readyCount"
        :aria-valuemin="0"
        :aria-valuemax="itemCount || 1"
      >
        <span :style="{ width: `${itemCount ? (readyCount / itemCount) * 100 : 0}%` }" />
      </div>
    </header>
    <p
      v-if="order.notes"
      class="kds-order-note"
    >
      <VIcon
        icon="tabler-message-2"
        size="17"
      />
      <span>{{ order.notes }}</span>
    </p>
    <div class="kds-ticket__items">
      <OrderProduct
        v-for="item in order.products"
        :key="item.id"
        :item="item"
        :disabled="disabled || processing || state === 'completed'"
        :processing="processing && processingItem === item.id"
        @advance="advance([$event], $event)"
      />
      <p
        v-if="!order.products.length"
        class="kds-no-items"
      >
        {{ t('pos::kds.no_visible_items') }}
      </p>
    </div>
    <div class="kds-ticket__footer">
      <div class="kds-ticket__reference">
        <span :title="order.reference_no">{{ order.reference_no }}</span>
        <span :title="`${order.date} ${order.time}`">{{ order.date }} · {{ order.time }}</span>
      </div>
      <div class="kds-ticket__progress-copy">
        <span>{{ t('pos::kds.ready_count', { ready: readyCount, total: itemCount }) }}</span>
      </div>
      <p
        v-if="actionError"
        class="kds-action-error"
        role="alert"
      >
        {{ t('pos::kds.action_error') }}
      </p>
      <VBtn
        v-if="action"
        class="kds-order-action"
        :class="`kds-order-action--${action.stage}`"
        type="button"
        variant="flat"
        :color="
          action.stage === 'pending'
            ? '#2463c5'
            : action.stage === 'preparing'
              ? '#18754d'
              : '#475569'
        "
        :loading="processing && processingItem === null"
        :disabled="disabled || processing"
        @click="advance(action.ids)"
      >
        <VIcon
          :icon="
            action.stage === 'pending'
              ? 'tabler-player-play'
              : action.stage === 'preparing'
                ? 'tabler-check'
                : 'tabler-checks'
          "
          size="20"
        />
        {{ actionLabel }}
      </VBtn>
      <div
        v-else
        class="kds-ticket__finished"
      >
        <VIcon
          icon="tabler-circle-check"
          size="20"
        />
        {{ t(state === 'completed' ? 'pos::kds.kitchen_completed' : 'pos::kds.no_kitchen_action') }}
      </div>
    </div>
  </article>
</template>
