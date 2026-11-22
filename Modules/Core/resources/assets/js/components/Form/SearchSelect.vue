<script lang="ts" setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { http } from '@/modules/core/api/http.ts'

type Primitive = string | number | boolean | null | undefined
type SelectItem = Record<string, any>
type PanelPlacement = 'top' | 'bottom'

const PANEL_GAP = 6
const VIEWPORT_MARGIN = 12
const PANEL_FIXED_HEIGHT = 82
const MIN_LIST_HEIGHT = 96

const props = withDefaults(
  defineProps<{
    modelValue: Primitive | Primitive[] | Record<string, any> | Record<string, any>[] | null
    items?: SelectItem[]
    url?: string | null
    requestParams?: Record<string, any>
    queryParamPath?: string
    itemTitle?: string | ((item: SelectItem) => string)
    itemSearch?: string | ((item: SelectItem) => string)
    itemValue?: string
    label?: string
    placeholder?: string
    error?: boolean
    errorMessages?: string | string[]
    multiple?: boolean
    clearable?: boolean
    disabled?: boolean
    readonly?: boolean
    returnObject?: boolean
    chips?: boolean
    closableChips?: boolean
    prefix?: string
    suffix?: string
    prependInnerIcon?: string
    loading?: boolean
    perPage?: number
    noDataText?: string
    listMaxHeight?: number | string
    panelMinWidth?: number
  }>(),
  {
    items: () => [],
    url: null,
    requestParams: () => ({}),
    queryParamPath: 'filters.search',
    itemTitle: 'name',
    itemSearch: undefined,
    itemValue: 'id',
    label: undefined,
    placeholder: undefined,
    error: false,
    errorMessages: '',
    multiple: false,
    clearable: true,
    disabled: false,
    readonly: false,
    returnObject: false,
    chips: false,
    closableChips: false,
    prefix: undefined,
    suffix: undefined,
    prependInnerIcon: undefined,
    loading: false,
    perPage: 10,
    noDataText: undefined,
    listMaxHeight: 280,
    panelMinWidth: 0,
  },
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: any): void
  (e: 'loaded', value: SelectItem[]): void
  (e: 'loading', value: boolean): void
  (e: 'load-error', value: unknown): void
  (e: 'search', value: string): void
}>()

const menu = ref(false)
const remoteLoading = ref(false)
const search = ref('')
const activeIndex = ref(-1)
const hasLoadedDefaults = ref(false)
const defaultRemoteItems = ref<SelectItem[]>([])
const searchRemoteItems = ref<SelectItem[]>([])
const knownItems = ref(new Map<string, SelectItem>())
const rootRef = ref<HTMLElement | null>(null)
const panelRef = ref<HTMLElement | { $el?: HTMLElement } | null>(null)
const activatorRef = ref<any>(null)
const searchInputRef = ref<any>(null)
const panelPlacement = ref<PanelPlacement>('bottom')
const panelPosition = ref({
  top: 0,
  left: 0,
  width: 0,
  maxHeight: 0,
  listMaxHeight: 0,
})
let searchTimeout: ReturnType<typeof setTimeout> | null = null
let requestSequence = 0

const requestSignature = computed(() => JSON.stringify(props.requestParams ?? {}))
const noDataLabel = computed(() => props.noDataText ?? 'No data available.')
const isLoading = computed(() => remoteLoading.value || props.loading)

function normalizeItemKey(value: Primitive): string | null {
  if (value === null || value === undefined) {
    return null
  }

  return String(value)
}

function resolveItemKey(item: SelectItem | null | undefined): Primitive {
  return item?.[props.itemValue]
}

function resolveItemTitle(item: SelectItem | null | undefined): string {
  if (!item) {
    return ''
  }

  const title =
    typeof props.itemTitle === 'function' ? props.itemTitle(item) : item?.[props.itemTitle]

  if (typeof title === 'string' || typeof title === 'number') {
    return String(title)
  }

  return ''
}

