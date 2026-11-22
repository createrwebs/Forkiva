<script lang="ts" setup>
import QRCodeVue3 from 'qrcode-vue3'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useAppStore } from '@/modules/core/stores/appStore.ts'
import { useTable } from '@/modules/seatingPlan/composables/table.ts'
import { useTableQrCodeActions } from '@/modules/seatingPlan/composables/tableQrCode.ts'

const props = defineProps<{ item: any }>()
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
    Object.assign(props.item, response)
  }
}

async function downloadQr() {
  try {
    downloadQrCode()
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
  <VMenu
    location="top"
    offset="8"
  >
    <template #activator="{ props: activatorProps }">
      <VChip
        class="cursor-pointer"
        color="default"
        size="small"
        v-bind="activatorProps"
      >
        <VIcon
          class="cursor-pointer text-medium-emphasis"
          icon="tabler-qrcode"
        />
        &nbsp;
        {{ t('seatingplan::tables.view_qrcode') }}
      </VChip>
    </template>

    <VCard
      class="table-qr-print-area"
      min-width="290"
    >
      <VCardText class="text-center pa-2">
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

        <div class="d-flex flex-wrap justify-center gap-2 mt-3">
          <VBtn
            color="default"
            icon
            size="small"
            variant="tonal"
            @click.stop="downloadQr"
          >
            <VIcon icon="tabler-download" />
            <VTooltip
              activator="parent"
              :text="t('admin::admin.buttons.download')"
            />
          </VBtn>

          <VBtn
            color="default"
            icon
            size="small"
            variant="tonal"
            @click.stop="copyLink"
          >
            <VIcon icon="tabler-copy" />
            <VTooltip
              activator="parent"
              :text="t('seatingplan::tables.copy_qr_link')"
            />
          </VBtn>

          <VBtn
            color="default"
            icon
            size="small"
            variant="tonal"
            @click.stop="printQr"
          >
            <VIcon icon="tabler-printer" />
            <VTooltip
              activator="parent"
              :text="t('seatingplan::tables.print_qr')"
            />
          </VBtn>

          <VBtn
            color="warning"
            icon
            :loading="loadings.qrToken"
            size="small"
            variant="tonal"
            @click.stop="regenerate"
          >
            <VIcon icon="tabler-refresh" />
            <VTooltip
              activator="parent"
              :text="t('seatingplan::tables.regenerate_qr')"
            />
          </VBtn>
        </div>
      </VCardText>
    </VCard>
  </VMenu>
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
