<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import { computed, defineAsyncComponent, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import { useAuthStore } from '@/modules/auth/stores/authStore.ts'
import { hasDriverPanelAccess } from '@/modules/auth/utils/driverPanelAccess.ts'
import { hasEmployeePanelAccess } from '@/modules/auth/utils/employeePanelAccess.ts'
import NavbarLanguageSwitcher from '@/app/layouts/components/NavbarLanguageSwitcher.vue'
import NavbarThemeSwitcher from '@/app/layouts/components/NavbarThemeSwitcher.vue'
import RoleBadge from '@/modules/core/components/RoleBadge.vue'

const LogoutDialog = defineAsyncComponent(() => import('./Dialogs/Logout.vue'))

const auth = useAuth()
const authStore = useAuthStore()
const { t } = useI18n()
const router = useRouter()
const isMenuOpen = ref(false)
const showLogoutDialog = ref(false)
const switchingAccountId = ref<number | null>(null)

async function switchAccount(accountId: number) {
  if (switchingAccountId.value !== null || accountId === authStore.getUser?.id) {
    return
  }
  switchingAccountId.value = accountId
  try {
    await auth.switchAccount(accountId)
    isMenuOpen.value = false
  } finally {
    switchingAccountId.value = null
  }
}

async function addAccount() {
  isMenuOpen.value = false
  await auth.goToAddAccount()
}

async function logoutAllAccounts() {
  isMenuOpen.value = false
  await auth.logoutAll()
}

function openProfile() {
  isMenuOpen.value = false
  void router.push({ name: 'admin.profile' })
}

function openLogoutDialog() {
  isMenuOpen.value = false
  showLogoutDialog.value = true
}

const accounts = computed(() => authStore.getAccounts)
const otherAccounts = computed(() =>
  accounts.value.filter((account) => account.user.id !== authStore.getUser?.id),
)
const canOpenDriverPanel = computed(() => {
  return router.hasRoute('driver.dashboard') && hasDriverPanelAccess(authStore.getUser)
})
const canOpenEmployeeDashboard = computed(() => {
  return router.hasRoute('employee.dashboard') && hasEmployeePanelAccess(authStore.getUser)
})

async function goToDriverPanel() {
  if (!canOpenDriverPanel.value) {
    return
  }

  isMenuOpen.value = false
  await router.push({ name: 'driver.dashboard' })
}

async function goToEmployeeDashboard() {
  if (!canOpenEmployeeDashboard.value) {
    return
  }

  isMenuOpen.value = false
  await router.push({ name: 'employee.dashboard' })
}
</script>

<template>
  <VMenu
    v-model="isMenuOpen"
    location="bottom end"
    offset="12"
    :close-on-content-click="false"
    :width="380"
    max-width="calc(100vw - 24px)"
  >
    <template #activator="{ props: menuProps }">
      <button
        v-bind="menuProps"
        class="profile-trigger"
        :class="{ 'profile-trigger--active': isMenuOpen }"
        :aria-label="t('admin::navbar.my_account')"
        type="button"
      >
        <VAvatar
          color="primary"
          size="38"
        >
          <VImg
            :src="authStore.getUser?.profile_photo_url"
            alt=""
          />
        </VAvatar>
        <VIcon
          icon="tabler-chevron-down"
          size="15"
          class="profile-trigger__chevron"
        />
      </button>
    </template>
    <VCard class="profile-menu-card">
      <div class="profile-menu-header">
        <VAvatar
          color="primary"
          size="46"
        >
          <VImg :src="authStore.getUser?.profile_photo_url" />
        </VAvatar>
        <div class="profile-menu-header-content">
          <div class="profile-menu-name-row">
            <p class="profile-menu-name">{{ authStore.getUser?.name }}</p>
          </div>
          <div class="profile-menu-detail-row">
            <p class="profile-menu-email">{{ authStore.getUser?.email }}</p>
            <RoleBadge
              v-if="authStore.getUser?.role"
              :role="authStore.getUser.role"
              class="profile-menu-role"
            />
          </div>
        </div>
      </div>

      <div class="profile-menu-actions">
        <button
          class="menu-action-btn"
          type="button"
          @click="openProfile"
        >
          <span class="menu-action-label">
            <VIcon
              icon="tabler-user-hexagon"
              size="18"
            />
            <span>{{ t('user::profiles.profile') }}</span>
          </span>
          <VIcon
            icon="tabler-arrow-up-right"
            size="18"
            class="profile-action-arrow"
          />
        </button>
        <button
          v-if="canOpenDriverPanel"
          class="menu-action-btn"
          type="button"
          @click="goToDriverPanel"
        >
          <span class="menu-action-label">
            <VIcon
              icon="tabler-truck-delivery"
              size="18"
            />
            <span>{{ t('delivery::sidebar.driver_dashboard') }}</span>
          </span>
        </button>
        <button
          v-if="canOpenEmployeeDashboard"
          class="menu-action-btn"
          type="button"
          @click="goToEmployeeDashboard"
        >
          <span class="menu-action-label">
            <VIcon
              icon="tabler-layout-dashboard"
              size="18"
            />
            <span>{{ t('hr::sidebar.employee_dashboard_navbar') }}</span>
          </span>
        </button>
      </div>

      <div class="profile-preferences">
        <NavbarLanguageSwitcher />
        <NavbarThemeSwitcher />
      </div>

      <div class="accounts-section">
        <p
          v-if="otherAccounts.length > 0"
          class="accounts-title"
        >
          {{ t('admin::navbar.accounts') }}
        </p>
        <button
          v-for="account in otherAccounts"
          :key="account.user.id"
          class="account-item-btn"
          :disabled="switchingAccountId !== null"
          type="button"
          @click="switchAccount(account.user.id)"
        >
          <span class="account-item-left">
            <VAvatar
              class="account-avatar"
              size="34"
            >
              <VImg :src="account.user.profile_photo_url" />
            </VAvatar>
            <span class="account-item-text">
              <span class="account-item-name-row">
                <span class="account-item-name">
                  {{ account.user.name }}
                </span>
                <RoleBadge
                  v-if="account.user.role"
                  :role="account.user.role"
                />
              </span>
              <span class="account-item-email">{{ account.user.email }}</span>
            </span>
          </span>
          <VProgressCircular
            v-if="switchingAccountId === account.user.id"
            color="primary"
            indeterminate
            :size="16"
            :width="2"
          />
        </button>
        <button
          class="account-item-btn add-account"
          type="button"
          @click="addAccount"
        >
          <span class="account-item-left">
            <span class="add-account-icon">
              <VIcon
                icon="tabler-plus"
                size="16"
              />
            </span>
            <span class="account-item-name">
              {{ t('admin::navbar.add_another_account') }}
            </span>
          </span>
        </button>
      </div>

      <div class="logout-section">
        <button
          v-if="accounts.length > 1"
          class="menu-action-btn logout-btn"
          type="button"
          @click="logoutAllAccounts"
        >
          <span class="menu-action-label">
            <VIcon
              icon="tabler-logout"
              size="18"
            />
            <span>{{ t('admin::navbar.logout_all_accounts') }}</span>
          </span>
        </button>
        <button
          class="menu-action-btn logout-btn"
          type="button"
          @click="openLogoutDialog"
        >
          <span class="menu-action-label">
            <VIcon
              icon="tabler-logout"
              size="18"
            />
            <span>{{ t('admin::navbar.logout') }}</span>
          </span>
        </button>
      </div>
    </VCard>
  </VMenu>
  <LogoutDialog
    v-if="showLogoutDialog"
    v-model="showLogoutDialog"
  />
</template>

<style lang="scss" scoped>
.profile-trigger {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: 0.4rem;
  padding-block: 4px;
  padding-inline: 4px 8px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 99px;
  background: rgb(var(--v-theme-surface));
  color: rgba(var(--v-theme-on-surface), 0.65);
  cursor: pointer;
}

.profile-trigger--active,
.profile-trigger:hover {
  border-color: rgba(var(--v-theme-primary), 0.5);
  background: rgba(var(--v-theme-primary), 0.05);
}

.profile-trigger--active .profile-trigger__chevron {
  transform: rotate(180deg);
}

.profile-menu-card {
  overflow-y: auto;
  max-block-size: min(760px, calc(100dvh - 100px));
  padding: 1rem;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 20px !important;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 18px 60px rgba(17, 24, 39, 0.18) !important;
  overscroll-behavior: contain;
}

.profile-menu-header {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  padding: 0.25rem 0.25rem 1rem;
}

.profile-menu-header-content {
  min-inline-size: 0;
  flex: 1;
}

.profile-menu-name {
  margin: 0;
  color: rgb(var(--v-theme-on-surface));
  font-size: 1rem;
  font-weight: 750;
  line-height: 1.4;
  overflow-wrap: anywhere;
}

.profile-menu-detail-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-block-start: 0.25rem;
}

