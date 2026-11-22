<script lang="ts" setup>
import { useI18n } from 'vue-i18n'

const props = defineProps<{ item: any }>()
const { t } = useI18n()
const search = ref('')

const lines = computed(() => {
  const value = search.value.trim().toLowerCase()

  return value
    ? props.item.lines.filter((line: any) => line.gift_card.code.toLowerCase().includes(value))
    : props.item.lines
})

const lifecycle = computed(() =>
  [
    ['created', props.item.created_by.name, props.item.created_at, 'secondary', 'tabler-file-plus'],
    [
      'reviewed',
      props.item.reviewed_by.name,
      props.item.reviewed_at,
      'info',
      'tabler-shield-check',
    ],
    ['posted', props.item.posted_by.name, props.item.posted_at, 'success', 'tabler-check'],
  ].filter((entry) => entry[2]),
)

const audit = computed(() => [
  [t('giftcard::gift_card_breakage_batches.table.branch'), props.item.branch.name],
  [t('admin::admin.table.created_by'), props.item.created_by.name],
  [t('giftcard::gift_card_breakage_batches.reviewed'), props.item.reviewed_by.name],
  [t('giftcard::gift_card_breakage_batches.posted'), props.item.posted_by.name],
  [t('giftcard::gift_card_breakage_batches.review_note'), props.item.review_note],
])
</script>

<template>
  <VRow>
    <VCol
      cols="12"
      lg="8"
    >
      <VCard>
        <VCardTitle class="d-flex flex-wrap align-center justify-space-between gap-3 border-b py-4">
          <div class="d-flex align-center gap-2 text-h6">
            <VIcon icon="tabler-gift" />
            {{ t('giftcard::gift_card_breakage_batches.selected_cards') }}
          </div>
          <VTextField
            v-model="search"
            class="breakage-lines-search"
            density="compact"
            hide-details
            :placeholder="t('admin::admin.table.search_placeholder')"
            prepend-inner-icon="tabler-search"
            variant="outlined"
          />
        </VCardTitle>

        <VTable class="breakage-lines-table">
          <thead>
            <tr>
              <th>#</th>
              <th>{{ t('giftcard::gift_cards.gift_card') }}</th>
              <th>
                {{ t('giftcard::gift_card_breakage_batches.table.expired_at') }}
              </th>
              <th class="text-end">
                {{ t('giftcard::gift_card_breakage_batches.frozen_amount') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(line, index) in lines"
              :key="line.id"
            >
              <td class="text-medium-emphasis">{{ Number(index) + 1 }}</td>
              <td class="font-weight-medium">{{ line.gift_card.code }}</td>
              <td>{{ line.gift_card.expiry_date }}</td>
              <td class="text-end font-weight-medium">{{ line.amount.formatted }}</td>
            </tr>
            <tr v-if="lines.length === 0">
              <td
                class="text-center text-medium-emphasis py-8"
                colspan="4"
              >
                {{ t('admin::admin.table.no_data_available') }}
              </td>
            </tr>
          </tbody>
          <tfoot v-if="lines.length === item.lines.length">
            <tr>
              <th colspan="3">
                {{ t('giftcard::gift_card_breakage_batches.table.total_amount') }}
              </th>
              <th class="text-end"><Money :money="item.total_amount" /></th>
            </tr>
          </tfoot>
        </VTable>
      </VCard>
    </VCol>

    <VCol
      cols="12"
      lg="4"
    >
      <VCard>
        <VCardTitle class="d-flex align-center gap-2 border-b py-4 text-h6">
          <VIcon icon="tabler-route" />
          {{ t('giftcard::gift_card_breakage_batches.lifecycle') }}
        </VCardTitle>
        <VCardText class="pt-5">
          <VTimeline
            align="start"
            class="breakage-lifecycle"
            density="compact"
            side="end"
          >
            <VTimelineItem
              v-for="entry in lifecycle"
              :key="String(entry[0])"
              :dot-color="String(entry[3])"
              fill-dot
              size="small"
            >
              <template #icon>
                <VIcon
                  color="white"
                  :icon="String(entry[4])"
                  size="14"
                />
              </template>
              <div class="font-weight-bold">
                {{ t(`giftcard::gift_card_breakage_batches.${entry[0]}`) }}
              </div>
              <div class="text-body-2 mt-1">{{ entry[1] || '-' }}</div>
              <div class="text-caption text-medium-emphasis">{{ entry[2] }}</div>
            </VTimelineItem>
          </VTimeline>
        </VCardText>

        <VDivider />

        <VExpansionPanels variant="accordion">
          <VExpansionPanel elevation="0">
            <VExpansionPanelTitle class="text-subtitle-1 font-weight-medium">
              <VIcon
                class="me-2"
                icon="tabler-file-search"
              />
              {{ t('giftcard::gift_card_breakage_batches.audit_details') }}
            </VExpansionPanelTitle>
            <VExpansionPanelText>
              <dl class="breakage-audit-list">
                <template
                  v-for="row in audit"
                  :key="String(row[0])"
                >
                  <dt>{{ row[0] }}</dt>
                  <dd class="text-break">{{ row[1] || '-' }}</dd>
                </template>
              </dl>
            </VExpansionPanelText>
          </VExpansionPanel>
        </VExpansionPanels>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss" scoped>
.breakage-lines-search {
  flex: 0 1 280px;
}

.breakage-lines-table :deep(th) {
  color: rgb(var(--v-theme-on-surface), 0.62);
  font-size: 0.75rem;
  font-weight: 700;
  white-space: nowrap;
}

.breakage-lines-table :deep(td),
.breakage-lines-table :deep(th) {
  height: 56px;
}

.breakage-lifecycle :deep(.v-timeline-item__body) {
  min-block-size: auto;
  padding-bottom: 18px;
}

.breakage-audit-list {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.25fr);
  gap: 14px 18px;
  margin: 0;
}

.breakage-audit-list dt {
  color: rgb(var(--v-theme-on-surface), 0.62);
  font-size: 0.8125rem;
}

.breakage-audit-list dd {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 600;
  text-align: end;
}

@media (max-width: 599px) {
  .breakage-lines-search {
    flex-basis: 100%;
  }
}
</style>
