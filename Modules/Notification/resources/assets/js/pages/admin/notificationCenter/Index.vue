<script lang="ts" setup>
import type {
  NotificationListFilters,
  RuntimeNotificationItem,
} from '@/modules/notification/contracts/NotificationCenter.ts'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useDisplay } from 'vuetify'
import { useNotificationCenterStore } from '@/modules/notification/stores/notificationCenterStore.ts'
import NotificationCenterDetailPanel from './Partials/NotificationCenterDetailPanel.vue'
import NotificationCenterListItem from './Partials/NotificationCenterListItem.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const { mdAndDown } = useDisplay()
const store = useNotificationCenterStore()
const {
  actionLoading,
  hasMore,
  hasUnread,
  items,
  listLoading,
  filters,
  selectedItem,
  unreadCount,
} = storeToRefs(store)

const search = ref(typeof route.query.search === 'string' ? route.query.search : '')
const searchLoading = ref(false)
const detailLoading = ref(false)
const loadingNotificationId = ref<string | null>(null)
const listScrollRef = ref<HTMLElement | null>(null)
const suppressAutoLoad = ref(false)
let searchTimer: number | null = null
const NOTIFICATION_SEARCH_MIN_LENGTH = 3

const selectedId = computed(() => {
  const value = route.query.selected
  return typeof value === 'string' ? value : null
})

const currentStatus = computed<'all' | 'unread' | 'read'>(() => {
  const value = route.query.status
  return value === 'unread' || value === 'read' ? value : 'all'
})

const routeSearch = computed(() => {
  const value = route.query.search
  return typeof value === 'string' ? value : ''
})

const pageFromQuery = computed(() => Number(route.query.page || filters.value.page || 1))

const resolveSearch = () => {
  const value = search.value.trim()
  return value === '' || Array.from(value).length >= NOTIFICATION_SEARCH_MIN_LENGTH ? value : ''
}

function buildNotificationFilters(overrides: NotificationListFilters = {}) {
  const selectedStatus = overrides.status || currentStatus.value
  return {
    page: overrides.page || pageFromQuery.value,
    status: selectedStatus,
    search: Object.prototype.hasOwnProperty.call(overrides, 'search')
      ? overrides.search
      : resolveSearch(),
    ...overrides,
  }
}

async function safeExecute(action: () => Promise<void>) {
  try {
    await action()
  } catch {
    toast.error(t('core::errors.failed_to_load_data'))
  }
}

const statusTabs = computed(() => [
  {
    title: t('notification::notifications.all_tab'),
    value: 'all',
    icon: 'tabler-bell',
    count: null,
  },
  {
    title: t('notification::notifications.unread_tab'),
    value: 'unread',
    icon: 'tabler-bell-ringing',
    count: unreadCount.value > 0 ? unreadCount.value : null,
  },
  {
    title: t('notification::notifications.read_tab'),
    value: 'read',
    icon: 'tabler-mail-opened',
    count: null,
  },
])

async function syncRouteSelection(notificationId?: string | null) {
  const currentScrollTop = listScrollRef.value?.scrollTop ?? 0

  await router.replace({
    query: {
      ...route.query,
      selected: notificationId || undefined,
    },
  })

  await nextTick()

  if (listScrollRef.value) {
    listScrollRef.value.scrollTop = currentScrollTop
  }
}

async function syncRouteSearch() {
  const value = resolveSearch()

  await router.replace({
    query: {
      ...route.query,
      search: value || undefined,
      page: undefined,
    },
  })
}

async function fetchNotifications(overrides: NotificationListFilters = {}, append = false) {
  await store.fetchNotifications(buildNotificationFilters(overrides), append)
}

async function fetchNotification(notificationId: string) {
  return await store.fetchNotification(notificationId)
}

async function markNotificationRead(notification: RuntimeNotificationItem) {
  return await store.markRead(notification.id)
}

async function openNotification(notificationId: string, listItem?: RuntimeNotificationItem) {
  loadingNotificationId.value = listItem?.id || notificationId
  detailLoading.value = true

  try {
    const openedNotification = await fetchNotification(notificationId)
    selectedItem.value = openedNotification

    if (!openedNotification.read_at) {
      const readNotification = await markNotificationRead(openedNotification)
      selectedItem.value = readNotification

      return readNotification
    }

    return openedNotification
  } finally {
    loadingNotificationId.value = null
    detailLoading.value = false
    suppressAutoLoad.value = false
  }
}

async function loadNotifications(overrides: NotificationListFilters = {}) {
  await fetchNotifications(overrides)

  if (!selectedId.value) {
    return
  }

  const current = items.value.find((item) => item.id === selectedId.value)
  await openNotification(selectedId.value, current)
}

