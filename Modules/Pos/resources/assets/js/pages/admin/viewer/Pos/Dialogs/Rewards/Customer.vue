<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import { useTheme } from 'vuetify'
import { getContrast } from 'vuetify/lib/util/colorUtils.js'

defineProps<{
  customer: Record<string, any>
}>()

const { t } = useI18n()
const theme = useTheme()
const panelTextColor = computed(() => {
  const primary = theme.current.value.colors.primary

  return getContrast(primary, '#000000') >= getContrast(primary, '#ffffff') ? '#000000' : '#ffffff'
})
</script>

<template>
  <aside
    class="loyalty-pass"
    :style="{
      '--loyalty-pass-text': panelTextColor,
      '--loyalty-pass-highlight': panelTextColor === '#000000' ? '#ffffff' : '#000000',
    }"
  >
    <div class="loyalty-pass__brand">
      <VIcon
        icon="tabler-gift-card"
        size="28"
      />
      <strong>Forkiva</strong>
    </div>

    <div class="loyalty-pass__identity">
      <VAvatar
        class="loyalty-pass__avatar"
        size="66"
      >
        <VIcon
          icon="tabler-user"
          size="30"
        />
      </VAvatar>
      <div class="loyalty-pass__details">
        <span>{{ t('pos::pos_viewer.loyalty_member') }}</span>
        <strong>{{ customer.name }}</strong>
        <small>
          <VIcon
            icon="tabler-phone"
            size="15"
          />
          {{ customer.phone }}
        </small>
      </div>
    </div>

    <div class="loyalty-pass__tier">
      <VAvatar
        class="loyalty-pass__tier-icon"
        rounded="0"
        size="22"
      >
        <VImg
          v-if="customer.tier?.icon?.preview_image_url || customer.tier?.icon?.url"
          :alt="customer.tier?.name"
          :src="customer.tier.icon.preview_image_url || customer.tier.icon.url"
          contain
        >
          <template #error>
            <svg
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              aria-hidden="true"
            >
              <path
                d="m3 6 3 12h12l3-12-6 4-3-6-3 6-6-4Z"
                stroke-linejoin="round"
              />
              <path
                d="M6 21h12"
                stroke-linecap="round"
              />
            </svg>
          </template>
        </VImg>
        <svg
          v-else
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.8"
          aria-hidden="true"
        >
          <path
            d="m3 6 3 12h12l3-12-6 4-3-6-3 6-6-4Z"
            stroke-linejoin="round"
          />
          <path
            d="M6 21h12"
            stroke-linecap="round"
          />
        </svg>
      </VAvatar>
      <strong>{{ customer.tier?.name || '—' }}</strong>
      <span>{{ t('pos::pos_viewer.member') }}</span>
    </div>

    <div class="loyalty-pass__balance">
      <strong>{{ customer.points_balance }}</strong>
      <span>{{ t('pos::pos_viewer.available_points') }}</span>
    </div>

    <div class="loyalty-pass__tagline">
      <span></span>
      <p>{{ t('pos::pos_viewer.loyalty_tagline') }}</p>
    </div>
  </aside>
</template>

<style lang="scss" scoped>
.loyalty-pass {
  position: relative;
  display: flex;
  min-height: 620px;
  flex-direction: column;
  padding: 32px 30px;
  overflow: hidden;
  color: var(--loyalty-pass-text);
  background: linear-gradient(
    155deg,
    color-mix(in srgb, rgb(var(--v-theme-primary)) 88%, var(--loyalty-pass-highlight)),
    rgb(var(--v-theme-primary)) 55%,
    color-mix(in srgb, rgb(var(--v-theme-primary)) 96%, var(--loyalty-pass-highlight))
  );

  &::before {
    position: absolute;
    inset-block-start: -90px;
    inset-inline-end: -125px;
    width: 290px;
    height: 470px;
    border: 22px solid color-mix(in srgb, var(--loyalty-pass-highlight) 32%, transparent);
    border-radius: 50%;
    pointer-events: none;
    content: '';
  }

  &::after {
    position: absolute;
    inset-inline: -65px 30px;
    inset-block-end: -190px;
    height: 340px;
    border-radius: 50%;
    background: color-mix(in srgb, var(--loyalty-pass-highlight) 12%, transparent);
    pointer-events: none;
    content: '';
  }

  > * {
    position: relative;
    z-index: 1;
  }

  &__brand {
    display: flex;
    align-items: center;
    gap: 10px;

    strong {
      font-size: 1.35rem;
      font-weight: 800;
      letter-spacing: -0.025em;
    }
  }

  &__identity {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-top: 66px;
  }

  &__avatar {
    flex: 0 0 auto;
    color: rgb(var(--v-theme-primary));
    background: rgb(var(--v-theme-surface));
    box-shadow: 0 6px 20px rgba(var(--v-theme-on-surface), 0.06);
  }

  &__details {
    display: flex;
    min-width: 0;
    flex-direction: column;

    > span {
      color: var(--loyalty-pass-text);
      font-size: 0.6875rem;
      font-weight: 650;
      letter-spacing: 0.14em;
      text-transform: uppercase;
    }

    strong {
      overflow: hidden;
      margin-top: 3px;
      font-size: 1.25rem;
      font-weight: 800;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    small {
      display: flex;
      align-items: center;
      gap: 5px;
      margin-top: 5px;
      color: var(--loyalty-pass-text);
      font-size: 0.75rem;
    }
  }

  &__tier {
    display: inline-flex;
    align-items: center;
    align-self: flex-start;
    gap: 6px;
    margin-top: 36px;
    padding: 9px 14px;
    border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
    border-radius: 999px;
    color: rgb(var(--v-theme-on-surface));
    background: rgb(var(--v-theme-surface));
    font-size: 0.8125rem;
  }

  &__tier-icon {
    flex-shrink: 0;

    svg {
      width: 22px;
      height: 22px;
    }
  }

  &__balance {
    display: flex;
    flex-direction: column;
    margin-top: 54px;

    strong {
      font-size: 4rem;
      font-weight: 850;
      letter-spacing: -0.065em;
      line-height: 0.95;
    }

    span {
      margin-top: 8px;
      color: var(--loyalty-pass-text);
      font-size: 0.9375rem;
    }
  }

  &__tagline {
    margin-top: auto;
    padding-top: 28px;

    span {
      display: block;
      width: 46px;
      height: 3px;
      border-radius: 3px;
      background: var(--loyalty-pass-text);
    }

    p {
      max-width: 150px;
      margin-top: 12px;
      color: var(--loyalty-pass-text);
      font-size: 0.9375rem;
      font-weight: 550;
      line-height: 1.5;
    }
  }
}

@media (max-width: 800px) {
  .loyalty-pass {
    min-height: auto;
    padding: 22px;
    gap: 18px;

    &::before {
      inset-block-start: -185px;
      inset-inline-end: -80px;
    }

    &__brand strong {
      font-size: 1.05rem;
    }

    &__identity {
      margin-top: 0;
      padding-inline-end: 12px;
    }

    &__tier {
      max-width: 100%;
      flex-wrap: wrap;
      margin: 0;
    }

    &__balance {
      margin: 0;
      padding-top: 16px;
      border-top: 1px solid rgba(var(--v-theme-on-surface), 0.12);

      strong {
        font-size: 2.35rem;
      }

      span {
        margin-top: 3px;
        font-size: 0.75rem;
      }
    }

    &__tagline {
      display: none;
    }
  }
}
</style>
