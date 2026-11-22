<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useLoyaltyGift } from '@/modules/loyalty/composables/loyaltyGift.ts'
import CustomerInfo from './Customer.vue'
import Empty from './Empty.vue'
import Rewards from './Rewards.vue'

const props = defineProps<{
  modelValue: boolean
  customerId: number
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'redeemed'): void
}>()

const { t } = useI18n()
const { getAllRewards } = useLoyaltyGift()
const loading = ref(false)
const isNotFound = ref(false)
const customer = ref<Record<string, unknown> | null>(null)
const rewards = ref<Record<string, unknown>[] | null>(null)

const dialogModel = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})

function close() {
  emit('update:modelValue', false)
}

onBeforeMount(async () => {
  loading.value = true
  const response = await getAllRewards(props.customerId)
  if (response.status === 200) {
    customer.value = response.data.customer
    rewards.value = response.data.eligible
  } else if (response.status === 404) {
    isNotFound.value = true
  }
  loading.value = false
})

async function redeemed() {
  const response = await getAllRewards(props.customerId)
  if (response.status === 200) {
    customer.value = response.data.customer
    rewards.value = response.data.eligible
    emit('redeemed')
  }
}
</script>

<template>
  <VDialog
    v-model="dialogModel"
    max-width="1100"
    scrollable
  >
    <VCard class="rewards-dialog">
      <VCardText class="rewards-dialog__body">
        <VBtn
          class="rewards-dialog__close"
          icon
          size="40"
          variant="text"
          :aria-label="t('admin::admin.buttons.close')"
          @click="close"
        >
          <VIcon
            icon="tabler-x"
            size="22"
          />
        </VBtn>

        <PageStateWrapper
          :loading="loading"
          :not-found="isNotFound"
        >
          <template v-if="customer || rewards">
            <div class="rewards-dialog__layout">
              <CustomerInfo
                v-if="customer"
                :customer="customer"
              />
              <main class="rewards-dialog__workspace">
                <header class="rewards-dialog__header">
                  <span class="rewards-dialog__icon">
                    <VIcon
                      icon="tabler-gift"
                      size="27"
                    />
                  </span>
                  <div>
                    <h2>{{ t('pos::pos_viewer.rewards_and_points') }}</h2>
                    <p>{{ t('pos::pos_viewer.turn_points_into_rewards') }}</p>
                  </div>
                </header>

                <Rewards
                  v-if="customer && rewards && rewards.length > 0"
                  :customer-id="customerId"
                  :points-balance="Number(customer.points_balance)"
                  :rewards="rewards"
                  @redeemed="redeemed"
                />
                <Empty v-else />
              </main>
            </div>
          </template>
          <template v-else>
            <Empty />
          </template>
        </PageStateWrapper>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.rewards-dialog {
  position: relative;
  max-height: min(700px, 90vh);
  overflow: hidden;
  border: 0 !important;
  border-radius: 22px !important;
  box-shadow: 0 28px 90px rgba(23, 34, 58, 0.24) !important;

  &__body {
    padding: 0 !important;
  }

  &__layout {
    display: grid;
    min-height: 620px;
    grid-template-columns: 330px minmax(0, 1fr);
    background: rgb(var(--v-theme-surface));
  }

  &__workspace {
    display: flex;
    min-width: 0;
    min-height: 0;
    flex-direction: column;
    padding: 34px 34px 30px;
  }

  &__header {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 15px;
    padding-inline-end: 48px;

    h2 {
      color: rgb(var(--v-theme-on-surface));
      font-size: 1.65rem;
      font-weight: 800;
      letter-spacing: -0.035em;
      line-height: 1.2;
    }

    p {
      margin-top: 4px;
      color: rgba(var(--v-theme-on-surface), 0.58);
      font-size: 0.875rem;
      line-height: 1.45;
    }
  }

  &__icon {
    display: grid;
    flex: 0 0 56px;
    height: 56px;
    place-items: center;
    border: 1px solid rgba(var(--v-theme-primary), 0.1);
    border-radius: 17px;
    color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.1);
  }

  &__close {
    position: absolute !important;
    z-index: 3;
    inset-block-start: 22px;
    inset-inline-end: 22px;
    color: rgba(var(--v-theme-on-surface), 0.65) !important;
    border-radius: 12px !important;

    &:hover {
      color: rgb(var(--v-theme-primary)) !important;
      background: rgba(var(--v-theme-primary), 0.08) !important;
    }
  }
}

@media (min-width: 801px) {
  .rewards-dialog {
    &__body {
      overflow: hidden !important;
    }

    &__layout {
      height: min(700px, 90dvh);
      min-height: 0;
    }

    :deep(.loyalty-pass) {
      min-height: 0;
      overflow-y: auto;
    }
  }
}

@media (max-width: 800px) {
  .rewards-dialog {
    max-height: 92vh;
    border-radius: 20px !important;

    &__layout {
      display: block;
      min-height: auto;
    }

    &__workspace {
      padding: 24px 18px 20px;
    }

    &__header {
      padding-inline-end: 38px;

      h2 {
        font-size: 1.25rem;
      }

      p {
        font-size: 0.75rem;
      }
    }

    &__icon {
      flex-basis: 46px;
      height: 46px;
      border-radius: 14px;
    }

    &__close {
      inset-block-start: 14px;
      inset-inline-end: 12px;
      color: rgb(var(--v-theme-on-surface)) !important;
      background: rgb(var(--v-theme-surface)) !important;
    }
  }
}
</style>
