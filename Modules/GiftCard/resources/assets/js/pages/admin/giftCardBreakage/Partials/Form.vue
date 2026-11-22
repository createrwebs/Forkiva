<script lang="ts" setup>
import type { RouteLocationRaw } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import BaseForm from '@/modules/core/components/Form/BaseForm.vue'
import DatePicker from '@/modules/core/components/Form/DatePicker.vue'
import { useForm } from '@/modules/core/composables/form.ts'
import { generateUuid } from '@/modules/core/utils/uuid.ts'
import { useGiftCardBreakage } from '@/modules/giftcard/composables/giftCardBreakage.ts'

type BranchOption = {
  id: number
  name: string
}

type EligibleCard = {
  id: number
  code: string
  currency: string
  current_balance?:
    | {
        amount?: number
        formatted?: string
      }
    | number
    | string
    | null
}

type BreakageFormMeta = {
  branches: BranchOption[]
  eligible_cards: EligibleCard[]
}

const { t } = useI18n()
const { user } = useAuth()
const router = useRouter()
const { getFormMeta, store } = useGiftCardBreakage()

const form = useForm({
  branch_id: (user?.assigned_to_branch ? user.branch_id : null) as number | null,
  gift_card_ids: [] as number[],
  economic_date: new Date().toISOString().slice(0, 10),
  review_note: '',
  idempotency_key: generateUuid(),
})

const meta = ref<BreakageFormMeta>({
  branches: [],
  eligible_cards: [],
})
const metaLoading = ref(false)

const selectedCards = computed(() => {
  const selectedIds = new Set(form.state.gift_card_ids.map(Number))

  return meta.value.eligible_cards.filter((card) => selectedIds.has(Number(card.id)))
})

function cardBalance(card: EligibleCard): string {
  if (
    card.current_balance &&
    typeof card.current_balance === 'object' &&
    card.current_balance.formatted
  ) {
    return card.current_balance.formatted
  }

  if (card.current_balance === null || card.current_balance === undefined) return card.currency

  return `${card.currency} ${card.current_balance}`
}

async function loadMeta(branchId?: number): Promise<void> {
  metaLoading.value = true

  try {
    const response = (await getFormMeta(branchId)).data.body
    if (branchId) {
      meta.value.eligible_cards = response.eligible_cards || []
    } else {
      meta.value.branches = response.branches || []
    }
    form.errors.value.general = ''
  } catch {
    if (branchId) meta.value.eligible_cards = []
    form.errors.value.general = t('core::errors.an_unexpected_error_occurred')
  } finally {
    metaLoading.value = false
  }
}

async function submit(): Promise<void> {
  if (form.loading.value) return

  const response = await form.submit(() => store(form.state), true)
  if (!response || typeof response === 'boolean') return

  form.state.idempotency_key = generateUuid()
  await router.push({
    name: 'admin.gift_card_breakage_batches.show',
    params: { id: response.data.body.id },
  } as unknown as RouteLocationRaw)
}

watch(
  () => form.state.branch_id,
  async (branchId) => {
    form.state.gift_card_ids = []
    meta.value.eligible_cards = []
    if (branchId) await loadMeta(Number(branchId))
  },
)

onBeforeMount(async () => {
  if (!user?.assigned_to_branch) await loadMeta()
  if (form.state.branch_id) await loadMeta(Number(form.state.branch_id))
})
</script>

<template>
  <BaseForm
    action="create"
    :loading="form.loading.value"
    resource="gift_card_breakage_batches"
    @submit="submit"
  >
    <VAlert
      v-if="form.errors.value.general"
      class="mb-6"
      closable
      type="error"
      variant="tonal"
    >
      {{ form.errors.value.general }}
    </VAlert>

    <VRow>
      <VCol
        cols="12"
        lg="4"
      >
        <VCard>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon
              icon="tabler-file-description"
              size="20"
            />
            {{ t('giftcard::gift_card_breakage_batches.batch_details') }}
          </VCardTitle>

          <VCardText class="pa-5">
            <VRow>
              <VCol
                v-if="!user?.assigned_to_branch"
                cols="12"
              >
                <VSelect
                  v-model="form.state.branch_id"
                  clearable
                  :error="!!form.errors.value.branch_id"
                  :error-messages="form.errors.value.branch_id"
                  item-title="name"
                  item-value="id"
                  :items="meta.branches"
                  :label="t('giftcard::gift_card_breakage_batches.table.branch')"
                />
              </VCol>

              <VCol cols="12">
                <DatePicker
                  v-model="form.state.economic_date"
                  :error="!!form.errors.value.economic_date"
                  :error-messages="form.errors.value.economic_date"
                  :label="t('giftcard::gift_card_breakage_batches.table.economic_date')"
                />
              </VCol>

              <VCol cols="12">
                <VTextarea
                  v-model="form.state.review_note"
                  :error="!!form.errors.value.review_note"
                  :error-messages="form.errors.value.review_note"
                  :label="t('giftcard::gift_card_breakage_batches.review_note')"
                  rows="5"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        lg="8"
      >
        <VCard>
          <VCardTitle class="d-flex flex-wrap align-center gap-2">
            <VIcon
              icon="tabler-cards"
              size="20"
            />
            {{ t('giftcard::gift_card_breakage_batches.selected_cards') }}
          </VCardTitle>

          <VCardText class="pa-5">
            <VAutocomplete
              v-model="form.state.gift_card_ids"
              chips
              closable-chips
              :disabled="!form.state.branch_id"
              :error="!!form.errors.value.gift_card_ids"
              :error-messages="form.errors.value.gift_card_ids"
              item-title="code"
              item-value="id"
              :items="meta.eligible_cards"
              :label="t('giftcard::gift_card_breakage_batches.eligible_cards')"
              :loading="metaLoading"
              multiple
            />

            <VAlert
              v-if="!form.state.branch_id"
              class="mt-2"
              color="info"
              icon="tabler-building-store"
              variant="tonal"
            >
              {{ t('giftcard::gift_card_breakage_batches.select_branch_first') }}
            </VAlert>

            <VAlert
              v-else-if="!metaLoading && meta.eligible_cards.length === 0"
              class="mt-2"
              color="warning"
              icon="tabler-gift-off"
              variant="tonal"
            >
              {{ t('giftcard::gift_card_breakage_batches.no_eligible_cards') }}
            </VAlert>

            <VList
              v-else-if="selectedCards.length > 0"
              class="breakage-card-list mt-4 pa-0"
              lines="two"
            >
              <VListItem
                v-for="card in selectedCards"
                :key="card.id"
                :subtitle="cardBalance(card)"
                :title="card.code"
              >
                <template #prepend>
                  <VAvatar
                    color="primary"
                    rounded
                    size="38"
                    variant="tonal"
                  >
                    <VIcon
                      icon="tabler-gift-card"
                      size="20"
                    />
                  </VAvatar>
                </template>

                <template #append>
                  <VBtn
                    :aria-label="t('admin::admin.buttons.delete')"
                    color="error"
                    icon="tabler-x"
                    size="small"
                    variant="text"
                    @click="
                      form.state.gift_card_ids = form.state.gift_card_ids.filter(
                        (id) => Number(id) !== Number(card.id),
                      )
                    "
                  />
                </template>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </BaseForm>
</template>

<style lang="scss" scoped>
.breakage-card-list {
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}

.breakage-card-list :deep(.v-list-item:not(:last-child)) {
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
