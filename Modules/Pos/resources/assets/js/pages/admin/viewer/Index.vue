<script lang="ts" setup>
import PageStateWrapper from '@/modules/core/components/PageStateWrapper.vue'
import { usePosViewer } from '@/modules/pos/composables/usePosViewer.ts'
import { computed, provide } from 'vue'
import { posCartKey } from '@/modules/pos/composables/posCartContext'
import { createPosSoundController, posSoundKey } from '@/modules/pos/composables/posSound'
import ConfigurationErrors from '@/modules/pos/pages/admin/viewer/ConfigurationErrors/Index.vue'
import Pos from '@/modules/pos/pages/admin/viewer/Pos/Index.vue'
import TopHeader from './TopHeader.vue'
import './styles/workspace.scss'
import './styles/products.scss'

const isNotFound = ref(false)
const {
  loadConfiguration,
  loadMenuItems,
  loadProducts,
  loadCustomers,
  hasConfigurationErrors,
  form,
  meta,
  cart,
  initOrder,
  reset,
  qintrix,
} = usePosViewer()
provide(posCartKey, cart)
provide(posSoundKey, createPosSoundController(computed(() => meta.value.sounds)))

onBeforeMount(async () => {
  await loadConfiguration()
})
</script>

<template>
  <TopHeader
    v-if="(!form.loading && !isNotFound) || form.reloadBranchData"
    :cart="cart"
    :form="form"
    :meta="meta"
  />

  <PageStateWrapper
    :loading="form.loading"
    :not-found="isNotFound"
  >
    <ConfigurationErrors
      v-if="hasConfigurationErrors"
      :form="form"
      :meta="meta"
      @on-opened-session="loadMenuItems"
    />

    <Pos
      v-else
      :cart="cart"
      :form="form"
      :load-customers="loadCustomers"
      :load-products="loadProducts"
      :meta="meta"
      :qintrix="qintrix"
      @reset="reset"
      @init-order="initOrder"
    />
  </PageStateWrapper>
</template>

<style lang="scss">
.layout-wrapper.layout-nav-type-vertical.pos-layout .layout-navbar,
.layout-wrapper.layout-nav-type-vertical.pos-layout .layout-footer {
  padding-inline: 0 !important;
}

.layout-wrapper.layout-nav-type-vertical.pos-layout .layout-footer {
  display: none;
}

.layout-wrapper.layout-nav-type-vertical.pos-layout .layout-navbar .navbar-content-container {
  box-shadow: none !important;
}

.pos-layout .layout-page-content {
  padding-block: 0.5rem !important;
  padding-inline: 0.5rem !important;
}

.layout-wrapper.layout-nav-type-vertical.pos-layout:not(.layout-navbar-sticky)
  .layout-navbar
  .navbar-content-container {
  margin-block-start: 0 !important;
}
</style>
