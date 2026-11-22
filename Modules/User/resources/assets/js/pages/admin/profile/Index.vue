<script setup lang="ts">
import { computed, defineAsyncComponent, ref, watch } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/modules/auth/stores/authStore.ts'
import { useAppStore } from '@/modules/core/stores/appStore.ts'
import ProfileInformation from '@/modules/user/components/profile/ProfileInformation.vue'
import UpdatePassword from '@/modules/user/components/profile/UpdatePassword.vue'
import ProfileSection from '@/modules/user/components/profile/ProfileSection.vue'

const Passkeys = defineAsyncComponent(
  () => import('@/modules/user/components/profile/Passkeys.vue'),
)
const TwoFactor = defineAsyncComponent(
  () => import('@/modules/user/components/profile/TwoFactor.vue'),
)
const { t } = useI18n()
const authStore = useAuthStore()
const appStore = useAppStore()
const active = ref('information')
const busy = ref(false)
const sections = [
  { id: 'information', label: 'user::profiles.information', icon: 'tabler-user' },
  { id: 'password', label: 'admin::navbar.update_password', icon: 'tabler-lock' },
  { id: 'passkeys', label: 'user::auth.passkeys', icon: 'tabler-key' },
  { id: 'two-factor', label: 'user::auth.two_factor_authentication', icon: 'tabler-shield-check' },
]
const initials = computed(() =>
  authStore.getUser?.name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join(''),
)
watch(
  () => authStore.getUser?.id,
  () => {
    active.value = 'information'
    busy.value = false
  },
)
onBeforeRouteLeave(() => !busy.value)
</script>

