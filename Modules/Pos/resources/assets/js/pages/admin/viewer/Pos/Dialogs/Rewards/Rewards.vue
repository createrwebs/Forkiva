<script lang="ts" setup>
import type { AxiosError } from 'axios'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useConfirmDialog } from '@/modules/core/composables/confirmDialog.ts'
import { useLoyaltyGift } from '@/modules/loyalty/composables/loyaltyGift.ts'
import RewardItem from './RewardItem.vue'

const props = defineProps<{
  rewards: Record<string, any>[]
  customerId: number
  pointsBalance: number
}>()
const emit = defineEmits(['redeemed'])

const { t } = useI18n()
const { redeem } = useLoyaltyGift()
const toast = useToast()
const search = ref<string | null>('')
const eligibleRewardsCount = computed(
  () => props.rewards.filter((reward) => reward.is_eligible).length,
)
const filteredRewards = computed(() => {
  const query = (search.value ?? '').trim().toLocaleLowerCase()

  return [...props.rewards]
    .filter((reward) => {
      if (!query) return true

      return [reward.name, reward.description].some((value) =>
        String(value ?? '')
          .toLocaleLowerCase()
          .includes(query),
      )
    })
    .sort((first, second) => Number(second.is_eligible) - Number(first.is_eligible))
})
const processing = ref({
  id: 0,
  loading: false,
})

async function redeemReward(reward: Record<string, any>, qty = 1) {
  const confirmed = await useConfirmDialog({
    title: t('pos::pos_viewer.redeem_confirmation.title'),
    message: t('pos::pos_viewer.redeem_confirmation.message', {
      points_cost: reward.points_cost,
    }),
  })
  if (!confirmed) return

  try {
    processing.value.id = reward.id
    processing.value.loading = true
    const response = await redeem(props.customerId, reward.id, qty)
    toast.success(response.data.message)
    emit('redeemed')
  } catch (error) {
    console.error('error :', error)
    toast.error(
      (
        error as AxiosError<{
          message?: string
        }>
      ).response?.data?.message || t('core::errors.an_unexpected_error_occurred'),
    )
  } finally {
    processing.value.id = 0
    processing.value.loading = false
  }
}
</script>

<template>
  <section class="rewards-list">
    <header class="rewards-list__header">
      <div class="rewards-list__heading">
        <div>
          <h3>{{ t('pos::pos_viewer.available_rewards') }}</h3>
          <p>{{ t('pos::pos_viewer.available_rewards_description') }}</p>
        </div>
        <span class="rewards-list__count">
          <i></i>
          {{ eligibleRewardsCount }} {{ t('pos::pos_viewer.rewards_ready') }}
        </span>
      </div>
      <VTextField
        v-model="search"
        class="rewards-list__search"
        clearable
        density="compact"
        hide-details
        prepend-inner-icon="tabler-search"
        :aria-label="t('pos::pos_viewer.search_rewards')"
        :placeholder="t('pos::pos_viewer.search_rewards')"
        single-line
        variant="outlined"
      />
    </header>

    <div class="rewards-list__items">
      <RewardItem
        v-for="reward in filteredRewards"
        :key="reward.id"
        :points-balance="pointsBalance"
        :processing="processing"
        :reward="reward"
        @redeem-reward="redeemReward"
      />
      <div
        v-if="filteredRewards.length === 0"
        class="rewards-list__empty"
      >
        <VIcon
          icon="tabler-search-off"
          size="28"
        />
        <strong>{{ t('pos::pos_viewer.no_rewards_match') }}</strong>
        <span>{{ t('pos::pos_viewer.try_another_search') }}</span>
      </div>
    </div>
  </section>
</template>

<style lang="scss" scoped>
.rewards-list {
  display: flex;
  min-height: 0;
  flex: 1;
  flex-direction: column;
  margin-top: 34px;

  &__header {
    flex-shrink: 0;
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    align-items: stretch;
    gap: 16px;

    h3 {
      color: rgb(var(--v-theme-on-surface));
      font-size: 1.2rem;
      font-weight: 800;
      letter-spacing: -0.02em;
    }

    p {
      margin-top: 3px;
      color: rgba(var(--v-theme-on-surface), 0.72);
      font-size: 0.8125rem;
      line-height: 1.4;
    }
  }

  &__heading {
    display: flex;
    align-items: flex-start;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 16px;
  }

  &__count {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 6px;
    padding: 7px 10px;
    border-radius: 999px;
    color: rgb(var(--v-theme-success));
    background: rgba(var(--v-theme-success), 0.1);
    font-size: 0.75rem;
    font-weight: 750;

    i {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: rgb(var(--v-theme-success));
    }
  }

  &__search {
    :deep(.v-field) {
      border-radius: 12px;
      background: rgb(var(--v-theme-surface));
    }

    :deep(.v-field__input) {
      min-height: 42px;
      padding-block: 7px;
      font-size: 0.8125rem;
    }
  }

  &__items {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-content: start;
    gap: 12px;
    min-height: 0;
    margin-top: 18px;
    padding: 2px 4px 6px;
    overflow-y: auto;
    overscroll-behavior: contain;
    scrollbar-width: thin;
  }

  &__empty {
    grid-column: 1 / -1;
    display: flex;
    min-height: 245px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: rgba(var(--v-theme-on-surface), 0.45);
    text-align: center;

    strong {
      margin-top: 10px;
      color: rgb(var(--v-theme-on-surface));
      font-size: 0.875rem;
    }

    span {
      margin-top: 3px;
      font-size: 0.75rem;
    }
  }
}

@media (max-width: 800px) {
  .rewards-list {
    margin-top: 26px;

    &__items {
      overflow: visible;
    }

    &__header {
      grid-template-columns: 1fr;
      align-items: stretch;

      p {
        display: none;
      }
    }

    &__search {
      width: 100%;
    }
  }
}
@media (max-width: 520px) {
  .rewards-list__items {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
