<script lang="ts" setup>
import { computed, nextTick, onBeforeMount, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import { useQintrix } from '@/modules/printer/composables/qintrix.ts'
import { useOrder } from '@/modules/order/composables/order.ts'
import PrintContent from './PrintContent.vue'

const props = defineProps<{
  modelValue: boolean
  orderId: string | number
  registerId?: number | null
  branchId?: number | null
}>()
const emit = defineEmits<{ (e: 'update:modelValue', value: boolean): void }>()
const { t } = useI18n()
const { getPrintMeta } = useOrder()
const { user } = useAuth()
const qintrix = useQintrix()
const loading = ref(true)
const loadFailed = ref(false)
const busy = ref(false)
const agentAvailable = ref(false)
const selectedDocument = ref(0)
const meta = reactive<{
  registers: Record<string, any>[]
  branches: Record<string, any>[]
  contents: Record<string, any>[]
  branch_id: number | null
  register_id: number | null
}>({
  registers: [],
  branches: [],
  contents: [],
  branch_id: props.branchId ?? null,
  register_id: props.registerId ?? null,
})
const selectedContent = computed(() => meta.contents[selectedDocument.value])
const dialogModel = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})
const close = () => emit('update:modelValue', false)
const documentIcons: Record<string, string> = {
  bill: 'tabler-receipt',
  invoice: 'tabler-file-invoice',
  waiter: 'tabler-user',
  kitchen: 'tabler-chef-hat',
  delivery: 'tabler-truck-delivery',
}

function documentTitle(content: Record<string, any>) {
  return content.type.id === 'kitchen' ? content.label : content.type.name
}
function documentDescription(content: Record<string, any>) {
  return documentIcons[content.type.id]
    ? t(`order::orders.print_dialog.document_descriptions.${content.type.id}`)
    : content.label
}
async function selectDocument(index: number) {
  if (busy.value || !meta.contents.length) return
  selectedDocument.value = (index + meta.contents.length) % meta.contents.length
  await nextTick()
  document.getElementById(`pos-print-document-${selectedDocument.value}`)?.focus()
}

onBeforeMount(() => loadFormData(meta.branch_id, meta.register_id))

async function loadFormData(branchId?: number | null, registerId?: number | null) {
  loading.value = true
  loadFailed.value = false
  agentAvailable.value = false
  try {
    const response = (await getPrintMeta(props.orderId, branchId, registerId)).data.body
    meta.branches = response.branches?.length ? response.branches : meta.branches
    meta.registers = response.registers ?? []
    meta.contents = response.contents ?? []
    meta.branch_id = response.branch_id ?? meta.branch_id
    selectedDocument.value = 0
    if (response.agent) {
      qintrix.initClient(response.agent.base_url, response.agent.api_key)
      agentAvailable.value = true
    }
  } catch {
    loadFailed.value = true
  } finally {
    loading.value = false
  }
}
function changeBranch(branchId: number | null) {
  meta.branch_id = branchId
  meta.register_id = null
  meta.registers = []
  loadFormData(branchId)
}
</script>

