<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import BlockInfo from '@/modules/core/components/BlockInfo.vue'
import TableEnum from '@/modules/core/components/Table/Partials/TableEnum.vue'
import TableMoney from '@/modules/core/components/Table/Partials/TableMoney.vue'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'

const props = defineProps<{
  delivery: Record<string, any> | null
}>()

const { t } = useI18n()

const addressLines = computed(() => {
  const address = props.delivery?.customer_address

  if (!address) {
    return []
  }

  return [
    address.label,
    address.contactName,
    address.phone,
    address.fullAddress ||
      [address.line1, address.line2, address.city, address.state, address.postalCode]
        .filter(Boolean)
        .join(', '),
  ].filter(Boolean)
})

const pricingSnapshot = computed(() => props.delivery?.pricing_snapshot?.pricing || null)
const pricingRule = computed(() => pricingSnapshot.value?.rule || null)
const resolvedZone = computed(
  () => props.delivery?.zone || props.delivery?.pricing_snapshot?.zone || null,
)
</script>

<template>
  <VCol
    v-if="delivery"
    cols="12"
  >
    <VCard>
      <VCardTitle>
        {{ t('delivery::deliveries.delivery') }}
      </VCardTitle>
      <VCardText>
        <VRow class="border-b-dashed">
          <VCol
            cols="12"
            md="3"
          >
            <BlockInfo :title="t('delivery::attributes.order_deliveries.status')">
              <TableStatus :item="delivery" />
            </BlockInfo>
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <BlockInfo :title="t('delivery::attributes.order_deliveries.delivery_method')">
              <TableEnum
                column="delivery_method"
                :item="delivery"
              />
            </BlockInfo>
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <BlockInfo
              :title="t('delivery::attributes.order_deliveries.payment_collection_channel')"
            >
              <TableEnum
                v-if="delivery.payment_collection_channel"
                column="payment_collection_channel"
                :item="delivery"
              />
              <span v-else>-</span>
            </BlockInfo>
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <BlockInfo :title="t('delivery::deliveries.table.fee_amount')">
              <TableMoney
                column="fee_amount"
                :item="delivery"
              />
            </BlockInfo>
          </VCol>
        </VRow>

        <VRow class="border-b-dashed">
          <VCol
            cols="12"
            md="6"
          >
            <BlockInfo :title="t('delivery::attributes.order_deliveries.dispatch_status')">
              <TableEnum
                v-if="delivery.dispatch_status"
                column="dispatch_status"
                :item="delivery"
              />
              <span v-else>-</span>
            </BlockInfo>
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <BlockInfo :title="t('delivery::attributes.order_deliveries.customer_address_id')">
              <div class="d-flex flex-column ga-1">
                <span
                  v-for="(line, index) in addressLines"
                  :key="`order-delivery-address-${index}`"
                  class="font-weight-medium"
                >
                  {{ line }}
                </span>
                <span v-if="addressLines.length === 0">-</span>
              </div>
            </BlockInfo>
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <BlockInfo
              :title="t('delivery::attributes.order_deliveries.note')"
              :value="delivery.note || '-'"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <BlockInfo
              :title="t('delivery::attributes.order_deliveries.zone_id')"
              :value="resolvedZone?.name || '-'"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <BlockInfo
              :title="t('delivery::attributes.order_deliveries.driver_id')"
              :value="delivery.driver?.employee?.name || '-'"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <BlockInfo :title="t('delivery::attributes.order_deliveries.cod_amount')">
              <span class="font-weight-medium">
                {{ delivery.cod_amount?.formatted || '-' }}
              </span>
            </BlockInfo>
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <BlockInfo
              :title="t('delivery::attributes.order_deliveries.assigned_at')"
              :value="delivery.assigned_at || '-'"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <BlockInfo
              :title="t('delivery::attributes.order_deliveries.returned_at')"
              :value="delivery.returned_at || '-'"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <BlockInfo :title="t('delivery::attributes.delivery_cash_settlements.status')">
              <span class="font-weight-medium">
                {{ delivery.cash_settlement?.status?.name || '-' }}
              </span>
            </BlockInfo>
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <BlockInfo :title="t('delivery::deliveries.earning_entries')">
              <span class="font-weight-medium">
                {{ delivery.earning_entries?.length || 0 }}
              </span>
            </BlockInfo>
          </VCol>
        </VRow>

        <VRow class="border-b-dashed">
          <VCol
            cols="12"
            md="3"
          >
            <BlockInfo :title="t('delivery::attributes.delivery_pricing_rules.base_fee')">
              <span class="font-weight-medium">
                {{ pricingRule?.baseFee?.formatted || '-' }}
              </span>
            </BlockInfo>
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <BlockInfo
              :title="t('delivery::attributes.delivery_pricing_rules.free_delivery_above')"
            >
              <span class="font-weight-medium">
                {{ pricingRule?.freeDeliveryAbove?.formatted || '-' }}
              </span>
            </BlockInfo>
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <BlockInfo
              :title="t('delivery::attributes.delivery_pricing_rules.small_order_threshold')"
            >
              <span class="font-weight-medium">
                {{ pricingRule?.smallOrderThreshold?.formatted || '-' }}
              </span>
            </BlockInfo>
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <BlockInfo :title="t('delivery::deliveries.table.small_order_surcharge')">
              <span class="font-weight-medium">
                {{ pricingSnapshot?.smallOrderSurcharge?.formatted || '-' }}
              </span>
            </BlockInfo>
          </VCol>
        </VRow>

        <VRow class="border-b-dashed">
          <VCol
            cols="12"
            md="4"
          >
            <BlockInfo
              :title="t('delivery::attributes.order_deliveries.ready_for_pickup_at')"
              :value="delivery.ready_for_pickup_at || '-'"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <BlockInfo
              :title="t('delivery::attributes.order_deliveries.picked_up_at')"
              :value="delivery.picked_up_at || '-'"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <BlockInfo
              :title="t('delivery::attributes.order_deliveries.delivered_at')"
              :value="delivery.delivered_at || '-'"
            />
          </VCol>
        </VRow>

        <VRow>
          <VCol
            cols="12"
            md="4"
          >
            <BlockInfo
              :title="t('delivery::attributes.order_deliveries.failed_at')"
              :value="delivery.failed_at || '-'"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <BlockInfo
              :title="t('delivery::attributes.order_deliveries.cancelled_at')"
              :value="delivery.cancelled_at || '-'"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <BlockInfo
              :title="t('admin::admin.table.updated_at')"
              :value="delivery.updated_at || '-'"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>
  </VCol>
</template>
