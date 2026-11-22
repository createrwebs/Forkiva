<script lang="ts" setup>
import WorkspacePanel from '../../WorkspacePanel.vue'
import type { PosMeta } from '@/modules/pos/contracts/posViewer.ts'
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useForm } from '@/modules/core/composables/form.ts'
import { generateUuid } from '@/modules/core/utils/uuid.ts'
import { usePosCashMovement } from '@/modules/pos/composables/posCashMovement.ts'

const props = defineProps<{
  modelValue: boolean
  embedded?: boolean
  registerId: number | null
  meta: PosMeta
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
}>()

const open = computed({
  get: () => props.modelValue,
  // Vue's v-model contract requires the camel-cased model prop in the emitted event.

  set: (val) => emit('update:modelValue', val),
})

const { store } = usePosCashMovement()
const { t } = useI18n()
const form = useForm({
  pos_register_id: props.registerId,
  reference: null,
  amount: null,
  notes: null,
  reason: null,
  direction: 'out',
  idempotency_key: generateUuid(),
})

const isDisabled = computed(
  () =>
    !form.state.direction ||
    !form.state.reason ||
    !form.state.amount ||
    Number.parseFloat(form.state.amount) < 1,
)

function close() {
  // Vue's v-model contract requires the camel-cased model prop in the emitted event.

  emit('update:modelValue', false)
  form.state.reference = null
  form.state.amount = null
  form.state.notes = null
  form.state.reason = null
  form.state.direction = 'out'
  form.state.idempotency_key = generateUuid()
}

async function submit() {
  if (!isDisabled.value && !form.loading.value && (await form.submit(() => store(form.state)))) {
    close()
  }
}

const filteredReasons = computed(() => {
  return props.meta.reasons[form.state.direction] || []
})

watch(
  () => form.state.direction,
  () => {
    form.state.reason = null
  },
)
</script>

<template>
  <WorkspacePanel
    v-model="open"
    :embedded="embedded"
  >
    <VCard
      class="pos-panel-card pos-cash-panel"
      flat
    >
      <VCardText class="pos-panel-body">
        <div
          v-if="!embedded"
          class="pos-cash-heading"
        >
          <span class="pos-service-icon">
            <VIcon
              icon="tabler-cash"
              size="24"
            />
          </span>
          <h3>{{ t('pos::pos_viewer.cash_flow_management') }}</h3>
          <VBtn
            :aria-label="t('admin::admin.buttons.close')"
            icon="tabler-x"
            variant="text"
            size="small"
            @click="close"
          />
        </div>
        <VForm
          class="pos-cash-form"
          @submit.prevent="submit"
        >
          <div class="pos-cash-fields">
            <div class="pos-cash-field">
              <span class="pos-cash-label">
                {{ t('pos::attributes.pos_cash_movements.direction') }}
              </span>
              <div
                class="pos-cash-directions"
                role="group"
                :aria-label="t('pos::attributes.pos_cash_movements.direction')"
              >
                <button
                  v-for="direction in meta.directions"
                  :key="direction.id"
                  type="button"
                  :aria-pressed="form.state.direction === direction.id"
                  :class="{ active: form.state.direction === direction.id }"
                  @click="form.state.direction = direction.id"
                >
                  <VIcon
                    :icon="direction.icon"
                    size="18"
                  />
                  {{ direction.name }}
                </button>
              </div>
            </div>
            <div class="pos-cash-field pos-cash-reason">
              <span class="pos-cash-label">
                {{ t('pos::attributes.pos_cash_movements.reason') }}
              </span>
              <VChipGroup
                v-model="form.state.reason"
                color="primary"
                column
                mandatory
                :aria-label="t('pos::attributes.pos_cash_movements.reason')"
              >
                <VChip
                  v-for="reason in filteredReasons"
                  :key="reason.id"
                  :value="reason.id"
                  variant="outlined"
                  filter
                >
                  <VIcon
                    :icon="reason.icon"
                    size="17"
                    start
                  />
                  {{ reason.name }}
                </VChip>
              </VChipGroup>
            </div>
            <div class="pos-cash-field">
              <label
                class="pos-cash-label"
                for="pos-cash-amount"
              >
                {{ t('pos::attributes.pos_cash_movements.amount') }}
              </label>
              <VTextField
                id="pos-cash-amount"
                v-model="form.state.amount"
                v-decimal-en
                class="pos-cash-amount"
                inputmode="decimal"
                placeholder="0.00"
                density="compact"
                hide-details="auto"
                :error="!!form.errors.value?.amount"
                :error-messages="form.errors.value?.amount"
              >
                <template #append-inner>
                  <span class="pos-cash-currency">{{ meta.currency }}</span>
                </template>
              </VTextField>
            </div>
            <div class="pos-cash-field">
              <label
                class="pos-cash-label"
                for="pos-cash-reference"
              >
                {{ t('pos::attributes.pos_cash_movements.reference') }}
              </label>
              <VTextField
                id="pos-cash-reference"
                v-model="form.state.reference"
                density="compact"
                hide-details="auto"
                :error="!!form.errors.value?.reference"
                :error-messages="form.errors.value?.reference"
              />
            </div>
            <div class="pos-cash-field">
              <label
                class="pos-cash-label"
                for="pos-cash-notes"
              >
                {{ t('pos::attributes.pos_cash_movements.notes') }}
              </label>
              <VTextarea
                id="pos-cash-notes"
                v-model="form.state.notes"
                density="compact"
                hide-details="auto"
                :error="!!form.errors.value?.notes"
                :error-messages="form.errors.value?.notes"
                rows="2"
              />
            </div>
          </div>
          <div class="pos-cash-footer">
            <VBtn
              color="default"
              variant="outlined"
              :disabled="form.loading.value"
              @click="close"
            >
              {{ t('admin::admin.buttons.cancel') }}
            </VBtn>
            <VBtn
              color="primary"
              type="submit"
              :disabled="isDisabled || form.loading.value"
              :loading="form.loading.value"
              prepend-icon="tabler-check"
            >
              {{ t('admin::admin.buttons.submit') }}
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </WorkspacePanel>
</template>

