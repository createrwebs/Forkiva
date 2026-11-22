<script lang="ts" setup>
import type { UseCart } from '@/modules/cart/composables/cart.ts'
import type { PosForm, PosMeta } from '@/modules/pos/contracts/posViewer.ts'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/modules/core/stores/appStore.ts'
import { useAuth } from '@/modules/auth/composables/auth.ts'

const props = defineProps<{
  form: PosForm
  meta: PosMeta
  cart: UseCart
}>()
const { t } = useI18n()
const { user } = useAuth()
const appStore = useAppStore()

const { form } = toRefs(props)
const posBusy = computed(
  () =>
    form.value.reloadBranchData ||
    props.cart.hasPendingMutations.value ||
    props.cart.processing.value,
)
</script>

<template>
  <teleport to="#main-header-left-content">
    <div class="pos-header-selects">
      <RouterLink
        to="/admin"
        class="pos-brand"
      >
        <img
          v-if="appStore.logo"
          class="pos-brand-logo"
          :src="appStore.logo"
          alt=""
          width="34"
          height="34"
        />
        <strong>{{ appStore.appName }}</strong>
      </RouterLink>
      <VSelect
        v-if="!user?.assigned_to_branch"
        v-model="form.branchId"
        class="pos-select"
        density="compact"
        :disabled="posBusy"
        item-title="name"
        item-value="id"
        :items="meta.branches"
        :loading="form.reloadBranchData"
        :placeholder="t('pos::pos_viewer.select_branch')"
        prepend-inner-icon="tabler-git-branch"
        variant="solo-filled"
      />

      <VSelect
        v-model="form.menuId"
        class="pos-select"
        density="compact"
        :disabled="!form.branchId || posBusy"
        hide-details
        item-title="name"
        item-value="id"
        :items="meta.menus"
        :placeholder="t('pos::pos_viewer.select_menu')"
        prepend-inner-icon="tabler-list-details"
        variant="solo-filled"
      />

      <VSelect
        v-model="form.registerId"
        class="pos-select"
        density="compact"
        :disabled="!form.branchId || posBusy"
        hide-details
        item-title="name"
        item-value="id"
        :items="meta.registers"
        :placeholder="t('pos::pos_viewer.select_register')"
        prepend-inner-icon="tabler-device-desktop-cog"
        variant="solo-filled"
      />
    </div>
  </teleport>
</template>
