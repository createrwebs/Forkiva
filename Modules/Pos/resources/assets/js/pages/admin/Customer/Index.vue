<script lang="ts" setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useFullscreen } from '@vueuse/core'
import { useAppStore } from '@/modules/core/stores/appStore.ts'
import { summarizeCartTaxes } from '@/modules/cart/utils/taxSummary.ts'
import TaxSummaryRows from '@/modules/tax/components/TaxSummaryRows.vue'
import DisplayCustomer from './DisplayCustomer.vue'
import DisplayWelcome from './DisplayWelcome.vue'
import { useCustomerDisplay } from '@/modules/pos/composables/useCustomerDisplay.ts'

const route = useRoute()
const app = useAppStore()
const { t, locale } = useI18n()
const label = (key: string, params: Record<string, string | number> = {}) =>
  t(`pos::pos_viewer.customer_display.${key}`, params)
const scope = computed(() => new URLSearchParams(route.hash.slice(1)).get('scope') || '')
const {
  cart,
  connection,
  lastUpdated,
  loading,
  terminal,
  retry: retrySnapshot,
} = useCustomerDisplay(() => ({
  scope: scope.value,
  locale: locale.value,
}))
const fullscreenError = ref(false)
const failedImages = ref<Record<string, string>>({})
const summaryLines = ref<HTMLElement | null>(null)
const summaryHasMore = ref(false)
function updateSummaryOverflow() {
  const element = summaryLines.value
  summaryHasMore.value =
    !!element && element.scrollHeight - element.scrollTop > element.clientHeight + 2
}
function showMoreSummary() {
  const element = summaryLines.value
  if (element) element.scrollTop += element.clientHeight * 0.8
}
let summaryObserver: ResizeObserver | undefined
watch(
  summaryLines,
  (element) => {
    summaryObserver?.disconnect()
    if (element && typeof ResizeObserver !== 'undefined') {
      summaryObserver = new ResizeObserver(updateSummaryOverflow)
      summaryObserver.observe(element)
    }
  },
  { flush: 'post' },
)
watch([cart, summaryLines], updateSummaryOverflow, { flush: 'post' })
watch(cart, (snapshot) => {
  const items = snapshot?.items ?? []
  failedImages.value = Object.fromEntries(
    Object.entries(failedImages.value).filter(([id, thumbnail]) =>
      items.some((item) => item.id === id && item.item.thumbnail === thumbnail),
    ),
  )
})
const { isFullscreen, isSupported, toggle } = useFullscreen()
const taxes = computed(() => summarizeCartTaxes(cart.value))
const hasItems = computed(() => !!cart.value?.items.length)
const itemCountLabel = computed(() => label('item_count', { count: cart.value?.quantity ?? 0 }))
const isStale = computed(() => ['offline', 'reconnecting'].includes(connection.value))
const lastUpdateLabel = computed(() =>
  lastUpdated.value
    ? new Intl.DateTimeFormat(locale.value, {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
      }).format(lastUpdated.value)
    : '',
)
const statusMessage = computed(() => label(connection.value))
const emptyTitle = computed(() =>
  label(
    terminal.value
      ? connection.value
      : connection.value === 'connected'
        ? 'welcome'
        : connection.value,
  ),
)
const emptyDescription = computed(() =>
  label(
    connection.value === 'unpaired'
      ? 'pairing_help'
      : terminal.value
        ? 'reopen'
        : isStale.value
          ? 'connection_help'
          : connection.value === 'connected'
            ? 'waiting'
            : 'loading',
  ),
)

async function toggleFullscreen() {
  fullscreenError.value = false
  try {
    await toggle()
  } catch {
    fullscreenError.value = true
  }
}

function retry() {
  retrySnapshot()
}
onMounted(() => window.addEventListener('resize', updateSummaryOverflow))
onBeforeUnmount(() => {
  window.removeEventListener('resize', updateSummaryOverflow)
  summaryObserver?.disconnect()
})
</script>

