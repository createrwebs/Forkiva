<template>
  <div class="me-2 custom-navbar-container">
    <slot />
  </div>
</template>

<style lang="scss" scoped>
.custom-navbar-container {
  position: relative;
  border-radius: 10px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  width: 40px;
  height: 40px;
  display: flex;
  justify-content: center;
  align-items: center;
  background: linear-gradient(
    180deg,
    rgba(var(--v-theme-surface), 0.96),
    rgba(var(--v-theme-surface), 0.82)
  );
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.28),
    0 8px 18px rgba(var(--v-theme-on-surface), 0.06);
  overflow: hidden;
}

.custom-navbar-container :deep(.v-btn:hover) {
  border-radius: 10px !important;
}

.custom-navbar-container :slotted(.v-btn) {
  border-radius: inherit;
  min-width: 100%;
  width: 100%;
  height: 100%;
}

.custom-navbar-container :slotted(.v-btn:hover),
.custom-navbar-container :slotted(.v-btn:focus),
.custom-navbar-container :slotted(.v-btn:focus-visible),
.custom-navbar-container :slotted(.v-btn:active) {
  border-radius: inherit;
}

.custom-navbar-container :slotted(.v-btn)::before,
.custom-navbar-container :slotted(.v-btn)::after {
  border-radius: inherit;
}

.custom-navbar-container :deep(.v-btn) {
  border-radius: inherit;
}

.custom-navbar-container :deep(.v-btn__overlay),
.custom-navbar-container :deep(.v-btn__underlay) {
  border-radius: inherit;
}

.custom-navbar-container :deep(.v-btn__content) {
  border-radius: inherit;
}

.custom-navbar-container :deep(.v-menu > .v-btn),
.custom-navbar-container :deep(.v-overlay-container .v-btn),
.custom-navbar-container :deep(.v-btn:hover),
.custom-navbar-container :deep(.v-btn:focus),
.custom-navbar-container :deep(.v-btn:focus-visible),
.custom-navbar-container :deep(.v-btn:active) {
  border-radius: inherit;
  min-width: 100%;
  width: 100%;
  height: 100%;
}

.custom-navbar-container :deep(.v-btn::before),
.custom-navbar-container :deep(.v-btn::after) {
  border-radius: inherit;
}

:deep(.v-btn):hover {
  transform: translateY(0px);
}
</style>