function resolveItemSearch(item: SelectItem): string {
  if (!props.itemSearch) {
    return resolveItemTitle(item)
  }

  const value =
    typeof props.itemSearch === 'function' ? props.itemSearch(item) : item?.[props.itemSearch]

  return typeof value === 'string' || typeof value === 'number' ? String(value) : ''
}

function cloneParams(): Record<string, any> {
  // JSON cloning safely unwraps Vue reactive request parameters.
  return JSON.parse(JSON.stringify(props.requestParams ?? {}))
}

function setNestedValue(target: Record<string, any>, path: string, value?: string): void {
  const segments = path.split('.').filter(Boolean)

  if (segments.length === 0) {
    return
  }

  let current: Record<string, any> = target

  for (const [index, segment] of segments.entries()) {
    if (index === segments.length - 1) {
      if (value) {
        current[segment] = value
      } else {
        delete current[segment]
      }

      continue
    }

    if (!current[segment] || typeof current[segment] !== 'object') {
      current[segment] = {}
    }

    current = current[segment]
  }
}

function resolveResponseItems(response: Record<string, any>): SelectItem[] {
  const body = response?.body

  if (Array.isArray(body)) {
    return body
  }

  if (Array.isArray(body?.data)) {
    return body.data
  }

  if (Array.isArray(response?.data)) {
    return response.data
  }

  return []
}

function mergeIntoKnown(items: SelectItem[]): void {
  const next = new Map(knownItems.value)

  for (const item of items) {
    const key = normalizeItemKey(resolveItemKey(item))

    if (!key) {
      continue
    }

    next.set(key, item)
  }

  knownItems.value = next
}

function resolveSelectedValues(value = props.modelValue): any[] {
  if (Array.isArray(value)) {
    return value
  }

  if (([null, undefined, ''] as Primitive[]).includes(value as Primitive)) {
    return []
  }

  return [value]
}

function resolveSelectedItems(value = props.modelValue): SelectItem[] {
  return resolveSelectedValues(value)
    .map((selectedValue) => {
      if (selectedValue && typeof selectedValue === 'object' && !Array.isArray(selectedValue)) {
        return selectedValue as SelectItem
      }

      const key = normalizeItemKey(selectedValue as Primitive)

      return key ? (knownItems.value.get(key) ?? null) : null
    })
    .filter(Boolean) as SelectItem[]
}

const selectedItems = computed(() => resolveSelectedItems())

const fieldValue = computed(() => {
  if (selectedItems.value.length === 0) {
    return ''
  }

  if (!props.multiple) {
    return resolveItemTitle(selectedItems.value[0])
  }

  const labels = selectedItems.value.map((item) => resolveItemTitle(item)).filter(Boolean)

  if (labels.length <= 2) {
    return labels.join(', ')
  }

  return `${labels.slice(0, 2).join(', ')} +${labels.length - 2}`
})

const normalizedSearch = computed(() => search.value.trim().toLocaleLowerCase())
const normalizedListMaxHeight = computed(() => {
  if (panelPosition.value.listMaxHeight > 0) {
    return `${panelPosition.value.listMaxHeight}px`
  }

  const preferredListMaxHeight = resolvePreferredListMaxHeight()

  return `${preferredListMaxHeight}px`
})
const panelStyle = computed(() => ({
  top: `${panelPosition.value.top}px`,
  left: `${panelPosition.value.left}px`,
  width: `${panelPosition.value.width}px`,
  maxHeight: panelPosition.value.maxHeight > 0 ? `${panelPosition.value.maxHeight}px` : undefined,
  transformOrigin: panelPlacement.value === 'top' ? 'center bottom' : 'center top',
}))

const localItems = computed(() => {
  if (!normalizedSearch.value) {
    return props.items
  }

  return props.items.filter((item) =>
    resolveItemSearch(item).toLocaleLowerCase().includes(normalizedSearch.value),
  )
})