.profile-menu-email {
  flex: 1;
  min-inline-size: 0;
  overflow: hidden;
  margin: 0;
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 0.78rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.profile-menu-role {
  flex-shrink: 0;
  max-inline-size: 50%;

  :deep(.v-chip__content) {
    color: rgb(var(--v-theme-on-surface));
  }
}

.profile-menu-actions {
  overflow: hidden;
  border-radius: 11px;
  background: rgba(var(--v-theme-primary), 0.06);
}

.menu-action-btn {
  display: flex;
  align-items: center;
  justify-content: space-between;
  inline-size: 100%;
  min-block-size: 44px;
  padding: 0.65rem 0.75rem;
  border: 0;
  border-radius: 10px;
  background: transparent;
  text-align: start;
  cursor: pointer;
}

.menu-action-btn:hover,
.account-item-btn:hover {
  background: rgba(var(--v-theme-primary), 0.08);
}

.profile-trigger:focus-visible,
.menu-action-btn:focus-visible,
.account-item-btn:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -2px;
}

.menu-action-label {
  display: inline-flex;
  align-items: center;
  gap: 0.65rem;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.85rem;
  font-weight: 600;
}

.profile-action-arrow {
  color: rgba(var(--v-theme-on-surface), 0.5);
}

.profile-preferences {
  display: grid;
  gap: 1rem;
  margin-block: 1rem;
  padding-block-end: 1rem;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.09);
}