<template>
  <VDialog
    v-model="dialogModel"
    max-width="640"
    aria-labelledby="pos-print-title"
    scrollable
    :persistent="busy"
  >
    <VCard class="pos-print-dialog">
      <div class="pos-print-heading">
        <span
          class="pos-print-symbol"
          aria-hidden="true"
        >
          <VIcon
            icon="tabler-printer"
            size="32"
          />
        </span>
        <div class="pos-print-title">
          <h2 id="pos-print-title">{{ t('order::orders.print_dialog.title') }}</h2>
          <p>{{ t('order::orders.print_dialog.description') }}</p>
        </div>
        <VBtn
          :aria-label="t('admin::admin.buttons.close')"
          :disabled="busy"
          icon="tabler-x"
          color="default"
          variant="text"
          size="small"
          @click="close"
        />
      </div>
      <VCardText
        class="pos-print-body"
        :aria-busy="loading"
      >
        <div
          v-if="loading"
          class="pos-print-loading"
          role="status"
        >
          <VProgressCircular
            color="primary"
            indeterminate
            size="36"
          />
        </div>
        <div
          v-else-if="loadFailed"
          class="pos-print-error"
          role="alert"
        >
          <p>{{ t('core::errors.an_unexpected_error_occurred') }}</p>
          <VBtn
            color="primary"
            variant="tonal"
            @click="loadFormData(meta.branch_id, meta.register_id)"
          >
            {{ t('order::orders.print_dialog.retry') }}
          </VBtn>
        </div>
        <template v-else>
          <div
            class="pos-document-folders"
            role="radiogroup"
            :aria-label="t('order::orders.print_dialog.documents')"
          >
            <button
              v-for="(content, index) in meta.contents"
              :id="`pos-print-document-${index}`"
              :key="`${content.type.id}-${content.id ?? index}`"
              class="pos-document-folder"
              :class="{ 'is-selected': selectedDocument === index }"
              type="button"
              role="radio"
              :aria-checked="selectedDocument === index"
              :tabindex="selectedDocument === index ? 0 : -1"
              :disabled="busy"
              @click="selectedDocument = index"
              @keydown.right.prevent="selectDocument(index + 1)"
              @keydown.down.prevent="selectDocument(index + 1)"
              @keydown.left.prevent="selectDocument(index - 1)"
              @keydown.up.prevent="selectDocument(index - 1)"
              @keydown.home.prevent="selectDocument(0)"
              @keydown.end.prevent="selectDocument(meta.contents.length - 1)"
            >
              <svg
                class="pos-folder-shape"
                viewBox="0 0 240 112"
                preserveAspectRatio="none"
                aria-hidden="true"
              >
                <path
                  d="M9 1 H82 Q86 1 89 5 L96 12 H231 Q239 12 239 20 V103 Q239 111 231 111 H9 Q1 111 1 103 V9 Q1 1 9 1 Z"
                />
              </svg>
              <VIcon
                v-if="selectedDocument === index"
                class="pos-folder-check"
                icon="tabler-check"
                size="19"
              />
              <VIcon
                class="pos-folder-icon"
                :icon="documentIcons[content.type.id] ?? 'tabler-file'"
                size="28"
              />
              <span class="pos-folder-copy">
                <strong>{{ documentTitle(content) }}</strong>
                <span>{{ documentDescription(content) }}</span>
              </span>
            </button>
          </div>
          <section
            v-if="!registerId && meta.contents.length"
            class="pos-print-destination"
            aria-labelledby="pos-print-destination-title"
          >
            <h3 id="pos-print-destination-title">
              {{ t('order::orders.print_dialog.destination') }}
            </h3>
            <div class="pos-print-configuration">
              <div
                v-if="!user?.assigned_to_branch"
                class="pos-print-field"
              >
                <label for="pos-print-branch">{{ t('order::attributes.print.branch_id') }}</label>
                <VSelect
                  id="pos-print-branch"
                  :model-value="meta.branch_id"
                  :disabled="busy"
                  item-title="name"
                  item-value="id"
                  :items="meta.branches"
                  density="comfortable"
                  hide-details="auto"
                  :aria-label="t('order::attributes.print.branch_id')"
                  :menu-props="{ maxHeight: 280 }"
                  @update:model-value="changeBranch"
                />
              </div>
              <div class="pos-print-field">
                <label for="pos-print-register">
                  {{ t('order::orders.print_dialog.register') }}
                </label>
                <VSelect
                  id="pos-print-register"
                  v-model="meta.register_id"
                  :disabled="busy"
                  item-title="name"
                  item-value="id"
                  :items="meta.registers"
                  density="comfortable"
                  hide-details="auto"
                  :aria-label="t('order::orders.print_dialog.register')"
                  :placeholder="t('order::orders.print_dialog.register_placeholder')"
                  :menu-props="{ maxHeight: 280 }"
                />
              </div>
            </div>
          </section>
          <p
            v-if="!meta.contents.length"
            class="pos-print-empty"
          >
            {{ t('order::orders.print_dialog.no_documents') }}
          </p>
        </template>
      </VCardText>
      <PrintContent
        v-if="!loading && !loadFailed && selectedContent"
        :content="selectedContent"
        :order-id="orderId"
        :qintrix="qintrix"
        :agent-available="agentAvailable"
        :register-id="meta.register_id"
        @busy="busy = $event"
        @close="close"
      />
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
@use '../../../styles/theme' as pos-theme;
.pos-print-dialog {
  @include pos-theme.light;
  --pos-text: #182235;
  --pos-muted: #687589;
  --pos-soft: #f0f3f7;
  border-radius: 16px !important;
  max-block-size: calc(100dvh - 32px);
  color: var(--pos-text);
  background: var(--pos-surface);
}
.pos-print-dialog.v-theme--dark {
  @include pos-theme.dark;
}
.pos-print-heading {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 20px 20px 16px;
}
.pos-print-symbol {
  display: grid;
  place-items: center;
  inline-size: 40px;
  block-size: 40px;
  flex-shrink: 0;
  border-radius: 12px;
  background: var(--pos-accent-soft);
  color: var(--pos-accent);
}
.pos-print-title {
  flex: 1;
  min-inline-size: 0;
}
.pos-print-title h2 {
  margin: 2px 0 0;
  font-size: 20px;
  font-weight: 700;
  line-height: 1.35;
  letter-spacing: -0.4px;
  color: var(--pos-text);
}
.pos-print-title p {
  margin: 4px 0 0;
  font-size: 14px;
  line-height: 1.5;
  color: var(--pos-muted);
}
.pos-print-heading > .v-btn {
  color: var(--pos-muted) !important;
  margin-inline-end: -8px;
}
.pos-print-body.v-card-text {
  padding: 0 20px 14px !important;
}
.pos-print-loading {
  display: grid;
  place-items: center;
  min-block-size: 200px;
}
.pos-print-error {
  display: grid;
  justify-items: center;
  gap: 16px;
  padding-block: 24px;
}
.pos-document-folders {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(170px, 100%), 1fr));
  gap: 10px;
  padding: 10px;
  background: var(--pos-soft);
  border-radius: 10px;
}
.pos-document-folder {
  position: relative;
  isolation: isolate;
  display: flex;
  align-items: center;
  gap: 10px;
  min-inline-size: 0;
  min-block-size: 84px;
  padding: 22px 12px 14px;
  background: transparent;
  color: var(--pos-text);
  text-align: start;
  cursor: pointer;
  font: inherit;
  border: 0;
  border-radius: 8px;
  transition: color 150ms ease;
}
.pos-folder-shape {
  position: absolute;
  inset: 0;
  inline-size: 100%;
  block-size: 100%;
  z-index: 0;
  overflow: visible;
}
.pos-folder-shape path {
  fill: var(--pos-surface);
  fill-opacity: 0.65;
  stroke: var(--pos-border);
  stroke-width: 1.5px;
  vector-effect: non-scaling-stroke;
  transition:
    stroke 150ms ease,
    fill-opacity 150ms ease;
}
.pos-document-folder:hover .pos-folder-shape path {
  stroke: var(--pos-muted);
  fill-opacity: 1;
}
.pos-document-folder.is-selected .pos-folder-shape path {
  fill-opacity: 1;
  stroke: var(--pos-accent);
  stroke-width: 1.7px;
}
.pos-document-folder.is-selected::after {
  content: '';
  position: absolute;
  inset-inline: 5px;
  inset-block-end: 0;
  block-size: 4px;
  border-radius: 0 0 4px 4px;
  background: var(--pos-accent);
}
.pos-document-folder:focus-visible {
  outline: 2px solid var(--pos-accent);
  outline-offset: 4px;
}
.pos-document-folder:disabled {
  cursor: wait;
}
.pos-folder-check {
  z-index: 1;
  position: absolute;
  inset-block-start: 12px;
  inset-inline-end: 9px;
  color: var(--pos-accent);
}
.pos-folder-icon {
  position: relative;
  z-index: 1;
  flex-shrink: 0;
}
.is-selected .pos-folder-icon {
  color: var(--pos-accent);
}
.pos-folder-copy {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-inline-size: 0;
  overflow-wrap: anywhere;
}
.pos-folder-copy strong {
  font-size: 15px;
  font-weight: 700;
  line-height: 1.35;
}
.pos-folder-copy > span {
  font-size: 12px;
  line-height: 1.4;
  color: var(--pos-muted);
}
.pos-print-destination {
  margin-block-start: 18px;
}
.pos-print-destination h3 {
  margin: 0 0 10px;
  font-size: 16px;
  font-weight: 700;
  color: var(--pos-text);
}
.pos-print-configuration {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}
.pos-print-field {
  min-inline-size: 0;
}
.pos-print-field:only-child {
  grid-column: 1 / -1;
}
.pos-print-field label {
  display: block;
  margin-block-end: 5px;
  font-size: 14px;
  font-weight: 500;
  color: var(--pos-muted);
}
.pos-print-field :deep(.v-field) {
  --v-input-control-height: 42px;
  border-radius: 8px;
  color: var(--pos-text);
}
.pos-print-field :deep(.v-field__input) {
  min-block-size: 42px;
  padding-block: 8px;
  font-size: 14px;
}
.pos-print-field :deep(.v-select__selection) {
  margin-block: 0;
}
.pos-print-field :deep(.v-field__outline) {
  color: var(--pos-border);
  --v-field-border-opacity: 1;
}
.pos-print-field :deep(.v-field--focused .v-field__outline) {
  color: var(--pos-accent);
}
.pos-print-field :deep(input:focus) {
  outline: none;
  box-shadow: none;
}
.pos-print-empty {
  padding-block: 24px;
  text-align: center;
  color: var(--pos-muted);
}
@media (max-width: 600px) {
  .pos-print-heading {
    gap: 12px;
    padding: 16px 16px 14px;
  }
  .pos-print-symbol {
    inline-size: 40px;
    block-size: 40px;
    border-radius: 9px;
  }
  .pos-print-symbol .v-icon {
    font-size: 24px !important;
  }
  .pos-print-title h2 {
    font-size: 18px;
  }
  .pos-print-title p {
    font-size: 13px;
  }
  .pos-print-body.v-card-text {
    padding: 0 16px 14px !important;
  }
  .pos-document-folders {
    display: flex;
    overflow-x: auto;
    gap: 10px;
    padding: 10px;
    scroll-snap-type: x proximity;
  }
  .pos-document-folder {
    flex: 0 0 160px;
    min-block-size: 82px;
    padding: 22px 12px 14px;
    gap: 10px;
    scroll-snap-align: center;
  }
  .pos-folder-copy strong {
    font-size: 15px;
  }
  .pos-folder-icon {
    font-size: 28px !important;
  }
  .pos-print-configuration {
    grid-template-columns: 1fr;
    gap: 14px;
  }
  .pos-print-destination {
    margin-block-start: 16px;
  }
  .pos-print-destination h3 {
    font-size: 15px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .pos-document-folder,
  .pos-folder-shape path {
    transition: none;
  }
}
</style>