const baseItems = computed(() => {
  if (normalizedSearch.value) {
    return [...localItems.value, ...searchRemoteItems.value]
  }

  return [...localItems.value, ...defaultRemoteItems.value]
})

const visibleItems = computed(() => {
  const merged = new Map<string, SelectItem>()

  for (const item of [...selectedItems.value, ...baseItems.value]) {
    const key = normalizeItemKey(resolveItemKey(item))

    if (!key) {
      continue
    }

    merged.set(key, item)
  }

  return Array.from(merged.values())
})

const selectedKeys = computed(
  () =>
    new Set(
      selectedItems.value.map((item) => normalizeItemKey(resolveItemKey(item))).filter(Boolean),
    ),
)

function isSelected(item: SelectItem): boolean {
  const key = normalizeItemKey(resolveItemKey(item))
  return Boolean(key && selectedKeys.value.has(key))
}

async function requestRemoteItems(searchValue = ''): Promise<SelectItem[]> {
  if (!props.url || props.disabled || props.readonly) {
    return []
  }

  remoteLoading.value = true
  emit('loading', true)

  try {
    const params = cloneParams()
    params.per_page = params.per_page ?? props.perPage
    setNestedValue(params, props.queryParamPath, searchValue.trim() || undefined)

    const response = await http.get(props.url, { params })
    return resolveResponseItems(response.data)
  } catch (error) {
    emit('load-error', error)
    return []
  } finally {
    remoteLoading.value = false
    emit('loading', false)
  }
}

async function loadDefaultItems(): Promise<void> {
  const sequence = ++requestSequence
  const items = await requestRemoteItems()

  if (sequence !== requestSequence) {
    return
  }

  defaultRemoteItems.value = items
  mergeIntoKnown(items)
  hasLoadedDefaults.value = true
  emit('loaded', items)
}

async function loadSearchItems(searchValue: string): Promise<void> {
  const sequence = ++requestSequence
  const items = await requestRemoteItems(searchValue)

  if (sequence !== requestSequence || search.value.trim() !== searchValue) {
    return
  }

  searchRemoteItems.value = items
  mergeIntoKnown(items)
  emit('loaded', items)
}

function queueRemoteSearch(searchValue: string): void {
  if (!props.url) {
    return
  }

  if (searchTimeout) {
    clearTimeout(searchTimeout)
  }

  searchTimeout = setTimeout(() => {
    const normalizedValue = searchValue.trim()

    if (!normalizedValue) {
      searchRemoteItems.value = []

      if (!hasLoadedDefaults.value) {
        void loadDefaultItems()
      }

      return
    }

    void loadSearchItems(normalizedValue)
  }, 250)
}

function normalizeReturnValue(item: SelectItem): any {
  return props.returnObject ? item : resolveItemKey(item)
}

function toggleItem(item: SelectItem): void {
  if (props.multiple) {
    const currentValues = resolveSelectedValues()
    const itemKey = normalizeItemKey(resolveItemKey(item))
    const existingIndex = currentValues.findIndex((currentValue) => {
      const currentKey = normalizeItemKey(
        currentValue && typeof currentValue === 'object'
          ? currentValue[props.itemValue]
          : currentValue,
      )

      return currentKey === itemKey
    })

    const nextValues = [...currentValues]

    if (existingIndex === -1) {
      nextValues.push(normalizeReturnValue(item))
    } else {
      nextValues.splice(existingIndex, 1)
    }

    emit('update:modelValue', nextValues)
    return
  }

  emit('update:modelValue', normalizeReturnValue(item))
  menu.value = false
  resetSearch()
}

function syncActiveIndex(): void {
  if (visibleItems.value.length === 0) {
    activeIndex.value = -1
    return
  }

  const selectedIndex = visibleItems.value.findIndex((item) => isSelected(item))

  activeIndex.value = selectedIndex === -1 ? 0 : selectedIndex
}