async function handleSelect(notification: RuntimeNotificationItem) {
  if (loadingNotificationId.value) {
    return
  }

  suppressAutoLoad.value = true
  selectedItem.value = notification

  if (selectedId.value === notification.id) {
    await openNotification(notification.id, notification)
    return
  }

  await syncRouteSelection(notification.id)
}

async function handleMarkAllRead() {
  await store.markAllRead()

  if (currentStatus.value === 'unread') {
    await syncRouteSelection(null)
    await loadNotifications()
  }
}

async function handleStatusChange(value: 'all' | 'unread' | 'read') {
  await router.replace({
    query: {
      ...route.query,
      status: value === 'all' ? undefined : value,
      page: undefined,
    },
  })
}

async function handleLoadMore() {
  if (!hasMore.value || listLoading.value || detailLoading.value || suppressAutoLoad.value) {
    return
  }

  try {
    await fetchNotifications(
      {
        page: filters.value.page + 1,
      },
      true,
    )
  } catch {
    toast.error(t('core::errors.failed_to_load_data'))
  }
}

async function handleListScroll() {
  const element = listScrollRef.value

  if (!element) {
    return
  }

  const threshold = 120
  const remaining = element.scrollHeight - element.scrollTop - element.clientHeight

  if (remaining <= threshold) {
    await handleLoadMore()
  }
}

watch(selectedId, async (value) => {
  if (!value) {
    selectedItem.value = null
    return
  }

  const current = items.value.find((item) => item.id === value)

  try {
    selectedItem.value = current || selectedItem.value
    await openNotification(value, current)
  } catch {
    toast.error(t('core::errors.failed_to_load_data'))
  }
})

watch(
  () => route.query.status,
  async () => {
    try {
      await loadNotifications({ page: 1 })
    } catch {
      toast.error(t('core::errors.failed_to_load_data'))
    }
  },
)

watch(routeSearch, (value) => {
  if (value !== search.value) {
    search.value = value
  }
})

watch(search, (value) => {
  if (searchTimer !== null) {
    window.clearTimeout(searchTimer)
  }

  const normalizedSearch = resolveSearch()

  if (normalizedSearch === '' && value.trim() !== '') {
    searchLoading.value = false
    return
  }

  searchLoading.value = true

  searchTimer = window.setTimeout(async () => {
    await safeExecute(async () => {
      await fetchNotifications({
        page: 1,
        search: normalizedSearch,
      })
    })

    searchLoading.value = false
  }, 350)
})

onMounted(async () => {
  await Promise.all([
    safeExecute(() => store.fetchSummary()),
    safeExecute(() => loadNotifications()),
  ])
})

onBeforeUnmount(() => {
  if (searchTimer !== null) {
    window.clearTimeout(searchTimer)
  }
})
</script>

