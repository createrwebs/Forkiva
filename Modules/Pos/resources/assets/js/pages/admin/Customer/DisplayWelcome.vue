<script setup lang="ts">
import { useI18n } from 'vue-i18n'
defineProps<{
  title: string
  description: string
  connected: boolean
  terminal: boolean
  stale: boolean
}>()
const { t } = useI18n()
const label = (key: string) => t(`pos::pos_viewer.customer_display.${key}`)
</script>

<template>
  <div class="display-empty">
    <div
      class="welcome-card"
      tabindex="0"
      :aria-label="title"
    >
      <div class="welcome-copy">
        <span
          class="welcome-icon"
          aria-hidden="true"
        >
          <VIcon
            :icon="
              terminal
                ? 'tabler-device-desktop-off'
                : stale
                  ? 'tabler-wifi-off'
                  : 'tabler-shopping-bag'
            "
            size="58"
          />
        </span>
        <h1>{{ title }}</h1>
        <h2 v-if="connected">{{ label('welcome_subtitle') }}</h2>
        <p>{{ description }}</p>
        <div
          v-if="connected"
          class="welcome-waiting"
        >
          {{ label('waiting_order') }}
          <span
            class="waiting-dots"
            aria-hidden="true"
          >
            <i />
            <i />
            <i />
          </span>
        </div>
        <slot />
      </div>
      <div
        class="welcome-art"
        aria-hidden="true"
      >
        <svg
          viewBox="0 0 440 390"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
        >
          <circle
            cx="244"
            cy="174"
            r="143"
            fill="var(--display-subtle-tint)"
          />
          <circle
            cx="91"
            cy="264"
            r="61"
            fill="var(--display-tint)"
          />
          <ellipse
            cx="238"
            cy="342"
            rx="169"
            ry="22"
            fill="var(--display-subtle-tint)"
          />
          <rect
            x="123"
            y="86"
            width="198"
            height="241"
            rx="13"
            fill="var(--display-surface)"
          />
          <path
            d="M123 327V99a13 13 0 0 1 13-13h172a13 13 0 0 1 13 13v123M123 327l21-17 21 17 21-17 21 17 21-17 21 17"
            fill="none"
            stroke="var(--display-primary)"
            stroke-width="5"
            stroke-linejoin="round"
          />
          <path
            d="M159 147h122M159 190h99M159 233h74"
            stroke="var(--display-muted)"
            stroke-opacity="0.4"
            stroke-width="17"
            stroke-linecap="round"
          />
          <path
            d="M281 249h69l9 68a9 9 0 0 1-9 10h-69a9 9 0 0 1-9-10l9-68Z"
            fill="var(--display-surface)"
            stroke="var(--display-primary)"
            stroke-width="7"
            stroke-linejoin="round"
          />
          <path
            d="M295 263v-21a21 21 0 0 1 42 0v21"
            stroke="var(--display-primary)"
            stroke-width="7"
            stroke-linecap="round"
          />
        </svg>
      </div>
    </div>
    <p class="welcome-follow">
      <VIcon
        icon="tabler-receipt"
        size="32"
        aria-hidden="true"
      />
      {{ label('follow_order') }}
    </p>
  </div>
</template>

<style scoped lang="scss">
.display-empty {
  display: flex;
  flex-direction: column;
  inline-size: 100%;
  max-inline-size: 1080px;
  max-block-size: 100%;
  min-block-size: 0;
  margin: auto;
}
.welcome-card {
  display: grid;
  grid-template-columns: 1.2fr 1fr;
  align-items: center;
  min-block-size: 0;
  padding: clamp(16px, 3dvh, 32px);
  background: var(--display-surface);
  border: 1px solid var(--display-border);
  border-radius: 18px;
  overflow-y: auto;
  overscroll-behavior: contain;
  scrollbar-width: thin;
}
.welcome-copy {
  display: flex;
  align-items: center;
  flex-direction: column;
  text-align: center;
  gap: clamp(8px, 1.6dvh, 14px);
  padding-inline: var(--display-space);
  min-inline-size: 0;
}
.welcome-icon {
  display: grid;
  place-items: center;
  inline-size: clamp(54px, 9dvh, 80px);
  block-size: clamp(48px, 8dvh, 72px);
  background: var(--display-tint);
  color: var(--display-primary);
  border-radius: 20px;
  flex-shrink: 0;
}
.welcome-icon :deep(.v-icon) {
  font-size: clamp(32px, 5.4dvh, 44px) !important;
}
.welcome-copy h1 {
  font-size: clamp(24px, min(2.5vw, 5dvh), 40px);
  line-height: 1.5;
  color: var(--display-text);
  margin: 0;
  overflow-wrap: anywhere;
}
.welcome-copy h2 {
  font-size: var(--display-section);
  line-height: 1.5;
  color: var(--display-muted);
  margin: 0;
}
.welcome-copy p {
  color: var(--display-muted);
  font-size: var(--display-body);
  line-height: 1.7;
  margin: 0;
}
.welcome-waiting {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  border-radius: 30px;
  background: var(--display-tint);
  color: var(--display-accent);
  font-size: var(--display-body);
  font-weight: 700;
  padding: 10px 20px;
}
.waiting-dots {
  display: flex;
  gap: 6px;
}
.waiting-dots i {
  inline-size: 7px;
  block-size: 7px;
  background: var(--display-primary);
  border-radius: 50%;
}
.welcome-art {
  padding-inline-start: 24px;
  border-inline-start: 1px solid var(--display-border);
}
.welcome-art svg {
  display: block;
  inline-size: 100%;
  max-block-size: min(32dvh, 270px);
}
.welcome-follow {
  flex: 0 0 auto;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 8px;
  color: var(--display-muted);
  font-size: var(--display-caption);
  margin: clamp(8px, 2dvh, 18px) 0 0;
}
.welcome-follow :deep(.v-icon) {
  font-size: 23px !important;
}
.welcome-copy :deep(.display-customer) {
  align-self: stretch;
}
.welcome-card:focus-visible {
  outline: 2px solid var(--display-primary);
  outline-offset: -3px;
}
@media (max-width: 699px) {
  .welcome-card {
    grid-template-columns: 1fr;
    padding: clamp(12px, 2.6dvh, 22px);
  }
  .welcome-copy {
    padding: 0;
  }
  .welcome-art {
    border: 0;
    padding: 0;
    inline-size: min(34dvh, 180px);
    margin: 10px auto 0;
  }
  .welcome-art svg {
    max-block-size: 20dvh;
  }
  .welcome-follow {
    text-align: center;
  }
}
@media (max-height: 599px) and (max-width: 699px) {
  .welcome-art {
    display: none;
  }
}
@media (max-height: 399px) {
  .welcome-icon {
    display: none;
  }
}
</style>
