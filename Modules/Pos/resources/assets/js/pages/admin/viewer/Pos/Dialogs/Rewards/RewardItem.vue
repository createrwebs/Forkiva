<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'

const props = defineProps<{
  reward: Record<string, any>
  processing: Record<string, any>
  pointsBalance: number
}>()
const emit = defineEmits(['redeem-reward'])

const { t } = useI18n()
const { can } = useAuth()
const rewardImage = computed(() => {
  const icon = props.reward.icon

  if (icon?.type !== 'image' && !icon?.mime?.startsWith('image/')) return null

  return icon.preview_image_url || icon.url || null
})
const pointsNeeded = computed(() =>
  Math.max(0, Number(props.reward.points_cost) - props.pointsBalance),
)
const progress = computed(() => {
  const cost = Number(props.reward.points_cost)

  return cost > 0 ? Math.min(100, Math.max(0, (props.pointsBalance / cost) * 100)) : 100
})

function redeemReward() {
  if (can('admin.loyalty_gifts.redeem')) {
    emit('redeem-reward', props.reward, 1)
  }
}
</script>

<template>
  <article
    class="reward-item"
    :class="{ 'reward-item--eligible': reward.is_eligible }"
  >
    <div class="reward-item__header">
      <div class="reward-item__media">
        <VImg
          v-if="rewardImage"
          :alt="reward.name"
          class="reward-item__image"
          cover
          height="40"
          :src="rewardImage"
          width="40"
        >
          <template #error>
            <VIcon
              icon="tabler-gift"
              size="22"
            />
          </template>
        </VImg>
        <VIcon
          v-else
          icon="tabler-gift"
          size="22"
        />
      </div>

      <div class="reward-item__content">
        <strong>{{ reward.name }}</strong>
        <p>{{ reward.description }}</p>
        <span
          v-if="reward.is_eligible"
          class="reward-item__eligible"
        >
          <VIcon
            icon="tabler-circle-filled"
            size="10"
          />
          {{ t('pos::pos_viewer.ready') }}
        </span>
      </div>
    </div>
    <div class="reward-item__footer">
      <div class="reward-item__cost">
        <VIcon
          icon="tabler-coin"
          size="18"
        />
        <strong>{{ reward.points_cost }}</strong>
        <span>{{ t('pos::pos_viewer.points_short') }}</span>
      </div>

      <div class="reward-item__action-area">
        <VBtn
          v-if="reward.is_eligible && can('admin.loyalty_gifts.redeem')"
          class="reward-item__action"
          color="primary"
          :loading="processing.loading && processing.id === reward.id"
          rounded="lg"
          variant="flat"
          @click="redeemReward"
        >
          {{ t('pos::pos_viewer.redeem') }}
          <VIcon
            end
            icon="tabler-arrow-right"
            size="17"
          />
        </VBtn>
        <template v-else-if="!reward.is_eligible">
          <div class="reward-item__progress">
            <span :style="{ width: `${progress}%` }"></span>
          </div>
          <small>{{ t('pos::pos_viewer.points_needed', { points: pointsNeeded }) }}</small>
        </template>
      </div>
    </div>
  </article>
</template>

<style lang="scss" scoped>
.reward-item {
  display: flex;
  min-width: 0;
  flex-direction: column;
  align-items: stretch;
  gap: 16px;
  min-height: 170px;
  padding: 15px 16px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.11);
  border-radius: 14px;
  background: rgb(var(--v-theme-surface));
  transition:
    border-color 180ms ease,
    box-shadow 180ms ease,
    transform 180ms ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.24);
    box-shadow: 0 10px 28px rgba(32, 43, 69, 0.08);
    transform: translateY(-1px);
  }

  &--eligible {
    border-color: rgba(var(--v-theme-primary), 0.3);
    background: rgba(var(--v-theme-primary), 0.035);
  }

  &__header {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr);
    align-items: start;
    gap: 10px;
  }

  &__footer {
    flex-shrink: 0;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: center;
    gap: 14px;
    margin-top: auto;
    padding-top: 12px;
    border-top: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  }

  &__media {
    flex-shrink: 0;
    display: grid;
    width: 42px;
    height: 42px;
    overflow: hidden;
    place-items: center;
    border: 1px solid rgba(var(--v-theme-primary), 0.16);
    border-radius: 12px;
    color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.08);
  }

  &__image {
    border-radius: 11px;
    overflow: hidden;
  }

  &__content {
    min-width: 0;

    > strong {
      display: block;
      overflow: hidden;
      color: rgb(var(--v-theme-on-surface));
      font-size: 0.875rem;
      font-weight: 750;
      overflow-wrap: anywhere;
      line-height: 1.45;
    }

    p {
      display: -webkit-box;
      margin: 3px 0 0;
      overflow: hidden;
      color: rgba(var(--v-theme-on-surface), 0.72);
      font-size: 0.75rem;
      line-height: 1.35;
      -webkit-box-orient: vertical;
      -webkit-line-clamp: 2;
    }
  }

  &__eligible {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 7px;
    padding: 3px 8px;
    border-radius: 7px;
    color: rgb(var(--v-theme-success));
    background: rgba(var(--v-theme-success), 0.1);
    font-size: 0.6875rem;
    font-weight: 700;
  }

  &__cost {
    display: grid;
    grid-template-columns: auto auto;
    align-items: center;
    justify-content: start;
    column-gap: 5px;
    min-width: 76px;
    color: rgb(var(--v-theme-primary));

    .v-icon {
      grid-row: 1 / 3;
    }

    strong {
      font-size: 0.9375rem;
      font-weight: 800;
      line-height: 1.1;
    }

    span {
      color: rgba(var(--v-theme-on-surface), 0.72);
      font-size: 0.6875rem;
      line-height: 1.1;
    }
  }

  &__action-area {
    min-height: 36px;
    min-width: 0;
    text-align: end;

    small {
      display: block;
      margin-top: 7px;
      color: rgba(var(--v-theme-on-surface), 0.78);
      font-size: 0.6875rem;
      font-weight: 650;
      white-space: nowrap;
    }
  }

  &__action {
    width: 100%;
    min-height: 36px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0;
    text-transform: none;
  }

  &__progress {
    position: relative;
    height: 7px;
    overflow: hidden;
    border-radius: 999px;
    background: rgba(var(--v-theme-on-surface), 0.08);

    span {
      position: absolute;
      inset-block: 0;
      inset-inline-start: 0;
      border-radius: inherit;
      background: linear-gradient(90deg, #ffb340, rgb(var(--v-theme-primary)));
    }
  }
}

[dir='rtl'] .reward-item__action :deep(.v-icon) {
  transform: rotate(180deg);
}

@media (prefers-reduced-motion: reduce) {
  .reward-item {
    transition: none;

    &:hover {
      transform: none;
    }
  }
}
</style>
