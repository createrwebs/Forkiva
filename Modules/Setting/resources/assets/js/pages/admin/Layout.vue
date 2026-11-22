<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import { adminSidebar } from '@/modules/setting/sidebars/admin.sidebar.ts'

const { t } = useI18n()
const route = useRoute()
const settingsGroup = adminSidebar.items.find(({ key }) => key === 'admin.settings.edit')
const activeSection = computed(() => settingsGroup?.children?.find(({ key }) => key === route.name))
</script>

<template>
  <VCard>
    <VCardTitle class="border-b pb-2 mb-4 d-flex align-center gap-1 font-weight-bold text-h6">
      <VIcon
        :icon="activeSection?.icon"
        size="20"
      />
      {{ t(route.meta.title as string) }}
    </VCardTitle>

    <VCardText>
      <RouterView />
    </VCardText>
  </VCard>
</template>
