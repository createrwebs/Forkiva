<script lang="ts" setup>
import type {
  ServiceCategory,
  ServiceField,
  ServiceItem,
} from '@/modules/service/contracts/Service.ts'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import PageLoader from '@/modules/core/components/PageLoader.vue'
import { useService } from '@/modules/service/composables/service.ts'
import ServiceSettingsDialog from '@/modules/service/pages/admin/service/Partials/ServiceSettingsDialog.vue'

const { t } = useI18n()
const { loading, getServices, getService } = useService()

const categories = ref<ServiceCategory[]>([])
const selectedService = ref<ServiceItem | null>(null)
const dialogVisible = ref(false)
const openingService = ref<string | null>(null)

onMounted(loadServices)

async function loadServices() {
  categories.value = await getServices()
}

async function openService(service: ServiceItem) {
  openingService.value = `${service.category}.${service.key}`
  const freshService = await getService(service.category, service.key)
  selectedService.value = freshService || service
  dialogVisible.value = true
  openingService.value = null
}

function replaceService(updatedService: ServiceItem) {
  categories.value = categories.value.map((category) => {
    if (category.key !== updatedService.category) {
      return category
    }

    return {
      ...category,
      services: category.services.map((service) =>
        service.key === updatedService.key ? updatedService : service,
      ),
    }
  })
}

function serviceEnabled(service: ServiceItem): boolean {
  return Boolean(service.values?.enabled)
}

function selectorField(service: ServiceItem): ServiceField | undefined {
  return service.settings.find((field) => field.type === 'dropdown')
}

function selectorLabel(service: ServiceItem): string | null {
  const field = selectorField(service)
  if (!field) {
    return null
  }

  if (field.value_option?.name) {
    return field.value_option.name
  }

  const value = service.values?.[field.key]
  if (!value) {
    return null
  }

  return field.options?.find((option) => option.id === value)?.name || String(value)
}

function serviceIsOpening(service: ServiceItem): boolean {
  return openingService.value === `${service.category}.${service.key}`
}

function serviceColor(service: ServiceItem): string {
  return service.color || '#f97316'
}

function serviceInitial(service: ServiceItem): string {
  return service.name?.trim()?.charAt(0)?.toUpperCase() || 'S'
}
</script>

<template>
  <PageLoader :loading="loading && categories.length === 0" />

  <div
    v-if="!loading || categories.length > 0"
    class="service-page"
  >
    <VAlert
      v-if="categories.length === 0"
      color="info"
      variant="tonal"
    >
      {{ t('admin::admin.table.no_data_available') }}
    </VAlert>

    <section
      v-for="category in categories"
      :key="category.key"
      class="service-category"
    >
      <div class="service-category__head">
        <span class="service-category__icon">
          <VIcon
            :icon="category.icon || 'tabler-category'"
            size="20"
          />
        </span>
        <div>
          <div class="service-category__title">
            {{ category.label }}
          </div>
          <div
            v-if="category.description"
            class="service-category__description"
          >
            {{ category.description }}
          </div>
        </div>
      </div>

      <VRow class="service-grid">
        <VCol
          v-for="service in category.services"
          :key="service.key"
          class="service-grid__item"
          cols="12"
          lg="3"
          sm="6"
        >
          <button
            class="service-card"
            :class="{ 'is-disabled': !serviceEnabled(service) }"
            :disabled="serviceIsOpening(service)"
            :style="{ '--service-accent': serviceColor(service) }"
            type="button"
            @click="openService(service)"
          >
            <span class="service-card__wash" />

            <div class="service-card__top">
              <span class="service-card__icon">
                <VIcon
                  v-if="service.icon"
                  :icon="service.icon"
                  size="24"
                />
                <span v-else>{{ serviceInitial(service) }}</span>
              </span>

              <span
                class="service-card__status"
                :class="{ 'is-active': serviceEnabled(service) }"
              >
                <span />
                {{
                  serviceEnabled(service) ? t('admin::admin.active') : t('admin::admin.inactive')
                }}
              </span>
            </div>

            <div class="service-card__body">
              <h3>
                {{ service.name }}
              </h3>
              <p v-if="service.description">
                {{ service.description }}
              </p>
            </div>

            <div class="service-card__foot">
              <span class="service-card__provider">
                {{ selectorLabel(service) || t('service::services.service') }}
              </span>
              <span class="service-card__action">
                <VProgressCircular
                  v-if="serviceIsOpening(service)"
                  color="primary"
                  indeterminate
                  size="18"
                  width="2"
                />
                <VIcon
                  v-else
                  icon="tabler-settings"
                  size="18"
                />
              </span>
            </div>
          </button>
        </VCol>
      </VRow>
    </section>
  </div>

  <ServiceSettingsDialog
    v-model="dialogVisible"
    :service="selectedService"
    @saved="replaceService"
  />