<template>
  <main
    class="customer-display"
    :class="{ 'has-order': hasItems }"
    :dir="app.isRtl ? 'rtl' : 'ltr'"
  >
    <header class="display-header">
      <div class="display-brand">
        <div class="display-brand-identity">
          <img
            v-if="app.logo"
            :src="app.logo"
            alt=""
            class="display-logo"
          />
          <strong>{{ app.appName }}</strong>
        </div>
        <span class="display-subtitle">{{ label('display_title') }}</span>
      </div>
      <div class="display-controls">
        <span
          class="connection-status"
          :data-state="connection"
          role="status"
          aria-live="polite"
        >
          <span
            class="connection-dot"
            aria-hidden="true"
          />
          {{ statusMessage }}
        </span>
        <button
          v-if="isSupported"
          type="button"
          class="display-icon-button"
          :aria-label="label(isFullscreen ? 'exit_fullscreen' : 'fullscreen')"
          :title="label(isFullscreen ? 'exit_fullscreen' : 'fullscreen')"
          @click="toggleFullscreen"
        >
          <VIcon
            :icon="isFullscreen ? 'tabler-minimize' : 'tabler-maximize'"
            size="24"
          />
        </button>
      </div>
    </header>

    <div
      v-if="isStale || fullscreenError"
      class="display-notice"
      role="status"
    >
      <div>
        <strong>
          {{ fullscreenError ? label('fullscreen_error') : label('connection_help') }}
        </strong>
        <span v-if="isStale && lastUpdateLabel">
          {{ label('last_updated', { time: lastUpdateLabel }) }}
        </span>
      </div>
      <button
        v-if="isStale"
        type="button"
        class="display-retry"
        :disabled="loading"
        @click="retry"
      >
        {{ label('retry') }}
      </button>
    </div>

    <div
      class="display-workspace"
      :class="{ 'display-workspace-empty': !hasItems }"
    >
      <section
        class="display-order"
        :aria-label="label('your_order')"
        :aria-busy="connection === 'connecting'"
      >
        <template v-if="hasItems && cart">
          <div class="order-heading">
            <div>
              <h1>{{ label('your_order') }}</h1>
              <p class="order-meta">
                <span>{{ itemCountLabel }}</span>
                <span
                  v-if="cart.orderReference"
                  class="order-reference"
                >
                  {{ label('order_reference', { reference: cart.orderReference }) }}
                </span>
              </p>
            </div>
            <span
              v-if="cart.orderType.name"
              class="order-type"
            >
              <VIcon
                icon="tabler-shopping-bag"
                size="24"
                aria-hidden="true"
              />
              {{ cart.orderType.name }}
            </span>
          </div>
          <ol
            class="display-items"
            tabindex="0"
            :aria-label="label('items_heading')"
          >
            <li
              v-for="item in cart.items"
              :key="item.id"
              class="display-item"
            >
              <div
                class="item-media"
                aria-hidden="true"
              >
                <img
                  v-if="item.item.thumbnail && failedImages[item.id] !== item.item.thumbnail"
                  :src="item.item.thumbnail"
                  alt=""
                  loading="lazy"
                  decoding="async"
                  @error="failedImages[item.id] = item.item.thumbnail"
                />
                <VIcon
                  v-else
                  icon="tabler-tools-kitchen-2"
                  size="30"
                />
              </div>
              <div class="item-description">
                <h2>{{ item.item.name }}</h2>
                <div class="item-unit">
                  <bdi>
                    <strong :aria-label="t('pos::pos_viewer.quantity')">{{ item.qty }} ×</strong>
                    {{ item.unitPrice.formatted }}
                  </bdi>
                  <span
                    v-if="item.isGift"
                    class="item-gift"
                  >
                    <VIcon
                      icon="tabler-gift"
                      size="16"
                    />
                    {{ label('gift') }}
                  </span>
                </div>
              </div>
              <bdi class="item-amount">{{ item.total.formatted }}</bdi>
              <dl
                v-if="item.options?.length"
                class="item-options"
              >
                <div
                  v-for="option in item.options"
                  :key="option.id"
                  class="item-option"
                >
                  <dt>
                    <VIcon
                      icon="tabler-adjustments-horizontal"
                      size="20"
                      aria-hidden="true"
                    />
                    {{ option.name }}
                  </dt>
                  <dd>
                    <span
                      v-for="(value, index) in option.values"
                      :key="value.id"
                    >
                      {{ value.label }}
                      <template v-if="value.price && value.price.amount !== 0">
                        <bdi class="option-price">
                          ({{ value.price.amount > 0 ? '+' : '' }}{{ value.price.formatted }})
                        </bdi>
                      </template>
                      <template v-if="index < option.values.length - 1">
                        {{ app.isRtl ? '، ' : ', ' }}
                      </template>
                    </span>
                  </dd>
                </div>
              </dl>
            </li>
          </ol>
        </template>
        <DisplayWelcome
          v-else
          :title="
            connection === 'connected' ? label('welcome_title', { brand: app.appName }) : emptyTitle
          "
          :description="emptyDescription"
          :connected="connection === 'connected'"
          :terminal="terminal"
          :stale="isStale"
        >
          <DisplayCustomer
            v-if="cart?.customer"
            :customer="cart.customer"
          />
        </DisplayWelcome>
      </section>

      <aside
        v-if="hasItems && cart"
        class="display-summary"
        :aria-label="t('pos::pos_viewer.order_summary')"
      >
        <div class="summary-content">
          <h2>
            <VIcon
              icon="tabler-receipt"
              size="36"
              aria-hidden="true"
            />
            {{ t('pos::pos_viewer.order_summary') }}
          </h2>
          <div
            ref="summaryLines"
            class="summary-scroll"
            @scroll="updateSummaryOverflow"
            tabindex="0"
            :aria-label="t('pos::pos_viewer.order_summary')"
          >
            <div class="summary-lines">
              <div class="summary-line">
                <VIcon
                  icon="tabler-receipt"
                  size="24"
                  aria-hidden="true"
                />
                <span>{{ t('pos::pos_viewer.subtotal') }}</span>
                <bdi>{{ cart.subTotal.formatted }}</bdi>
              </div>
              <div
                v-if="cart.discount?.value && cart.discount.value.amount > 0"
                class="summary-line summary-discount"
              >
                <VIcon
                  icon="tabler-rosette-discount"
                  size="24"
                  aria-hidden="true"
                />
                <span>
                  {{ t('pos::pos_viewer.discount') }}
                  <small v-if="cart.discount.name">{{ cart.discount.name }}</small>
                </span>
                <bdi>− {{ cart.discount.value?.formatted }}</bdi>
              </div>
              <div
                v-for="tax in taxes"
                :key="`${tax.scope}:${tax.id}`"
                class="summary-tax"
              >
                <VIcon
                  :icon="tax.scope === 'product' ? 'tabler-percentage' : 'tabler-chart-pie'"
                  size="24"
                  aria-hidden="true"
                />
                <TaxSummaryRows :taxes="[tax]" />
              </div>
              <div
                v-for="charge in cart.charges"
                :key="charge.code"
                class="summary-line"
              >
                <VIcon
                  icon="tabler-receipt-2"
                  size="24"
                  aria-hidden="true"
                />
                <span>{{ charge.name }}</span>
                <bdi>{{ charge.amount.formatted }}</bdi>
              </div>
            </div>
            <p
              v-if="isStale"
              class="summary-note"
            >
              <VIcon
                icon="tabler-wifi-off"
                size="34"
                aria-hidden="true"
              />
              <span>{{ label('amount_unconfirmed') }}</span>
            </p>
            <DisplayCustomer
              v-if="cart.customer"
              :customer="cart.customer"
            />
          </div>
          <button
            v-if="summaryHasMore"
            type="button"
            class="summary-more"
            @click="showMoreSummary"
          >
            {{ label('more_details') }}
            <VIcon
              icon="tabler-chevron-down"
              size="18"
              aria-hidden="true"
            />
          </button>
          <div
            class="display-total"
            :class="{ 'total-stale': isStale }"
          >
            <span>{{ label('total_due') }}</span>
            <bdi>{{ cart.total.formatted }}</bdi>
          </div>
        </div>
      </aside>
    </div>
    <footer class="display-footer">
      <span>{{ label('food_together') }}</span>
      <span>{{ label('thank_you', { brand: app.appName }) }}</span>
    </footer>
    <div
      v-if="hasItems && cart"
      class="mobile-total"
      :class="{ 'total-stale': isStale }"
    >
      <span>
        {{ label('total_due') }}
        <small v-if="isStale">{{ label('amount_unconfirmed') }}</small>
      </span>
      <bdi>{{ cart.total.formatted }}</bdi>
    </div>
  </main>
</template>

<style lang="scss" scoped src="./customer-display.scss"></style>
