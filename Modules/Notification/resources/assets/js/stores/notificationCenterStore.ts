import type { PaginationMeta } from '@/modules/core/contracts/Table.ts'
import type {
  NotificationListFilters,
  RuntimeNotificationItem,
} from '@/modules/notification/contracts/NotificationCenter.ts'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import {
  getNotification,
  getNotifications,
  getNotificationSummary,
  markAllNotificationsRead,
  markNotificationRead,
} from '@/modules/notification/api/notificationCenter.api.ts'

const summaryCacheLifetime = 15_000

type NotificationCenterFilters = {
  page: number
  per_page: number
  status: 'all' | 'unread' | 'read'
  search: string
}

function defaultPagination(): PaginationMeta {
  return {
    current_page: 1,
    from: 0,
    last_page: 1,
    per_page: 15,
    to: 0,
    total: 0,
  }
}

function defaultFilters(): NotificationCenterFilters {
  return {
    page: 1,
    per_page: 15,
    status: 'all',
    search: '',
  }
}

export const useNotificationCenterStore = defineStore('notification-center', () => {
  const summaryLoading = ref(false)
  const listLoading = ref(false)
  const actionLoading = ref(false)
  const unreadCount = ref(0)
  const recent = ref<RuntimeNotificationItem[]>([])
  const items = ref<RuntimeNotificationItem[]>([])
  const selectedItem = ref<RuntimeNotificationItem | null>(null)
  const pagination = ref<PaginationMeta>(defaultPagination())
  const hasMore = ref(false)
  const filters = ref<NotificationCenterFilters>(defaultFilters())
  const summaryFetchedAt = ref<number | null>(null)
  const summaryPromise = ref<Promise<void> | null>(null)
  const pollingTimer = ref<number | null>(null)

  const hasUnread = computed(() => unreadCount.value > 0)

  const applySummary = (notificationsSummary: {
    unread_count: number
    recent?: RuntimeNotificationItem[]
  }) => {
    unreadCount.value = notificationsSummary.unread_count
    recent.value = notificationsSummary.recent || []
    summaryFetchedAt.value = Date.now()
  }

  const applyReadNotification = (notification: RuntimeNotificationItem) => {
    const currentItem =
      items.value.find((item) => item.id === notification.id) ||
      recent.value.find((item) => item.id === notification.id) ||
      (selectedItem.value?.id === notification.id ? selectedItem.value : null)

    items.value = items.value.map((item) => (item.id === notification.id ? notification : item))
    recent.value = recent.value.map((item) => (item.id === notification.id ? notification : item))

    if (selectedItem.value?.id === notification.id) {
      selectedItem.value = notification
    }

    if (currentItem?.read_at === null && notification.read_at !== null) {
      unreadCount.value = Math.max(0, unreadCount.value - 1)
      summaryFetchedAt.value = Date.now()
    }
  }

  const applyNotification = (notification: RuntimeNotificationItem) => {
    items.value = items.value.map((item) => (item.id === notification.id ? notification : item))
    recent.value = recent.value.map((item) => (item.id === notification.id ? notification : item))

    if (selectedItem.value?.id === notification.id) {
      selectedItem.value = notification
    }
  }

  const markCollectionAsRead = (collection: RuntimeNotificationItem[]) => {
    const now = new Date().toISOString()

    return collection.map((item) => ({
      ...item,
      read_at: item.read_at || now,
    }))
  }

  const fetchSummary = async (force = false) => {
    const isFresh =
      summaryFetchedAt.value !== null && Date.now() - summaryFetchedAt.value < summaryCacheLifetime

    if (!force && isFresh) {
      return
    }

    if (summaryPromise.value) {
      return summaryPromise.value
    }

    summaryLoading.value = true
    summaryPromise.value = (async () => {
      try {
        const response = await getNotificationSummary()
        applySummary(response.data.body)
      } finally {
        summaryLoading.value = false
        summaryPromise.value = null
      }
    })()

    try {
      await summaryPromise.value
    } catch (error) {
      summaryFetchedAt.value = null
      throw error
    }
  }

  const fetchNotifications = async (overrides: NotificationListFilters = {}, append = false) => {
    listLoading.value = true

    const params = {
      ...filters.value,
      ...overrides,
    }

    try {
      const response = await getNotifications(params)
      const body = response.data.body
      const fetchedItems = body.data || []

      items.value = append
        ? [
            ...items.value,
            ...fetchedItems.filter(
              (item) => !items.value.some((existingItem) => existingItem.id === item.id),
            ),
          ]
        : fetchedItems

      pagination.value = body.pagination || defaultPagination()
      hasMore.value = pagination.value.current_page < pagination.value.last_page
      filters.value = {
        page: params.page || 1,
        per_page: params.per_page || 15,
        status: params.status || 'all',
        search: params.search || '',
      }
    } finally {
      listLoading.value = false
    }
  }

  const fetchNotification = async (notificationId: string) => {
    const response = await getNotification(notificationId)
    applyNotification(response.data.body)
    return response.data.body
  }

  const markRead = async (notificationId: string) => {
    const response = await markNotificationRead(notificationId)
    applyReadNotification(response.data.body)
    return response.data.body
  }

  const markAllRead = async () => {
    actionLoading.value = true

    try {
      const response = await markAllNotificationsRead()
      unreadCount.value = response.data.body.unread_count
      summaryFetchedAt.value = Date.now()
      items.value = markCollectionAsRead(items.value)
      recent.value = markCollectionAsRead(recent.value)

      if (selectedItem.value) {
        selectedItem.value = {
          ...selectedItem.value,
          read_at: selectedItem.value.read_at || new Date().toISOString(),
        }
      }
    } finally {
      actionLoading.value = false
    }
  }

  const startPolling = () => {
    if (pollingTimer.value !== null) {
      return
    }

    const refresh = () => {
      if (document.visibilityState === 'hidden') {
        return
      }

      void fetchSummary(true)
    }

    refresh()
    pollingTimer.value = window.setInterval(refresh, 30_000)
  }

  const stopPolling = () => {
    if (pollingTimer.value === null) {
      return
    }

    window.clearInterval(pollingTimer.value)
    pollingTimer.value = null
  }

  const resetState = () => {
    stopPolling()
    summaryLoading.value = false
    listLoading.value = false
    actionLoading.value = false
    unreadCount.value = 0
    recent.value = []
    items.value = []
    selectedItem.value = null
    pagination.value = defaultPagination()
    hasMore.value = false
    filters.value = defaultFilters()
    summaryFetchedAt.value = null
    summaryPromise.value = null
  }

  return {
    summaryLoading,
    listLoading,
    actionLoading,
    unreadCount,
    recent,
    items,
    selectedItem,
    pagination,
    hasMore,
    filters,
    pollingTimer,
    hasUnread,
    fetchSummary,
    fetchNotifications,
    fetchNotification,
    markRead,
    markAllRead,
    applyNotification,
    applyReadState: applyReadNotification,
    startPolling,
    stopPolling,
    resetState,
  }
})