.accounts-title {
  margin: 0 0 0.6rem;
  padding-inline: 0.25rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
}

.account-item-btn {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  inline-size: 100%;
  min-block-size: 44px;
  margin-block-end: 0.3rem;
  padding: 0.6rem;
  border: 1px solid transparent;
  border-radius: 11px;
  background: transparent;
  text-align: start;
  cursor: pointer;
}

.account-item-btn:disabled {
  cursor: wait;
  opacity: 0.65;
}

.account-item-left {
  display: inline-flex;
  align-items: center;
  gap: 0.65rem;
  min-inline-size: 0;
}

.account-item-text {
  display: flex;
  flex-direction: column;
  min-inline-size: 0;
}

.account-item-name-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.25rem;
  min-inline-size: 0;
}

.account-item-name {
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.8rem;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.account-item-email {
  overflow: hidden;
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 0.73rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.account-avatar {
  flex-shrink: 0;
  background: rgba(var(--v-theme-on-surface), 0.08);
}

.add-account-icon {
  display: grid;
  flex: 0 0 34px;
  block-size: 34px;
  border: 1px dashed rgba(var(--v-theme-on-surface), 0.22);
  border-radius: 50%;
  color: rgba(var(--v-theme-on-surface), 0.6);
  place-items: center;
}

.logout-section {
  margin-block-start: 0.65rem;
  padding-block-start: 0.6rem;
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.09);
}

.logout-btn .menu-action-label {
  color: rgb(var(--v-theme-error));
}

.logout-btn:hover {
  background: rgba(var(--v-theme-error), 0.07);
}

@media (max-width: 400px) {
  .profile-menu-card {
    padding: 0.8rem;
  }
}
</style>
