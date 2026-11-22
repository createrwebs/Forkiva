<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { KitchenProduct } from '@/modules/pos/contracts/kitchen'
import PreparationTimeChip from '../PreparationTimeChip.vue'
import StatusBadge from './StatusBadge.vue'

const props = defineProps<{ item: KitchenProduct; disabled: boolean; processing: boolean }>()
defineEmits<{ advance: [id: number] }>()
const { t } = useI18n()
const done = computed(() => ['ready', 'served'].includes(props.item.status.id))
const actionLabel = computed(() => t(`pos::kds.item_actions.${props.item.status.id}`))
const options = computed(() =>
  (props.item.options || [])
    .map((option) => {
      // Field options also have a linked, empty catalog value. Their entered
      // value must take precedence over that placeholder selection.
      const fieldValue = option.value ?? ''
      const hasEnteredValue = fieldValue.trim().length > 0
      const value = hasEnteredValue
        ? fieldValue
        : (option.values || [])
            .map((selected) => selected.label)
            .filter((label) => label?.trim())
            .join(', ')
      return { id: option.id, name: option.name, value }
    })
    .filter((option) => option.value.trim().length > 0),
)
</script>

<template>
  <div
    class="kds-item"
    :class="{
      'kds-item--done': done,
      'kds-item--cancelled': ['cancelled', 'refunded'].includes(item.status.id),
    }"
  >
    <span class="kds-quantity">{{ item.quantity }}</span>
    <div class="kds-item__content">
      <div class="kds-item__title">{{ item.product.name }}</div>
      <dl
        v-if="options.length"
        class="kds-modifiers"
      >
        <div
          v-for="option in options"
          :key="option.id"
        >
          <dt>{{ option.name }}:</dt>
          <dd>{{ option.value }}</dd>
        </div>
      </dl>
      <div class="kds-item__meta">
        <StatusBadge
          class="kds-item__state"
          :status="item.status.id"
          :label="
            item.status.id === 'pending'
              ? t('pos::kds.states.new')
              : item.status.id === 'served'
                ? t('pos::kds.states.completed')
                : item.status.name
          "
        />
        <PreparationTimeChip
          v-if="['pending', 'preparing'].includes(item.status.id)"
          :active="item.status.id === 'preparing'"
          :minutes="item.prep_time_minutes"
          :started-at="item.preparation_started_at"
          :expected-ready-at="item.expected_ready_at"
        />
      </div>
    </div>
    <VBtn
      v-if="item.next_status"
      type="button"
      class="kds-item__action"
      :class="`kds-item__action--${item.status.id}`"
      :color="
        item.status.id === 'pending'
          ? '#2463c5'
          : item.status.id === 'preparing'
            ? '#18754d'
            : '#475569'
      "
      variant="tonal"
      :loading="processing"
      :disabled="disabled || processing"
      :aria-label="`${actionLabel}: ${item.product.name}`"
      @click="$emit('advance', item.id)"
    >
      <VIcon
        :icon="
          item.status.id === 'pending'
            ? 'tabler-player-play'
            : item.status.id === 'preparing'
              ? 'tabler-check'
              : 'tabler-checks'
        "
        size="17"
      />
      {{ actionLabel }}
    </VBtn>
  </div>
</template>
