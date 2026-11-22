<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'

const props = withDefaults(
  defineProps<{
    activeOrder: Record<string, any> | null
    refreshing: boolean
    variant?: 'action' | 'card'
  }>(),
  {
    variant: 'card',
  },
)

defineEmits<{
  (event: 'refresh'): void
}>()

const { t } = useI18n()
const trackingDialog = ref(false)
const isActionVariant = computed(() => props.variant === 'action')

const orderItems = computed<Record<string, any>[]>(() => {
  const items = props.activeOrder?.items

  return Array.isArray(items) ? items : []
})

const totalItems = computed(() => Math.max(Number(props.activeOrder?.items_count || 0), 0))
const orderLabel = computed(
  () => props.activeOrder?.order_number || props.activeOrder?.reference_no || '-',
)
const statusItem = computed(() => ({ status: props.activeOrder?.status || null }))
const completedItems = computed(
  () =>
    Number(props.activeOrder?.progress?.ready || 0) +
    Number(props.activeOrder?.progress?.served || 0),
)
const progressPercent = computed(() => {
  if (totalItems.value === 0) {
    return 0
  }

  return Math.min(100, Math.round((completedItems.value / totalItems.value) * 100))
})
const fallbackProgressSteps = computed(() => [
  {
    key: 'pending',
    status: {
      color: '#F59E0B',
      icon: 'tabler-hourglass',
      name: t('seatingplan::tables.table_order.tracking_pending'),
    },
    value: Number(props.activeOrder?.progress?.pending || 0),
  },
  {
    key: 'preparing',
    status: {
      color: '#8B5CF6',
      icon: 'tabler-chef-hat',
      name: t('seatingplan::tables.table_order.tracking_preparing'),
    },
    value: Number(props.activeOrder?.progress?.preparing || 0),
  },
  {
    key: 'ready',
    status: {
      color: '#10B981',
      icon: 'tabler-package',
      name: t('seatingplan::tables.table_order.tracking_ready'),
    },
    value: Number(props.activeOrder?.progress?.ready || 0),
  },
  {
    key: 'served',
    status: {
      color: '#14B8A6',
      icon: 'tabler-brand-airtable',
      name: t('seatingplan::tables.table_order.tracking_served'),
    },
    value: Number(props.activeOrder?.progress?.served || 0),
  },
])
const progressSteps = computed<Record<string, any>[]>(() => {
  const steps = props.activeOrder?.progress_steps

  return Array.isArray(steps) && steps.length > 0 ? steps : fallbackProgressSteps.value
})

function moneyFormatted(value: any) {
  return value?.formatted || '-'
}

function stepStyle(step: Record<string, any>) {
  return {
    '--track-status-color': step.status?.color || 'rgb(var(--v-theme-primary))',
  }
}

function itemOptions(item: Record<string, any>) {
  const options = item.options

  return Array.isArray(options) ? options : []
}

function optionValueText(option: Record<string, any>) {
  const values = Array.isArray(option.values) ? option.values : []

  return values
    .map((value) => value.label)
    .filter(Boolean)
    .join(', ')
}
</script>

