<script lang="ts" setup>
import type { PosForm, PosMeta } from '@/modules/pos/contracts/posViewer.ts'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'

const props = defineProps<{
  form: PosForm
  meta: PosMeta
  active?: string | null
  showCustomerViewer?: boolean
  showAssignWaiter?: boolean
  processing?: boolean
}>()

defineEmits<{
  (e: 'on-click-action', value: string): void
}>()

const { t } = useI18n()
const { can, canAny } = useAuth()

const hasOrderTypeDineIn = computed(() =>
  props.meta.orderTypes?.some((type) => type.id === 'dine_in'),
)

const actions = [
  {
    id: 'table_viewer',
    icon: 'tabler-armchair',
    visible: hasOrderTypeDineIn.value && can('admin.tables.viewer'),
    color: '#8e44ad',
  },
  {
    id: 'orders',
    icon: 'tabler-clipboard-list',
    color: '#2980b9',
    visible: canAny(['admin.orders.active', 'admin.orders.upcoming']),
  },
  {
    id: 'manage_cash_movement',
    icon: 'tabler-cash',
    color: '#16a085',
    visible: can('admin.pos_cash_movements.create'),
  },
]
</script>

<template>
  <nav
    class="pos-operation-tabs"
    :aria-label="t('admin::admin.buttons.actions')"
  >
    <template
      v-for="action in actions"
      :key="action.id"
    >
      <button
        v-if="action.visible"
        type="button"
        :class="{ active: active === action.id }"
        :aria-pressed="active === action.id"
        @click="$emit('on-click-action', action.id)"
      >
        <VIcon
          :icon="action.icon"
          size="22"
        />
        <span>
          {{
            action.id === 'table_viewer'
              ? t('seatingplan::tables.tables')
              : t(`pos::pos_viewer.actions.${action.id}`)
          }}
        </span>
      </button>
    </template>
    <button
      v-if="showAssignWaiter"
      type="button"
      :disabled="processing"
      :title="t('seatingplan::tables.assign_waiter')"
      @click="$emit('on-click-action', 'assign_waiter')"
    >
      <VIcon
        icon="tabler-user-pentagon"
        size="22"
      />
      <span>{{ form.waiter?.name || t('seatingplan::tables.assign_waiter') }}</span>
    </button>
    <button
      v-if="showCustomerViewer"
      type="button"
      @click="$emit('on-click-action', 'customer_viewer')"
    >
      <VIcon
        icon="tabler-device-desktop"
        size="22"
      />
      <span>{{ t('pos::pos_viewer.customer_viewer') }}</span>
    </button>
  </nav>
</template>