<style lang="scss" scoped>
.pos-cash-heading {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 4px 16px;
}
.pos-cash-heading h3 {
  flex: 1;
  font-size: 17px;
  line-height: 1.4;
  color: var(--pos-text);
}
.pos-service-icon {
  display: grid;
  place-items: center;
  inline-size: 40px;
  block-size: 40px;
  flex-shrink: 0;
  border-radius: 12px;
  background: var(--pos-accent-soft);
  color: var(--pos-accent);
}
.pos-cash-form {
  display: flex;
  flex-direction: column;
  flex: 1;
}
.pos-cash-fields {
  display: grid;
  gap: 18px;
  padding-block: 4px 20px;
}
.pos-cash-field {
  display: grid;
  grid-template-columns: 78px minmax(0, 1fr);
  gap: 12px;
  align-items: start;
}
.pos-cash-label {
  padding-block-start: 11px;
  font-size: 13px;
  line-height: 1.5;
  font-weight: 600;
  color: var(--pos-text);
}
.pos-cash-directions {
  display: flex;
  min-block-size: 40px;
  border: 1px solid var(--pos-border);
  border-radius: 9px;
  background: var(--pos-soft);
  overflow: hidden;
}
.pos-cash-directions button {
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 1;
  gap: 7px;
  padding: 8px;
  color: var(--pos-muted);
  font-size: 13px;
  font-weight: 600;
}
.pos-cash-directions button.active {
  background: var(--pos-accent);
  color: rgb(var(--v-theme-on-primary));
}
.pos-cash-reason :deep(.v-chip-group) {
  padding: 0;
}
.pos-cash-reason :deep(.v-chip) {
  min-block-size: 36px;
  margin: 0 6px 6px 0;
  border-radius: 8px;
  border-color: var(--pos-border);
  color: var(--pos-text);
  font-size: 12px;
}
.pos-cash-reason :deep(.v-chip--selected) {
  background: var(--pos-accent-soft);
  border-color: var(--pos-accent);
  color: var(--pos-accent);
}
.pos-cash-panel .pos-cash-amount :deep(input.v-field__input) {
  font-size: 20px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}
.pos-cash-currency {
  padding-inline-start: 10px;
  border-inline-start: 1px solid var(--pos-border);
  color: var(--pos-muted);
  font-size: 12px;
  font-weight: 700;
}
.pos-cash-footer {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  padding-block: 12px 4px;
  border-block-start: 1px solid var(--pos-border);
  margin-block-start: auto;
  position: sticky;
  bottom: -3px;
  background: var(--pos-surface);
  z-index: 1;
}
.pos-cash-footer .v-btn {
  min-inline-size: 0;
  min-block-size: 42px;
}
@media (max-width: 420px) {
  .pos-cash-field {
    grid-template-columns: minmax(0, 1fr);
    gap: 6px;
  }
  .pos-cash-label {
    padding: 0;
  }
  .pos-cash-fields {
    gap: 14px;
  }
}
</style>