<template>
  <div class="notification-center-page">
    <div class="notification-center-page__layout">
      <VCard class="notification-center-page__list-card">
        <div class="notification-center-page__toolbar">
          <div class="notification-center-page__search-row">
            <VTextField
              v-model="search"
              class="notification-center-page__search-field"
              clearable
              density="comfortable"
              hide-details
              :label="$t('notification::notifications.search_placeholder')"
              :minlength="NOTIFICATION_SEARCH_MIN_LENGTH"
              prepend-inner-icon="tabler-search"
              variant="outlined"
              @blur="syncRouteSearch"
            >
              <template #append-inner>
                <VProgressCircular
                  v-if="searchLoading"
                  color="primary"
                  indeterminate
                  size="18"
                  width="2"
                />
              </template>
            </VTextField>

            <VMenu location="bottom end">
              <template #activator="{ props }">
                <VBtn
                  color="default"
                  icon="tabler-dots-vertical"
                  size="small"
                  v-bind="props"
                  variant="text"
                />
              </template>

              <VList class="py-1">
                <VListItem
                  :disabled="!hasUnread || actionLoading"
                  @click="handleMarkAllRead"
                >
                  <template #prepend>
                    <VIcon
                      icon="tabler-mail-opened"
                      size="18"
                    />
                  </template>

                  <VListItemTitle>
                    {{ $t('notification::notifications.mark_all_as_read') }}
                  </VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </div>

          <div class="notification-center-page__filter-bar">
            <VTabs
              class="notification-center-page__tabs"
              color="primary"
              grow
              :model-value="currentStatus"
              @update:model-value="handleStatusChange"
            >
              <VTab
                v-for="tab in statusTabs"
                :key="tab.value"
                class="notification-center-page__tab"
                :value="tab.value"
              >
                <span class="notification-center-page__tab-inner">
                  <VIcon
                    :icon="tab.icon"
                    size="18"
                  />
                  <span class="notification-center-page__tab-label">
                    {{ tab.title }}
                  </span>

                  <span
                    v-if="tab.count !== null"
                    class="notification-center-page__tab-count"
                  >
                    {{ tab.count }}
                  </span>
                </span>
              </VTab>
            </VTabs>
          </div>
        </div>

        <VDivider />

        <div
          ref="listScrollRef"
          class="notification-center-page__list-content"
          @scroll.passive="handleListScroll"
        >
          <div
            v-if="listLoading && items.length === 0"
            class="notification-center-page__state"
          >
            <VProgressCircular
              color="primary"
              indeterminate
              size="26"
            />
          </div>

          <div
            v-else-if="items.length === 0"
            class="notification-center-page__state notification-center-page__state--empty text-medium-emphasis"
          >
            <div class="notification-center-page__empty-content">
              <VIcon
                class="mb-3"
                icon="tabler-bell-off"
                size="34"
              />

              <div class="text-body-1 font-weight-medium text-high-emphasis">
                {{ $t('notification::notifications.empty_state') }}
              </div>
            </div>
          </div>

          <div
            v-else
            class="notification-center-page__list"
          >
            <NotificationCenterListItem
              v-for="notification in items"
              :key="notification.id"
              :disabled="loadingNotificationId === notification.id"
              :fallback-title="$t('notification::notifications.notification')"
              :is-active="selectedItem?.id === notification.id"
              :loading="loadingNotificationId === notification.id"
              :notification="notification"
              @select="handleSelect"
            />
          </div>

          <div
            v-if="listLoading && items.length > 0"
            class="notification-center-page__load-more notification-center-page__load-more--inline"
          >
            <VProgressCircular
              color="primary"
              indeterminate
              size="22"
            />
          </div>

          <div
            v-else-if="items.length > 0 && !hasMore"
            class="notification-center-page__load-more"
          />
        </div>
      </VCard>

      <VCard class="notification-center-page__detail-card">
        <NotificationCenterDetailPanel
          :loading="detailLoading"
          :md-and-down="mdAndDown"
          :selected-item="selectedItem"
        />
      </VCard>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.notification-center-page {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  min-height: calc(100vh - 10rem);

  &__layout {
    display: grid;
    align-items: stretch;
    gap: 1rem;
    flex: 1 1 auto;
    grid-template-columns: minmax(20rem, 24rem) minmax(0, 1fr);
  }

  &__list-card,
  &__detail-card {
    display: flex;
    flex-direction: column;
    border-radius: 10px;
    height: calc(100vh - 10rem);
    min-height: calc(100vh - 10rem);
    overflow: hidden;
  }

  &__toolbar {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: minmax(0, 1fr);
    padding: 1rem;
  }

  &__search-row {
    display: grid;
    align-items: center;
    gap: 0.5rem;
    grid-template-columns: minmax(0, 1fr) auto;
  }

  &__search-field {
    min-width: 0;
  }

  &__filter-bar {
    min-width: 0;
  }

  &__tabs {
    min-width: 0;

    :deep(.v-slide-group) {
      width: 100%;
    }

    :deep(.v-slide-group__content) {
      display: grid;
      width: 100%;
      gap: 0;
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    :deep(.v-tab--selected) {
      color: rgb(var(--v-theme-primary));
    }

    :deep(.v-tab) {
      min-width: 0;
      min-height: 2.875rem;
      padding-inline: 0.35rem;
      text-transform: none;
    }
  }

  &__tab {
    text-transform: none;
  }

  &__tab-inner {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    min-width: 0;
    width: 100%;
  }

  &__tab-label {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__tab-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.375rem;
    height: 1.375rem;
    padding-inline: 0.3125rem;
    border-radius: 999px;
    background: rgba(var(--v-theme-primary), 0.14);
    color: rgb(var(--v-theme-primary));
    font-size: 0.7rem;
    font-weight: 600;
    line-height: 1;
  }

  &__state {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 1 1 auto;
    min-height: 14rem;
    padding: 1rem;
    text-align: center;

    &--empty {
      flex-direction: column;
      gap: 0.75rem;
    }
  }

  &__empty-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    max-width: 18rem;
    text-align: center;

    &--detail {
      min-height: 18rem;
      max-width: 22rem;
    }
  }

  &__list {
    display: flex;
    flex-direction: column;
  }

  &__list-content {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
  }

  &__load-more {
    display: flex;
    justify-content: center;
    padding: 1rem;

    &--inline {
      border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    }
  }
}

@media (max-width: 1279px) {
  .notification-center-page {
    min-height: auto;
  }

  .notification-center-page__layout {
    grid-template-columns: minmax(0, 1fr);
  }

  .notification-center-page__list-card,
  .notification-center-page__detail-card {
    min-height: auto;
    height: auto;
  }
}
</style>