function scrollActiveItemIntoView(): void {
  nextTick(() => {
    resolvePanelElement()
      ?.querySelector('.search-select-item--keyboard-active')
      ?.scrollIntoView({ block: 'nearest' })
  })
}

function moveActiveItem(direction: 1 | -1): void {
  const itemCount = visibleItems.value.length

  if (itemCount === 0) {
    activeIndex.value = -1
    return
  }

  activeIndex.value =
    activeIndex.value === -1
      ? direction === 1
        ? 0
        : itemCount - 1
      : (activeIndex.value + direction + itemCount) % itemCount

  scrollActiveItemIntoView()
}

function selectActiveItem(): void {
  const item = visibleItems.value[activeIndex.value]

  if (!item) {
    return
  }

  toggleItem(item)
}

function clearSelection(): void {
  emit('update:modelValue', props.multiple ? [] : null)
  resetSearch()
}

function resetSearch(): void {
  const hadSearch = search.value.length > 0

  search.value = ''
  searchRemoteItems.value = []

  if (hadSearch) {
    emit('search', '')
  }
}

function handleMenuToggle(isOpen: boolean): void {
  if (menu.value === isOpen) {
    return
  }

  menu.value = isOpen

  if (isOpen && props.url && !hasLoadedDefaults.value) {
    void loadDefaultItems()
  }

  if (isOpen) {
    syncActiveIndex()
    updatePanelPosition()

    nextTick(() => {
      updatePanelPosition()
      searchInputRef.value?.focus?.()
    })
    return
  }

  if (!isOpen) {
    requestSequence++
    resetSearch()
  }
}

function toggleMenu(): void {
  if (props.disabled || props.readonly) {
    return
  }

  handleMenuToggle(!menu.value)
}

function closeMenu(): void {
  handleMenuToggle(false)
}

function resolvePanelElement(): HTMLElement | null {
  const panel = panelRef.value

  if (!panel) {
    return null
  }

  if (panel instanceof HTMLElement) {
    return panel
  }

  return panel.$el instanceof HTMLElement ? panel.$el : null
}

function resolvePreferredListMaxHeight(): number {
  if (typeof props.listMaxHeight === 'number') {
    return props.listMaxHeight
  }

  const listMaxHeight = props.listMaxHeight.trim()
  const numericValue = /^(?:\d+|\d*\.\d+)px?$/.test(listMaxHeight)
    ? Number.parseFloat(listMaxHeight)
    : Number.NaN

  return Number.isFinite(numericValue) ? numericValue : 280
}

function updatePanelPosition(): void {
  if (!menu.value || !rootRef.value) {
    return
  }

  const rect = rootRef.value.getBoundingClientRect()
  const viewportWidth = document.documentElement.clientWidth
  const viewportHeight = document.documentElement.clientHeight
  const availablePanelWidth = Math.max(0, viewportWidth - VIEWPORT_MARGIN * 2)
  const preferredPanelWidth = Math.max(rect.width, props.panelMinWidth)
  const panelWidth = Math.min(preferredPanelWidth, availablePanelWidth || preferredPanelWidth)
  const preferredListMaxHeight = resolvePreferredListMaxHeight()
  const preferredPanelHeight = preferredListMaxHeight + PANEL_FIXED_HEIGHT
  const panelElement = resolvePanelElement()
  const desiredPanelHeight = panelElement?.offsetHeight ?? preferredPanelHeight
  const spaceBelow = viewportHeight - rect.bottom - PANEL_GAP - VIEWPORT_MARGIN
  const spaceAbove = rect.top - PANEL_GAP - VIEWPORT_MARGIN
  const shouldOpenTop = spaceBelow < desiredPanelHeight && spaceAbove > spaceBelow
  const availablePanelHeight = Math.max(
    PANEL_FIXED_HEIGHT + MIN_LIST_HEIGHT,
    shouldOpenTop ? spaceAbove : spaceBelow,
  )
  const maxPanelHeight = Math.min(preferredPanelHeight, availablePanelHeight)
  const listMaxHeight = Math.max(
    MIN_LIST_HEIGHT,
    Math.min(preferredListMaxHeight, maxPanelHeight - PANEL_FIXED_HEIGHT),
  )
  const panelHeight = Math.min(desiredPanelHeight, listMaxHeight + PANEL_FIXED_HEIGHT)
  const top = shouldOpenTop
    ? Math.max(VIEWPORT_MARGIN, rect.top - PANEL_GAP - panelHeight)
    : Math.min(rect.bottom + PANEL_GAP, viewportHeight - VIEWPORT_MARGIN - panelHeight)
  const left = Math.min(
    Math.max(VIEWPORT_MARGIN, rect.left),
    Math.max(VIEWPORT_MARGIN, viewportWidth - VIEWPORT_MARGIN - panelWidth),
  )

  panelPlacement.value = shouldOpenTop ? 'top' : 'bottom'

  panelPosition.value = {
    top,
    left,
    width: panelWidth,
    maxHeight: maxPanelHeight,
    listMaxHeight,
  }
}

