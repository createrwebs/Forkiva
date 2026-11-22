<script lang="ts" setup>
import type { RuntimeNotificationItem } from '@/modules/notification/contracts/NotificationCenter.ts'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import NavbarAction from '@/app/layouts/components/NavbarAction.vue'
import { useAuthStore } from '@/modules/auth/stores/authStore.ts'
import { useNotificationCenterStore } from '@/modules/notification/stores/notificationCenterStore.ts'

const { t } = useI18n()
const authStore = useAuthStore()
const store = useNotificationCenterStore()
const { summaryLoading, unreadCount, recent } = storeToRefs(store)
const route = useRoute()
const router = useRouter()
const menu = ref(false)
const activeUserId = computed(() => authStore.getUser?.id ?? null)

const badgeContent = computed(() => (unreadCount.value > 99 ? '99+' : String(unreadCount.value)))
const unreadCountText = computed(
  () => `${unreadCount.value} ${t('notification::notifications.unread')}`,
)

async function openAll(notificationId?: string) {
  menu.value = false

  await router.push({
    name: 'admin.notifications.index',
    query: notificationId ? { selected: notificationId } : {},
  })
}

async function handleSelect(notification: RuntimeNotificationItem) {
  await openAll(notification.id)
}

function handleFocus() {
  void store.fetchSummary()
}

watch(activeUserId, async (currentUserId, previousUserId) => {
  if (currentUserId === previousUserId) {
    return
  }

  menu.value = false
  store.resetState()

  if (!currentUserId || route.meta?.target !== 'admin') {
    return
  }

  store.startPolling()
  await store.fetchSummary(true)
})

onMounted(() => {
  if (route.meta?.target === 'admin') {
    store.startPolling()
  }

  window.addEventListener('focus', handleFocus)
})

onBeforeUnmount(() => {
  store.stopPolling()
  window.removeEventListener('focus', handleFocus)
})
</script>

<template>
  <VMenu
    v-model="menu"
    :close-on-content-click="false"
    location="bottom end"
    width="380"
  >
    <template #activator="{ props }">
      <VTooltip :text="$t('notification::notifications.notifications')">
        <template #activator="{ props: tooltipProps }">
          <NavbarAction class="notification-bell-trigger">
            <VBtn
              color="default"
              icon="tabler-bell"
              v-bind="{ ...props, ...tooltipProps }"
              variant="text"
            />

            <span
              v-if="unreadCount > 0"
              class="notification-bell-trigger__count"
            >
              {{ badgeContent }}
            </span>
          </NavbarAction>
        </template>
      </VTooltip>
    </template>

    <VCard class="notification-bell-card">
      <div class="notification-bell-card__header">
        <div>
          <div class="text-body-1 font-weight-medium">
            {{ $t('notification::notifications.recent_notifications') }}
          </div>

          <div class="text-caption text-medium-emphasis">
            {{ unreadCountText }}
          </div>
        </div>

        <VBtn
          color="primary"
          size="small"
          variant="text"
          @click="openAll()"
        >
          {{ $t('notification::notifications.view_all') }}
        </VBtn>
      </div>

      <VDivider />

      <div
        v-if="summaryLoading"
        class="notification-bell-card__state"
      >
        <VProgressCircular
          color="primary"
          indeterminate
          size="22"
        />
      </div>

      <div
        v-else-if="recent.length === 0"
        class="notification-bell-card__state text-medium-emphasis"
      >
        {{ $t('notification::notifications.empty_recent_state') }}
      </div>

      <VList
        v-else
        class="py-0"
      >
        <VListItem
          v-for="notification in recent"
          :key="notification.id"
          class="notification-bell-card__item"
          @click="handleSelect(notification)"
        >
          <template #prepend>
            <VAvatar
              :color="notification.read_at ? 'default' : 'primary'"
              size="34"
              variant="tonal"
            >
              <VIcon
                icon="tabler-bell"
                size="18"
              />
            </VAvatar>
          </template>

          <VListItemTitle class="text-body-2 font-weight-medium">
            {{
              notification.title ||
              notification.subject ||
              $t('notification::notifications.notification')
            }}
          </VListItemTitle>

          <VListItemSubtitle class="notification-bell-card__subtitle">
            {{ notification.content || notification.subject || notification.type }}
          </VListItemSubtitle>

          <template #append>
            <div
              v-if="!notification.read_at"
              class="notification-bell-card__dot"
            />
          </template>
        </VListItem>
      </VList>
    </VCard>
  </VMenu>
</template>

<style lang="scss" scoped>
.notification-bell-trigger {
  position: relative;

  &__count {
    position: absolute;
    inset-block-start: 0.2rem;
    inset-inline-end: 0.2rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1rem;
    height: 1rem;
    padding-inline: 0.1875rem;
    border-radius: 999px;
    background: rgb(var(--v-theme-error));
    color: rgb(var(--v-theme-on-error));
    border: 2px solid rgb(var(--v-theme-surface));
    box-shadow: 0 2px 6px rgba(var(--v-theme-error), 0.18);
    font-size: 0.6rem;
    font-weight: 700;
    line-height: 1;
    z-index: 2;
    pointer-events: none;
  }

  :deep(.v-btn) {
    inline-size: 100%;
    block-size: 100%;
  }
}

.notification-bell-card {
  border-radius: 10px;

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 1rem;
  }

  &__state {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 10rem;
    padding: 1rem;
    text-align: center;
  }

  &__item {
    padding-block: 0.875rem;
    padding-inline: 1rem;
  }

  &__subtitle {
    overflow: hidden;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    white-space: normal;
  }

  &__dot {
    border-radius: 999px;
    background: rgb(var(--v-theme-primary));
    block-size: 0.5rem;
    inline-size: 0.5rem;
  }
}
</style>
