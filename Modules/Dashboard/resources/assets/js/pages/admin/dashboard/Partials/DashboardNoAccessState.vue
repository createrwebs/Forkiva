<script lang="ts" setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <VCard
    class="dashboard-no-access"
    data-testid="dashboard-no-access"
    elevation="0"
  >
    <div class="dashboard-no-access__content">
      <span class="dashboard-no-access__icon">
        <VIcon
          icon="tabler-shield-lock"
          size="32"
        />
      </span>
      <h2>{{ t('dashboard::dashboards.empty.access_title') }}</h2>
      <p>{{ t('dashboard::dashboards.empty.access_description') }}</p>
    </div>

    <div
      aria-hidden="true"
      class="dashboard-no-access__preview"
    >
      <div class="dashboard-no-access__toolbar">
        <span />
        <span />
        <span />
      </div>
      <div class="dashboard-no-access__stats">
        <span />
        <span />
        <span />
      </div>
      <div class="dashboard-no-access__chart">
        <span class="dashboard-no-access__line" />
        <span class="dashboard-no-access__bar dashboard-no-access__bar--one" />
        <span class="dashboard-no-access__bar dashboard-no-access__bar--two" />
        <span class="dashboard-no-access__bar dashboard-no-access__bar--three" />
        <span class="dashboard-no-access__bar dashboard-no-access__bar--four" />
        <span class="dashboard-no-access__bar dashboard-no-access__bar--five" />
      </div>
      <span class="dashboard-no-access__lock">
        <VIcon
          icon="tabler-lock"
          size="23"
        />
      </span>
    </div>
  </VCard>
</template>

<style lang="scss" scoped>
.dashboard-no-access {
  position: relative;
  display: grid;
  overflow: hidden;
  grid-template-columns: minmax(0, 0.9fr) minmax(340px, 1.1fr);
  max-inline-size: 980px;
  min-block-size: 390px;
  align-items: center;
  padding: 48px;
  border: 1px solid rgba(var(--v-border-color), 0.13);
  border-radius: 24px;
  margin: 46px auto 0;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 20px 55px rgba(28, 38, 58, 0.07) !important;
  gap: 52px;
}

.dashboard-no-access::before {
  position: absolute;
  background: rgb(var(--v-theme-primary));
  block-size: 84px;
  content: '';
  inline-size: 4px;
  inset-block-start: 48px;
  inset-inline-start: 0;
}

.dashboard-no-access__content {
  display: grid;
  max-inline-size: 390px;
  justify-items: start;
  text-align: start;
}

.dashboard-no-access__icon {
  display: grid;
  border-radius: 18px;
  margin-block-end: 24px;
  background: rgba(var(--v-theme-primary), 0.1);
  block-size: 64px;
  color: rgb(var(--v-theme-primary));
  inline-size: 64px;
  place-items: center;
}

.dashboard-no-access h2 {
  font-size: clamp(1.45rem, 2.4vw, 1.85rem);
  letter-spacing: -0.035em;
  line-height: 1.25;
}

.dashboard-no-access p {
  margin-block: 12px 0;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.9rem;
  line-height: 1.7;
}

.dashboard-no-access__preview {
  position: relative;
  display: grid;
  overflow: hidden;
  min-block-size: 270px;
  padding: 22px;
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 20px;
  background:
    linear-gradient(rgba(var(--v-theme-primary), 0.035), rgba(var(--v-theme-primary), 0)),
    rgb(var(--v-theme-background));
  gap: 18px;
}

.dashboard-no-access__toolbar {
  display: flex;
  gap: 6px;
}

.dashboard-no-access__toolbar span {
  border-radius: 50%;
  background: rgba(var(--v-theme-on-surface), 0.15);
  block-size: 6px;
  inline-size: 6px;
}

.dashboard-no-access__toolbar span:first-child {
  background: rgba(var(--v-theme-primary), 0.55);
}

.dashboard-no-access__stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 9px;
}

.dashboard-no-access__stats span {
  border: 1px solid rgba(var(--v-border-color), 0.1);
  border-radius: 11px;
  background: rgb(var(--v-theme-surface));
  block-size: 54px;
  box-shadow: 0 7px 18px rgba(28, 38, 58, 0.04);
}

.dashboard-no-access__chart {
  position: relative;
  display: flex;
  overflow: hidden;
  min-block-size: 128px;
  align-items: flex-end;
  justify-content: space-around;
  padding: 28px 22px 16px;
  border: 1px solid rgba(var(--v-border-color), 0.1);
  border-radius: 14px;
  background:
    repeating-linear-gradient(
      to bottom,
      transparent,
      transparent 31px,
      rgba(var(--v-border-color), 0.07) 32px
    ),
    rgb(var(--v-theme-surface));
}

.dashboard-no-access__line {
  position: absolute;
  z-index: 1;
  border-block-start: 2px solid rgba(var(--v-theme-primary), 0.62);
  inline-size: 72%;
  inset-block-start: 52px;
  inset-inline-start: 14%;
  transform: rotate(-5deg);
}

.dashboard-no-access__bar {
  border-radius: 5px 5px 2px 2px;
  background: rgba(var(--v-theme-on-surface), 0.09);
  inline-size: 11%;
}

.dashboard-no-access__bar--one {
  block-size: 36%;
}

.dashboard-no-access__bar--two {
  block-size: 57%;
}

.dashboard-no-access__bar--three {
  block-size: 45%;
}

.dashboard-no-access__bar--four {
  block-size: 69%;
}

.dashboard-no-access__bar--five {
  block-size: 52%;
}

.dashboard-no-access__lock {
  position: absolute;
  display: grid;
  border: 1px solid rgba(var(--v-theme-primary), 0.18);
  border-radius: 16px;
  background: rgb(var(--v-theme-surface));
  block-size: 58px;
  box-shadow: 0 14px 30px rgba(28, 38, 58, 0.12);
  color: rgb(var(--v-theme-primary));
  inline-size: 58px;
  inset-block-start: 50%;
  inset-inline-start: 50%;
  place-items: center;
  transform: translate(-50%, -35%);
}

@media (max-width: 840px) {
  .dashboard-no-access {
    grid-template-columns: 1fr;
    max-inline-size: 620px;
    padding: 38px;
    gap: 34px;
  }

  .dashboard-no-access__content {
    max-inline-size: 480px;
  }
}

@media (max-width: 600px) {
  .dashboard-no-access {
    min-block-size: 0;
    padding: 30px 22px 22px;
    border-radius: 20px;
    margin-block-start: 30px;
    gap: 28px;
  }

  .dashboard-no-access::before {
    block-size: 64px;
    inset-block-start: 30px;
  }

  .dashboard-no-access__icon {
    margin-block-end: 20px;
  }

  .dashboard-no-access__preview {
    min-block-size: 230px;
    padding: 16px;
  }

  .dashboard-no-access__chart {
    min-block-size: 108px;
  }
}
</style>
