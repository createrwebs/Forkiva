<script lang="ts" setup>
import type { ThemeMode } from '@/modules/core/contracts/AppState.ts'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/modules/core/stores/appStore.ts'
import { useUserPreference } from '@/modules/userPreference/composables/userPreference.ts'

const themes = [
  {
    name: 'light',
    icon: 'tabler-sun',
  },
  {
    name: 'dark',
    icon: 'tabler-moon',
  },
  {
    name: 'system',
    icon: 'tabler-device-desktop',
  },
] as const

const appStore = useAppStore()
const preferences = useUserPreference()
function changeTheme(mode: ThemeMode) {
  preferences.setThemeMode(mode)
}

const { t } = useI18n()
</script>

<template>
  <section
    class="profile-theme"
    :aria-label="t('admin::navbar.theme_mode')"
  >
    <p class="profile-theme__label">{{ t('admin::navbar.theme_mode') }}</p>
    <div class="theme-palette">
      <button
        v-for="theme in themes"
        :key="theme.name"
        :aria-label="t(`admin::navbar.theme_modes.${theme.name}`)"
        :aria-pressed="theme.name === appStore.appThemeMode"
        :class="[
          'theme-choice',
          `theme-choice--${theme.name}`,
          { 'theme-choice--active': theme.name === appStore.appThemeMode },
        ]"
        type="button"
        @click="changeTheme(theme.name)"
      >
        <span
          class="theme-choice__preview"
          aria-hidden="true"
        >
          <span class="theme-choice__preview-bar">
            <i />
            <i />
            <i />
          </span>
          <span class="theme-choice__preview-body">
            <i class="theme-choice__preview-nav" />
            <span class="theme-choice__preview-content">
              <i />
              <i />
              <i />
            </span>
          </span>
        </span>

        <span class="theme-choice__footer">
          <span class="theme-choice__name">
            <VIcon
              :icon="theme.icon"
              size="17"
            />
            {{ t(`admin::navbar.theme_modes.${theme.name}`) }}
          </span>

          <span class="theme-choice__check">
            <VIcon
              icon="tabler-check"
              size="14"
            />
          </span>
        </span>
      </button>
    </div>
  </section>
</template>

<style lang="scss" scoped>
.profile-theme__label {
  margin: 0 0 0.65rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 0.75rem;
  font-weight: 650;
}

.theme-palette {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.5rem;
}

.theme-choice {
  position: relative;
  display: grid;
  min-inline-size: 0;
  gap: 0.5rem;
  padding: 0.5rem;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 12px;
  background: rgba(var(--v-theme-on-surface), 0.025);
  color: rgba(var(--v-theme-on-surface), 0.72);
  cursor: pointer;
  transition:
    border-color 180ms ease,
    background-color 180ms ease,
    box-shadow 180ms ease,
    transform 180ms ease;
}

.theme-choice:hover {
  border-color: rgba(var(--v-theme-on-surface), 0.18);
  background: rgba(var(--v-theme-on-surface), 0.045);
  transform: translateY(-1px);
}

.theme-choice:focus-visible {
  outline: 3px solid rgba(var(--v-theme-primary), 0.18);
  outline-offset: 2px;
}

.theme-choice--active {
  border-color: rgba(var(--v-theme-primary), 0.5);
  background: rgba(var(--v-theme-primary), 0.06);
  box-shadow: 0 8px 20px rgba(var(--v-theme-primary), 0.1);
  color: rgb(var(--v-theme-on-surface));
}

.theme-choice__preview {
  overflow: hidden;
  position: relative;
  display: grid;
  border: 1px solid rgba(31, 41, 55, 0.12);
  border-radius: 10px;
  background: #f7f8fb;
  block-size: 50px;
  box-shadow: 0 6px 14px rgba(17, 24, 39, 0.08);
}

.theme-choice__preview-bar {
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 3px;
  padding-inline: 7px;
  border-block-end: 1px solid rgba(31, 41, 55, 0.08);
  background: rgba(255, 255, 255, 0.88);
  block-size: 16px;
}

.theme-choice__preview-bar i {
  border-radius: 50%;
  background: #d8dce4;
  block-size: 3px;
  inline-size: 3px;
}

.theme-choice__preview-body {
  z-index: 1;
  display: grid;
  grid-template-columns: 19px 1fr;
  min-block-size: 0;
}

.theme-choice__preview-nav {
  background: #e9ebf0;
}

.theme-choice__preview-content {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 6px 8px;
}

.theme-choice__preview-content i {
  border-radius: 99px;
  background: #dfe3e9;
  block-size: 4px;
}

.theme-choice__preview-content i:first-child {
  inline-size: 70%;
}

.theme-choice__preview-content i:nth-child(2) {
  background: #f58220;
  inline-size: 48%;
}

.theme-choice__preview-content i:last-child {
  inline-size: 84%;
}

.theme-choice--dark .theme-choice__preview {
  border-color: rgba(255, 255, 255, 0.09);
  background: #202131;
}

.theme-choice--dark .theme-choice__preview-bar {
  border-color: rgba(255, 255, 255, 0.08);
  background: #2a2b3e;
}

.theme-choice--dark .theme-choice__preview-bar i {
  background: #5f6279;
}

.theme-choice--dark .theme-choice__preview-nav {
  background: #171826;
}

.theme-choice--dark .theme-choice__preview-content i {
  background: #55586d;
}

.theme-choice--dark .theme-choice__preview-content i:nth-child(2) {
  background: #f58220;
}

.theme-choice--system .theme-choice__preview::after {
  z-index: 0;
  position: absolute;
  background: #202131;
  clip-path: polygon(52% 0, 100% 0, 100% 100%, 22% 100%);
  content: '';
  inset: 0;
}

.theme-choice--system .theme-choice__preview-bar,
.theme-choice--system .theme-choice__preview-body {
  background: transparent;
}

.theme-choice--system .theme-choice__preview-nav {
  background: linear-gradient(110deg, #e9ebf0 45%, #171826 46%);
}

.theme-choice__footer,
.theme-choice__name {
  display: flex;
  align-items: center;
}

.theme-choice__footer {
  min-inline-size: 0;
  justify-content: center;
  gap: 0.35rem;
  padding-inline: 0.15rem;
}

.theme-choice__name {
  min-inline-size: 0;
  gap: 0.35rem;
  font-size: 0.72rem;
  font-weight: 650;
  white-space: nowrap;
}

.theme-choice__check {
  z-index: 2;
  position: absolute;
  inset-block-start: 0.65rem;
  inset-inline-end: 0.65rem;
  inline-size: 20px;
  opacity: 0;
  display: grid;
  flex: 0 0 20px;
  border-radius: 50%;
  background: rgba(var(--v-theme-on-surface), 0.08);
  block-size: 20px;
  color: transparent;
  place-items: center;
  transition:
    background-color 180ms ease,
    color 180ms ease,
    transform 180ms ease;
}

.theme-choice--active .theme-choice__check {
  opacity: 1;
  background: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
  transform: scale(1.04);
}

@media (prefers-reduced-motion: reduce) {
  .theme-choice,
  .theme-choice__check {
    transition: none;
    transform: none;
  }
}
</style>