</template>

<style lang="scss" scoped>
.service-page {
  padding-block-start: 2px;
}

.service-category {
  margin-block-end: 28px;
}

.service-category__head {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-block-end: 18px;
}

.service-category__icon {
  display: inline-grid;
  flex: 0 0 42px;
  block-size: 42px;
  inline-size: 42px;
  place-items: center;
  border: 1px solid rgba(var(--v-theme-primary), 0.18);
  border-radius: 8px;
  background:
    linear-gradient(135deg, rgba(var(--v-theme-primary), 0.14), rgba(var(--v-theme-warning), 0.11)),
    rgb(var(--v-theme-surface));
  color: rgb(var(--v-theme-primary));
}

.service-category__title {
  color: rgb(var(--v-theme-on-surface));
  font-size: 1.03rem;
  font-weight: 800;
  line-height: 1.2;
}

.service-category__description {
  color: rgba(var(--v-theme-on-surface), 0.64);
  font-size: 0.9rem;
  line-height: 1.45;
  margin-block-start: 4px;
}

.service-grid {
  margin-block: 0;
}

.service-grid__item {
  display: flex;
}

.service-card {
  position: relative;
  display: flex;
  overflow: hidden;
  flex-direction: column;
  justify-content: space-between;
  min-block-size: 192px;
  block-size: 100%;
  inline-size: 100%;
  padding: 18px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 16px;
  appearance: none;
  background:
    radial-gradient(
      circle at 18% 0%,
      color-mix(in srgb, var(--service-accent) 20%, transparent),
      transparent 32%
    ),
    linear-gradient(
      145deg,
      color-mix(in srgb, rgb(var(--v-theme-surface)) 92%, var(--service-accent) 8%),
      rgb(var(--v-theme-surface)) 70%
    );
  color: rgb(var(--v-theme-on-surface));
  cursor: pointer;
  text-align: start;
  transition:
    border-color 0.18s ease,
    transform 0.18s ease;
}

.service-card::after {
  position: absolute;
  inset-block-start: 0;
  inset-inline-end: 0;
  block-size: 70px;
  inline-size: 70px;
  border-end-start-radius: 48px;
  background: color-mix(in srgb, var(--service-accent) 16%, transparent);
  content: '';
}

.service-card:disabled {
  cursor: wait;
  opacity: 0.84;
}

.service-card:hover {
  border-color: color-mix(in srgb, var(--service-accent) 42%, rgba(var(--v-theme-on-surface), 0.1));
  transform: translateY(-2px);
}

.service-card__top,
.service-card__foot {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.service-card__icon {
  display: inline-grid;
  block-size: 46px;
  inline-size: 46px;
  place-items: center;
  border-radius: 14px;
  background: color-mix(in srgb, var(--service-accent) 15%, rgb(var(--v-theme-surface)));
  color: var(--service-accent);
  font-size: 1rem;
  font-weight: 800;
}

.service-card__status {
  display: inline-flex;
  align-items: center;
  padding: 5px 8px;
  border-radius: 12px;
  background: rgba(var(--v-theme-on-surface), 0.07);
  color: rgba(var(--v-theme-on-surface), 0.62);
  font-size: 0.74rem;
  font-weight: 700;
  gap: 6px;
  line-height: 1;
}

.service-card__status span {
  display: inline-block;
  block-size: 7px;
  inline-size: 7px;
  border-radius: 50%;
  background: currentColor;
}

.service-card__status.is-active {
  background: rgba(var(--v-theme-success), 0.13);
  color: rgb(var(--v-theme-success));
}

.service-card__body {
  position: relative;
  z-index: 1;
  margin-block: 22px 18px;
}

.service-card__body h3 {
  color: rgb(var(--v-theme-on-surface));
  font-size: 1.02rem;
  font-weight: 850;
  line-height: 1.24;
  margin-block-end: 8px;
}

.service-card__body p {
  display: -webkit-box;
  overflow: hidden;
  color: rgba(var(--v-theme-on-surface), 0.66);
  font-size: 0.84rem;
  line-height: 1.48;
  margin: 0;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.service-card__provider {
  overflow: hidden;
  max-inline-size: calc(100% - 40px);
  color: rgba(var(--v-theme-on-surface), 0.66);
  font-size: 0.79rem;
  font-weight: 750;
  text-overflow: ellipsis;
  text-transform: uppercase;
  white-space: nowrap;
}

.service-card__action {
  display: inline-grid;
  block-size: 30px;
  inline-size: 30px;
  place-items: center;
  border-radius: 12px;
  background: rgba(var(--v-theme-surface), 0.72);
  color: var(--service-accent);
}

.service-card.is-disabled .service-card__icon {
  filter: saturate(0.65);
}
</style>