<template>
  <div class="profile-page">
    <div class="profile-workspace">
      <aside class="profile-sidebar">
        <header class="profile-cover">
          <div
            class="profile-cover__art"
            aria-hidden="true"
          >
            <span
              v-for="ring in 4"
              :key="ring"
              class="profile-cover__ring"
            />
            <span class="profile-cover__planet" />
            <span class="profile-cover__satellite" />
          </div>
        </header>
        <div class="profile-identity">
          <div class="profile-identity__avatar">
            <VAvatar
              size="72"
              color="primary"
            >
              <VImg
                v-if="authStore.getUser?.profile_photo_url"
                :src="authStore.getUser.profile_photo_url"
                :alt="authStore.getUser.name"
              />
              <span v-else>{{ initials }}</span>
            </VAvatar>
          </div>
          <div class="profile-identity__details">
            <h2>{{ authStore.getUser?.name }}</h2>
            <p>{{ authStore.getUser?.email }}</p>
          </div>
          <div
            v-if="authStore.getUser?.role"
            class="profile-identity__role"
          >
            <VIcon
              icon="tabler-user-shield"
              size="20"
            />
            <span>{{ authStore.getUser.role.display_name }}</span>
          </div>
        </div>
        <nav
          class="profile-navigation"
          :aria-label="t('user::profiles.profile')"
        >
          <button
            v-for="section in sections"
            :key="section.id"
            type="button"
            :class="{ 'is-active': active === section.id }"
            :aria-current="active === section.id ? 'page' : undefined"
            :disabled="busy"
            :title="t(section.label)"
            aria-controls="profile-content"
            @click="active = section.id"
          >
            <VIcon
              :icon="section.icon"
              size="20"
            />
            <span>{{ t(section.label) }}</span>
          </button>
        </nav>
      </aside>
      <div
        id="profile-content"
        :key="authStore.getUser?.id"
        class="profile-content"
        :aria-busy="busy"
      >
        <ProfileInformation
          v-if="active === 'information'"
          @busy="busy = $event"
        />
        <UpdatePassword
          v-else-if="active === 'password'"
          @busy="busy = $event"
        />
        <template v-else-if="active === 'passkeys'">
          <Passkeys
            v-if="appStore.settings.passkeys_enabled"
            embedded
            :model-value="true"
            @busy="busy = $event"
          />
          <ProfileSection
            v-else
            icon="tabler-fingerprint"
            :title="t('user::auth.passkeys')"
            :description="t('user::auth.passkey_description')"
          >
            <VAlert
              type="info"
              variant="tonal"
            >
              {{ t('user::auth.passkeys_disabled') }}
            </VAlert>
          </ProfileSection>
        </template>
        <template v-else>
          <TwoFactor
            v-if="appStore.settings.two_factor_enabled"
            embedded
            :model-value="true"
            @busy="busy = $event"
          />
          <ProfileSection
            v-else
            icon="tabler-shield-lock"
            :title="t('user::auth.two_factor_authentication')"
            :description="t('user::auth.two_factor_subtitle')"
          >
            <VAlert
              type="info"
              variant="tonal"
            >
              {{ t('user::auth.two_factor_disabled') }}
            </VAlert>
          </ProfileSection>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.profile-page {
  width: 100%;
  padding-block: 4px 20px;
}
.profile-workspace {
  display: grid;
  grid-template-columns: 284px minmax(0, 1fr);
  align-items: start;
  gap: 36px;
}
.profile-sidebar {
  position: sticky;
  top: 88px;
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 24px rgba(var(--v-theme-on-surface), 0.035);
}
.profile-cover {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  height: 112px;
  padding: 20px;
  background: #242638;
  color: #fff;
}
.profile-cover__art {
  position: absolute;
  inset-block-start: -74px;
  inset-inline-end: -24px;
  width: 250px;
  height: 250px;
  pointer-events: none;
  transform: rotate(-20deg);
}
.profile-cover__ring {
  position: absolute;
  border: 1px solid rgba(226, 230, 255, 0.22);
  border-radius: 50%;
  inset: 0;
  &:nth-child(2) {
    inset: 24px;
  }
  &:nth-child(3) {
    inset: 48px;
  }
  &:nth-child(4) {
    inset: 72px;
  }
}
.profile-cover__planet,
.profile-cover__satellite {
  position: absolute;
  border-radius: 50%;
  background: rgb(var(--v-theme-primary));
}
.profile-cover__planet {
  height: 32px;
  width: 32px;
  inset-block-start: 100px;
  inset-inline-end: 42px;
}
.profile-cover__satellite {
  height: 8px;
  width: 8px;
  inset-block-start: 148px;
  inset-inline-start: 40px;
}
.profile-identity {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 0 20px 22px;
  text-align: center;
}
.profile-identity__avatar {
  margin-top: -38px;
  border: 5px solid rgb(var(--v-theme-surface));
  border-radius: 50%;
  background: rgb(var(--v-theme-surface));
  font-size: 1.45rem;
  font-weight: 600;
}
.profile-identity__details {
  min-width: 0;
  width: 100%;
  h2 {
    font-size: 1.15rem;
    line-height: 1.5;
    font-weight: 700;
    overflow-wrap: anywhere;
    margin: 0 0 4px;
  }
  p {
    font-size: 0.8rem;
    color: rgba(var(--v-theme-on-surface), 0.6);
    overflow-wrap: anywhere;
    margin: 0;
  }
}
.profile-identity__role {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  color: rgba(var(--v-theme-on-surface), 0.7);
  font-size: 0.75rem;
}
.profile-navigation {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 0 14px;
  padding: 16px 0;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  button {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 46px;
    padding: 10px 14px;
    border-radius: 8px;
    text-align: start;
    color: rgba(var(--v-theme-on-surface), 0.7);
    font-size: 0.85rem;
    line-height: 1.5;
    transition: background-color 0.15s;
  }
  button:hover {
    background: rgba(var(--v-theme-primary), 0.04);
    color: rgb(var(--v-theme-primary));
  }
  button:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: -2px;
  }
  button.is-active {
    background: rgba(var(--v-theme-primary), 0.1);
    color: rgb(var(--v-theme-primary));
    font-weight: 600;
  }
  button:disabled {
    opacity: 0.6;
    cursor: wait;
  }
}
.profile-content {
  min-width: 0;
  padding: 24px;
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), 0.22);
  border-radius: 16px;
  box-shadow: 0 4px 24px rgba(var(--v-theme-on-surface), 0.035);
}
.profile-content :deep(.profile-security-panel) {
  > .v-card {
    background: transparent;
    border: 0;
    box-shadow: none !important;
    border-radius: 0 !important;
  }
  .dialog-header {
    padding: 0 0 24px;
  }
  .dialog-heading h2 {
    font-size: 1.25rem;
  }
  .dialog-heading p {
    font-size: 0.85rem;
  }
  .dialog-body {
    padding: 0 !important;
    gap: 14px;
  }
  .dialog-actions {
    margin-top: 16px;
    padding: 16px 0 0;
    border-top-color: rgba(var(--v-border-color), var(--v-border-opacity));
  }
}
@media (max-width: 960px) {
  .profile-workspace {
    grid-template-columns: 250px minmax(0, 1fr);
    gap: 24px;
  }
}
@media (max-width: 700px) {
  .profile-workspace {
    grid-template-columns: minmax(0, 1fr);
    gap: 28px;
  }
  .profile-content {
    padding: 18px;
  }
  .profile-sidebar {
    position: static;
  }
  .profile-cover {
    height: 90px;
  }
  .profile-identity {
    padding-bottom: 16px;
  }
  .profile-navigation {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 4px;
    padding-block: 12px;
    button {
      padding: 10px;
      gap: 8px;
      font-size: 0.8rem;
    }
  }
  .profile-content :deep(.profile-security-panel) {
    .dialog-actions {
      flex-wrap: wrap;
    }
  }
}
@media (prefers-reduced-motion: reduce) {
  .profile-navigation button {
    transition: none;
  }
}
</style>