<template>
  <section
    v-if="activeOrder"
    class="track-entry"
    :class="{ 'track-entry--action': isActionVariant }"
  >
    <button
      v-if="isActionVariant"
      :aria-label="t('seatingplan::tables.table_order.track_order')"
      class="track-action"
      type="button"
      @click="trackingDialog = true"
    >
      <VIcon
        icon="tabler-route"
        size="18"
      />
      <span>{{ t('seatingplan::tables.table_order.track_order') }}</span>
    </button>

    <button
      v-else
      class="track-entry__button"
      type="button"
      @click="trackingDialog = true"
    >
      <span class="track-entry__icon">
        <VIcon
          icon="tabler-route"
          size="21"
        />
      </span>

      <span class="track-entry__copy">
        <strong>{{ t('seatingplan::tables.table_order.track_order') }}</strong>
        <span>#{{ orderLabel }} · {{ activeOrder.status?.name }}</span>
      </span>

      <span class="track-entry__chevron">
        <VIcon
          icon="tabler-chevron-right"
          size="20"
        />
      </span>
    </button>

    <VDialog
      v-model="trackingDialog"
      class="table-order-dialog"
      max-width="520"
      scrollable
    >
      <VCard class="sheet-card tracking-dialog">
        <header class="tracking-dialog__header">
          <div class="tracking-dialog__headline">
            <div class="tracking-dialog__title">
              <span>{{ t('seatingplan::tables.table_order.track_order') }}</span>
              <h2>#{{ orderLabel }}</h2>
            </div>

            <TableStatus :item="statusItem" />
          </div>

          <div class="tracking-dialog__analytics">
            <div class="tracking-dialog__metric">
              <VIcon
                icon="tabler-shopping-bag"
                size="18"
              />
              <span>{{ t('seatingplan::tables.table_order.items_count', totalItems) }}</span>
            </div>

            <div class="tracking-dialog__metric">
              <VIcon
                icon="tabler-receipt"
                size="18"
              />
              <span>{{ moneyFormatted(activeOrder.total) }}</span>
            </div>

            <div class="tracking-dialog__metric">
              <VIcon
                icon="tabler-progress-check"
                size="18"
              />
              <span>{{ progressPercent }}%</span>
            </div>
          </div>
        </header>

        <VCardText class="tracking-dialog__body">
          <div class="tracking-dialog__progress">
            <div class="tracking-dialog__progress-track">
              <span :style="{ inlineSize: `${progressPercent}%` }" />
            </div>
          </div>

          <div class="tracking-dialog__steps">
            <div
              v-for="step in progressSteps"
              :key="step.key"
              class="tracking-dialog__step"
              :class="{ 'tracking-dialog__step--active': step.value > 0 }"
              :style="stepStyle(step)"
            >
              <VIcon
                :icon="step.status?.icon || 'tabler-circle'"
                size="18"
              />
              <span>{{ step.status?.name }}</span>
              <strong>{{ step.value }}</strong>
            </div>
          </div>

          <div class="tracking-dialog__items">
            <div
              v-for="item in orderItems"
              :key="item.id"
              class="tracking-dialog__item"
            >
              <div class="tracking-dialog__item-content">
                <div class="tracking-dialog__item-main">
                  <strong>{{ item.name }}</strong>
                  <span>
                    {{ t('seatingplan::tables.table_order.quantity') }} {{ item.quantity }}
                  </span>
                </div>

                <div
                  v-if="itemOptions(item).length > 0"
                  class="tracking-dialog__item-options"
                >
                  <span
                    v-for="option in itemOptions(item)"
                    :key="option.id"
                    class="tracking-dialog__item-option"
                  >
                    <strong>{{ option.name }}</strong>
                    <em>{{ optionValueText(option) }}</em>
                  </span>
                </div>
              </div>

              <TableStatus :item="{ status: item.status }" />
            </div>
          </div>
        </VCardText>

        <footer class="tracking-dialog__footer sheet-footer sheet-footer--actions">
          <VBtn
            class="dialog-secondary-action"
            color="secondary"
            size="large"
            variant="tonal"
            @click="trackingDialog = false"
          >
            <VIcon
              icon="tabler-x"
              start
            />
            {{ t('seatingplan::tables.table_order.close') }}
          </VBtn>

          <VBtn
            class="tracking-dialog__refresh dialog-primary-action"
            color="primary"
            :loading="refreshing"
            size="large"
            variant="tonal"
            @click="$emit('refresh')"
          >
            <VIcon
              icon="tabler-refresh"
              start
            />
            {{ t('seatingplan::tables.table_order.refresh_status') }}
          </VBtn>
        </footer>
      </VCard>
    </VDialog>
  </section>
</template>