function handleSearchInput(value: string): void {
  search.value = value
  activeIndex.value = visibleItems.value.length > 0 ? 0 : -1
  emit('search', value)

  if (props.url) {
    queueRemoteSearch(value)
  }
}

function openMenu(): void {
  handleMenuToggle(true)
}

defineExpose({
  focus: openMenu,
  open: openMenu,
})

watch(
  () => props.items,
  (items) => mergeIntoKnown(items),
  { deep: true, immediate: true },
)

watch(
  () => props.modelValue,
  (value) => {
    const selectedObjects = resolveSelectedValues(value).filter(
      (selectedValue) =>
        selectedValue && typeof selectedValue === 'object' && !Array.isArray(selectedValue),
    ) as SelectItem[]

    if (selectedObjects.length > 0) {
      mergeIntoKnown(selectedObjects)
    }
  },
  { deep: true, immediate: true },
)

watch(
  [() => props.url, requestSignature],
  () => {
    requestSequence++
    hasLoadedDefaults.value = false
    defaultRemoteItems.value = []
    searchRemoteItems.value = []
    resetSearch()

    if (props.url && menu.value) {
      void loadDefaultItems()
    }
  },
  { immediate: true },
)

watch([visibleItems, remoteLoading, () => props.listMaxHeight, () => props.panelMinWidth], () => {
  if (menu.value && activeIndex.value >= visibleItems.value.length) {
    activeIndex.value = visibleItems.value.length === 0 ? -1 : visibleItems.value.length - 1
  }

  if (menu.value && activeIndex.value === -1 && visibleItems.value.length > 0) {
    activeIndex.value = 0
  }

  nextTick(updatePanelPosition)
})

function handleDocumentPointerDown(event: MouseEvent): void {
  if (!menu.value || !rootRef.value) {
    return
  }

  const target = event.target as Node | null

  if (target && rootRef.value.contains(target)) {
    return
  }

  if (target && resolvePanelElement()?.contains(target)) {
    return
  }

  closeMenu()
}

onMounted(() => {
  document.addEventListener('mousedown', handleDocumentPointerDown)
  window.addEventListener('resize', updatePanelPosition, { passive: true })
  window.addEventListener('scroll', updatePanelPosition, { capture: true, passive: true })
})

onBeforeUnmount(() => {
  if (searchTimeout) {
    clearTimeout(searchTimeout)
  }

  document.removeEventListener('mousedown', handleDocumentPointerDown)
  window.removeEventListener('resize', updatePanelPosition)
  window.removeEventListener('scroll', updatePanelPosition, { capture: true })
})
</script>

