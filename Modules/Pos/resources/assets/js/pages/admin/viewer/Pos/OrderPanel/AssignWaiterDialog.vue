<script setup lang="ts">
import type { PosForm, PosMeta } from '@/modules/pos/contracts/posViewer.ts'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{ form: PosForm; meta: PosMeta; disabled: boolean }>()
const open = defineModel<boolean>({ required: true })
const { t } = useI18n()
const search = ref('')
const waiters = computed(() =>
  props.meta.waiters.filter((waiter) =>
    waiter.name.toLocaleLowerCase().includes(search.value.trim().toLocaleLowerCase()),
  ),
)

function select(waiter: PosForm['waiter']) {
  if (props.disabled) return
  props.form.waiter = waiter
  open.value = false
}
</script>

<template>
  <VDialog
    v-model="open"
    max-width="380"
    :aria-label="t('seatingplan::tables.assign_waiter')"
    scrollable
  >
    <VCard class="pos-assign-waiter-dialog">
      <div class="pos-customer-dialog-header">
        <span class="pos-customer-dialog-icon">
          <VIcon
            icon="tabler-user-pentagon"
            size="23"
          />
        </span>
        <div class="flex-grow-1">
          <h2>{{ t('seatingplan::tables.assign_waiter') }}</h2>
          <p class="pos-context-label">
            {{ t('pos::pos_viewer.workspace.current_order') }}
            <template v-if="form.table">· {{ form.table.name }}</template>
          </p>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          color="default"
          :aria-label="t('admin::admin.buttons.close')"
          @click="open = false"
        />
      </div>
      <VCardText class="pos-waiter-picker">
        <VTextField
          v-model="search"
          :placeholder="t('admin::admin.table.search_placeholder')"
          prepend-inner-icon="tabler-search"
          variant="outlined"
          hide-details
          autofocus
        />
        <div class="pos-waiter-grid">
          <button
            type="button"
            class="pos-waiter-choice pos-waiter-choice--unassigned"
            :aria-pressed="!form.waiter"
            :disabled="disabled"
            @click="select(null)"
          >
            <VIcon
              icon="tabler-user-off"
              size="18"
            />
            <span>{{ t('seatingplan::tables.unassigned') }}</span>
            <VIcon
              v-if="!form.waiter"
              icon="tabler-check"
              size="16"
            />
          </button>
          <button
            v-for="waiter in waiters"
            :key="waiter.id"
            type="button"
            class="pos-waiter-choice"
            :aria-pressed="form.waiter?.id === waiter.id"
            :disabled="disabled"
            @click="select(waiter)"
          >
            <span class="pos-waiter-initial">
              {{ waiter.name.trim().charAt(0).toLocaleUpperCase() }}
            </span>
            <span class="pos-waiter-name">{{ waiter.name }}</span>
            <VIcon
              v-if="form.waiter?.id === waiter.id"
              class="pos-waiter-check"
              icon="tabler-circle-check-filled"
              size="16"
            />
          </button>
          <p
            v-if="!waiters.length"
            class="pos-waiter-empty text-medium-emphasis"
          >
            {{ t('admin::admin.table.no_matching_records_found') }}
          </p>
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>
