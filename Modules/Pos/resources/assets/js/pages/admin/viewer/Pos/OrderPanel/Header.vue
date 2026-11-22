<script lang="ts" setup>
import type { UseCart } from '@/modules/cart/composables/cart.ts'
import type { PosCustomerLoadOptions, PosForm, PosMeta } from '@/modules/pos/contracts/posViewer.ts'
import { watchDebounced } from '@vueuse/core'
import { computed, nextTick, ref, toRefs, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import SearchSelect from '@/modules/core/components/Form/SearchSelect.vue'
import PosExtensionRenderer from '@/modules/pos/components/PosExtensionRenderer.vue'
import AvailableGiftsDialog from '@/modules/pos/pages/admin/viewer/Pos/Dialogs/AvailableGifts/Index.vue'
import RewardsDialog from '@/modules/pos/pages/admin/viewer/Pos/Dialogs/Rewards/Index.vue'
import AddCustomerDialog from '@/modules/pos/pages/admin/viewer/Pos/OrderPanel/AddCustomerDialog.vue'

const props = defineProps<{
  form: PosForm
  meta: PosMeta
  cart: UseCart
  pendingOrderTypeSelection?: string | null
  loadCustomers: (options?: PosCustomerLoadOptions) => Promise<void>
}>()

const emit = defineEmits<{
  (e: 'pending-order-type-resolved'): void
}>()

const { t } = useI18n()
const { can } = useAuth()

const { processing, data, addCustomer, removeCustomer, storeOrderType, removeOrderType } =
  props.cart

const showRewardsDialog = ref(false)
const showGiftDialog = ref(false)
const showAddCustomerDialog = ref(false)
const createdCustomers = ref<Record<string, any>[]>([])
const customerSelect = ref<InstanceType<typeof SearchSelect> | null>(null)
const customerSearch = ref('')
const { form } = toRefs(props)
const isDeliveryOrder = computed(() => data.value.orderType?.id === 'delivery')
const selectedCustomer = computed({
  get: () => data.value.customer,
  set: (value) => {
    void updateCustomer(value).catch(() => undefined)
  },
})

const customerItems = computed(() => [
  ...createdCustomers.value,
  ...props.meta.customers.filter(
    (customer) => !createdCustomers.value.some((item) => item.id === customer.id),
  ),
])

const fallbackOrderTypeId = computed(() => {
  const fallback = props.meta.orderTypes.find((type) => !['delivery', 'dine_in'].includes(type.id))

  return fallback?.id ?? null
})

/** Persist the customer before resolving delivery so address extensions use draft state. */
async function updateCustomer(customer: Record<string, any> | null) {
  if (customer?.id === data.value.customer?.id) {
    return
  }

  await (customer ? addCustomer(customer.id, customer) : removeCustomer())

  if (
    customer?.id &&
    props.pendingOrderTypeSelection === 'delivery' &&
    data.value.orderType?.id !== 'delivery'
  ) {
    await storeOrderType('delivery')
    emit('pending-order-type-resolved')
    return
  }

  if (!customer?.id && data.value.orderType?.id === 'delivery') {
    await (fallbackOrderTypeId.value
      ? storeOrderType(fallbackOrderTypeId.value)
      : removeOrderType())
  }
}

watch(
  () => props.pendingOrderTypeSelection,
  async (value) => {
    if (value !== 'delivery' || data.value.customer?.id) {
      return
    }

    await nextTick()
    customerSelect.value?.open()
  },
  { immediate: true },
)

watch(
  isDeliveryOrder,
  (value) => {
    if (value) {
      form.value.waiter = null
    }
  },
  { immediate: true },
)

watchDebounced(
  customerSearch,
  async (value) => {
    await props.loadCustomers({ reset: true, search: value || '' })
  },
  { debounce: 300, maxWait: 700 },
)

function showAvailableGifts() {
  showGiftDialog.value = true
}

function showRewards() {
  if (can('admin.loyalty_gifts.index_rewards')) {
    showRewardsDialog.value = true
  }
}

function onCustomerCreated(customer: Record<string, any>) {
  if (!createdCustomers.value.some((item) => item.id === customer.id)) {
    createdCustomers.value.unshift(customer)
  }
  selectedCustomer.value = customer
}
</script>

<template>
  <div class="pos-customer-controls">
    <div class="pos-customer-card">
      <div class="pos-customer-row">
        <SearchSelect
          ref="customerSelect"
          v-model="selectedCustomer"
          class="pos-order-search pos-customer-search"
          item-title="name"
          item-value="id"
          :items="customerItems"
          :loading="form.loadingCustomers"
          :panel-min-width="300"
          :list-max-height="220"
          :readonly="processing"
          return-object
          @search="customerSearch = $event"
        >
          <template #activator="{ toggle, expanded }">
            <button
              type="button"
              class="pos-customer-trigger"
              :title="data.customer?.name"
              :disabled="processing"
              :aria-expanded="expanded"
              :aria-label="t('pos::pos_viewer.select_customer')"
              @click="toggle"
            >
              <span class="pos-customer-avatar">
                <VIcon
                  icon="tabler-user"
                  size="22"
                />
              </span>
              <span class="pos-customer-identity">
                <span class="pos-context-label">{{ t('user::customers.customer') }}</span>
                <strong>{{ data.customer?.name || t('pos::pos_viewer.select_customer') }}</strong>
              </span>
              <VIcon
                icon="tabler-chevron-down"
                size="18"
              />
            </button>
          </template>
          <template #item="{ item, selected }">
            <div class="pos-person-option">
              <span class="pos-person-option-icon">
                <VIcon
                  icon="tabler-user"
                  size="19"
                />
              </span>
              <span class="pos-person-option-name">{{ item.name }}</span>
              <VIcon
                v-if="selected"
                icon="tabler-check"
                color="primary"
                size="18"
              />
            </div>
          </template>
        </SearchSelect>
        <VMenu
          location="bottom end"
          :offset="8"
          content-class="pos-action-menu pos-action-menu--customer"
          width="280"
        >
          <template #activator="{ props: menuProps }">
            <VBtn
              v-bind="menuProps"
              class="pos-customer-actions"
              color="default"
              variant="text"
              icon="tabler-dots-vertical"
              :disabled="processing"
              :aria-label="
                t('user::customers.customer') + ' — ' + t('admin::admin.buttons.actions')
              "
            />
          </template>
          <VList class="pos-action-grid">
            <VListItem
              v-if="can('admin.customers.create')"
              prepend-icon="tabler-user-plus"
              :title="t('pos::pos_viewer.add_customer')"
              @click="showAddCustomerDialog = true"
            />
            <VListItem
              prepend-icon="tabler-users"
              :title="t('pos::pos_viewer.select_customer')"
              @click="customerSelect?.open()"
            />
            <VListItem
              v-if="can('admin.loyalty_gifts.index_rewards')"
              prepend-icon="tabler-coins"
              :disabled="!data.customer"
              :title="t('pos::pos_viewer.redeem_with_points')"
              @click="showRewards"
            />
            <VListItem
              prepend-icon="tabler-gift-card"
              :disabled="!data.customer"
              :title="t('pos::pos_viewer.view_available_gifts')"
              @click="showAvailableGifts"
            />
            <template v-if="data.customer">
              <VListItem
                class="pos-action-clear"
                base-color="error"
                prepend-icon="tabler-user-minus"
                :title="t('admin::admin.buttons.clear')"
                @click="selectedCustomer = null"
              />
            </template>
          </VList>
        </VMenu>
      </div>
      <PosExtensionRenderer
        :cart="cart"
        :form="form"
        :meta="meta"
        mount="order-panel-header"
      />
    </div>
  </div>

  <AvailableGiftsDialog
    v-if="data.customer?.id && showGiftDialog"
    v-model="showGiftDialog"
    :cart="cart"
    :customer-id="data.customer?.id"
  />

  <RewardsDialog
    v-if="data.customer?.id && can('admin.loyalty_gifts.index_rewards') && showRewardsDialog"
    v-model="showRewardsDialog"
    :customer-id="data.customer?.id"
  />

  <AddCustomerDialog
    v-if="can('admin.customers.create') && showAddCustomerDialog"
    v-model="showAddCustomerDialog"
    @created="onCustomerCreated"
  />
</template>

<style lang="scss" scoped>
.pos-order-search {
  flex: 1 1 0;
  min-width: 0;
}
</style>