<template>
  <div
    ref="rootRef"
    class="search-select"
  >
    <div class="search-select__activator">
      <slot
        name="activator"
        :toggle="toggleMenu"
        :expanded="menu"
        :value="fieldValue"
      >
        <VTextField
          ref="activatorRef"
          append-inner-icon="tabler-chevron-down"
          autocomplete="off"
          :clearable="clearable && !disabled && !readonly && selectedItems.length > 0"
          :disabled="disabled"
          :error="error"
          :error-messages="errorMessages"
          :label="label"
          :model-value="fieldValue"
          :placeholder="placeholder"
          :prefix="prefix"
          :prepend-inner-icon="prependInnerIcon"
          readonly
          :suffix="suffix"
          @click="toggleMenu"
          @click:clear.stop="clearSelection"
        />
      </slot>
    </div>

    <Teleport to="body">
      <Transition name="search-select-panel">
        <VCard
          v-if="menu"
          ref="panelRef"
          class="search-select__panel"
          :class="`search-select__panel--${panelPlacement}`"
          elevation="10"
          :style="panelStyle"
        >
          <VCardText class="pa-3">
            <VTextField
              ref="searchInputRef"
              clearable
              :disabled="disabled || readonly"
              hide-details
              :loading="isLoading"
              :model-value="search"
              placeholder="Search"
              prepend-inner-icon="tabler-search"
              variant="outlined"
              @click:clear="resetSearch"
              @keydown.down.prevent="moveActiveItem(1)"
              @keydown.enter.prevent="selectActiveItem"
              @keydown.esc.stop="closeMenu"
              @keydown.up.prevent="moveActiveItem(-1)"
              @update:model-value="handleSearchInput(String($event ?? ''))"
            />
          </VCardText>

          <VDivider />

          <VList
            class="search-select__list py-1"
            :style="{ maxHeight: normalizedListMaxHeight }"
          >
            <template v-if="visibleItems.length > 0">
              <VListItem
                v-for="(item, index) in visibleItems"
                :key="String(resolveItemKey(item))"
                :active="isSelected(item)"
                class="search-select-item"
                :class="{
                  'search-select-item--keyboard-active': activeIndex === index,
                }"
                rounded="lg"
                @click="toggleItem(item)"
                @mouseenter="activeIndex = index"
              >
                <slot
                  name="item"
                  :item="item"
                  :selected="isSelected(item)"
                  :select="() => toggleItem(item)"
                >
                  <VCheckboxBtn
                    v-if="multiple"
                    :model-value="isSelected(item)"
                    tabindex="-1"
                  />
                  <VListItemTitle>{{ resolveItemTitle(item) }}</VListItemTitle>
                </slot>
              </VListItem>
            </template>

            <div
              v-else
              class="search-select__empty text-medium-emphasis"
            >
              {{ noDataLabel }}
            </div>
          </VList>
        </VCard>
      </Transition>
    </Teleport>
  </div>
</template>

<style lang="scss" scoped>
.search-select {
  position: relative;
  z-index: 1;
  width: 100%;
}

.search-select:focus-within {
  z-index: 60;
}

.search-select__activator {
  width: 100%;
}

.search-select__panel {
  position: fixed;
  z-index: 3100;
  display: flex;
  flex-direction: column;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  overflow: hidden;
}

.search-select__list {
  flex: 1 1 auto;
  overflow-y: auto;
}

.search-select-item {
  margin: 4px 8px;
}

.search-select-item--keyboard-active {
  background: rgba(var(--v-theme-primary), 0.08);
}

.search-select__empty {
  padding: 18px 16px;
  font-size: 0.95rem;
}

.search-select-item :deep(.v-list-item-title) {
  white-space: normal;
  word-break: break-word;
}

.search-select-panel-enter-active,
.search-select-panel-leave-active {
  transition:
    opacity 0.16s ease,
    transform 0.16s ease;
}

.search-select-panel-enter-from,
.search-select-panel-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

.search-select__panel--top.search-select-panel-enter-from,
.search-select__panel--top.search-select-panel-leave-to {
  transform: translateY(4px);
}
</style>
