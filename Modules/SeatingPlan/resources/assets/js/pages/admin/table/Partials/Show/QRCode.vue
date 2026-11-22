<script lang="ts" setup>
import QRCodeVue3 from 'qrcode-vue3'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useAppStore } from '@/modules/core/stores/appStore.ts'
import { useTable } from '@/modules/seatingPlan/composables/table.ts'
import { useTableQrCodeActions } from '@/modules/seatingPlan/composables/tableQrCode.ts'

const props = defineProps<{
  item: Record<string, any>
}>()

const emit = defineEmits<{
  (e: 'regenerated', value: Record<string, any>): void
}>()

const { t } = useI18n()
const toast = useToast()
const appStore = useAppStore()
const { loadings, regenerateTableQrToken } = useTable()
const qrCodeElement = ref<HTMLElement | null>(null)
const qrLink = computed(() =>
  props.item.table_qr_link ? new URL(props.item.table_qr_link, window.origin).toString() : '',
)
const qrFileName = computed(() => `${props.item.name || 'table'}-qr-code`)
const qrTitle = computed(() => props.item.name || t('seatingplan::tables.show.cards.qrcode'))
const qrSubtitle = computed(() => props.item.branch?.name || null)
const { downloadQrCode, printQrCode } = useTableQrCodeActions({
  element: qrCodeElement,
  fileName: qrFileName,
  title: qrTitle,
  subtitle: qrSubtitle,
  link: qrLink,
})

async function copyLink() {
  try {
    await navigator.clipboard.writeText(qrLink.value)
    toast.success(t('seatingplan::tables.qr_link_copied'))
  } catch {
    toast.error(t('core::errors.an_unexpected_error_occurred'))
  }
}

async function regenerate() {
  const response = await regenerateTableQrToken(props.item.id)

  if (response) {
    emit('regenerated', response)
  }
}

async function downloadQr() {
  try {
    await downloadQrCode()
  } catch {
    toast.error(t('core::errors.an_unexpected_error_occurred'))
  }
}

async function printQr() {
  try {
    await printQrCode()
  } catch {
    toast.error(t('core::errors.an_unexpected_error_occurred'))
  }
}
</script>

<template>
  <VCard class="table-qr-print-area">
    <VCardTitle>
      {{ t('seatingplan::tables.show.cards.qrcode') }}
    </VCardTitle>

    <VCardText>
      <VRow>
        <VCol
          class="text-center"
          cols="12"
        >
          <div
            ref="qrCodeElement"
            class="table-qr-code"
          >
            <QRCodeVue3
              :background-options="{
                color: '#ffffff',
                round: 0,
              }"
              :corners-dot-options="{
                type: '',
                color: '#4d4d4d',
              }"
              :corners-square-options="{
                type: 'extra-rounded',
                color: '#4d4d4d',
              }"
              :dots-options="{
                type: 'extra-rounded',
                color: '#f57c00',
                roundSize: true,
                gradient: null,
              }"
              file-ext="png"
              :height="250"
              :image="appStore.logoDataBase64 || ''"
              :image-options="{
                hideBackgroundDots: true,
                imageSize: 0.4,
                margin: 0,
              }"
              :qr-options="{ typeNumber: 0, mode: 'Byte', errorCorrectionLevel: 'H' }"
              :value="qrLink"
              :width="250"
            />
          </div>
        </VCol>

        <VCol cols="12">
          <VList density="compact">
            <VListItem>
              <template #prepend>
                <VIcon icon="tabler-circle-check" />
              </template>

              <VListItemTitle>
                {{ t('seatingplan::tables.show.qr_status') }}
              </VListItemTitle>

              <template #append>
                <VChip
                  :color="item.table_qr_status?.id === 'active' ? 'success' : 'default'"
                  size="small"
                  variant="tonal"
                >
                  {{ item.table_qr_status?.name }}
                </VChip>
              </template>
            </VListItem>

            <VListItem>
              <template #prepend>
                <VIcon icon="tabler-key" />
              </template>

              <VListItemTitle>
                {{ t('seatingplan::tables.show.qr_token') }}
              </VListItemTitle>

              <template #append>
                <span class="text-medium-emphasis">
                  {{ item.table_qr_token_masked }}
                </span>
              </template>
            </VListItem>

            <VListItem>
              <template #prepend>
                <VIcon icon="tabler-refresh" />
              </template>

              <VListItemTitle>
                {{ t('seatingplan::tables.show.qr_token_regenerated_at') }}
              </VListItemTitle>

              <template #append>
                <span class="text-medium-emphasis">
                  {{ item.table_qr_token_regenerated_at }}
                </span>
              </template>
            </VListItem>
          </VList>
        </VCol>

        <VCol cols="12">
          <div class="d-flex flex-wrap gap-2">
            <VBtn
              color="default"
              size="small"
              variant="tonal"
              @click="downloadQr"
            >
              <VIcon
                icon="tabler-download"
                start
              />
              {{ t('admin::admin.buttons.download') }}
            </VBtn>

            <VBtn
              color="default"
              size="small"
              variant="tonal"
              @click="copyLink"
            >
              <VIcon
                icon="tabler-copy"
                start
              />
              {{ t('seatingplan::tables.copy_qr_link') }}
            </VBtn>

            <VBtn
              color="default"
              size="small"
              variant="tonal"
              @click="printQr"
            >
              <VIcon
                icon="tabler-printer"
                start
              />
              {{ t('seatingplan::tables.print_qr') }}
            </VBtn>

            <VBtn
              color="warning"
              :loading="loadings.qrToken"
              size="small"
              variant="tonal"
              @click="regenerate"
            >
              <VIcon
                icon="tabler-refresh"
                start
              />
              {{ t('seatingplan::tables.regenerate_qr') }}
            </VBtn>
          </div>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.table-qr-code {
  display: flex;
  justify-content: center;
}

.table-qr-code :deep(img) {
  display: block;
  max-inline-size: 100%;
}
</style>
