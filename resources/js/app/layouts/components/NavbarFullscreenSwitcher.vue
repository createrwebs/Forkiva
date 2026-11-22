<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import NavbarAction from '@/app/layouts/components/NavbarAction.vue'

const { t } = useI18n()
const toast = useToast()
const isFullscreen = ref(false)
const label = computed(() =>
  t(`admin::navbar.fullscreen.${isFullscreen.value ? 'exit_fullscreen' : 'fullscreen'}`),
)

async function switchFullscreen() {
  try {
    if (document.fullscreenElement) {
      await document.exitFullscreen()
    } else {
      await document.documentElement.requestFullscreen()
    }
  } catch {
    toast.error(t('admin::messages.something_went_wrong'))
  }
}

function syncFullscreen() {
  isFullscreen.value = document.fullscreenElement !== null
}

onMounted(() => {
  syncFullscreen()
  document.addEventListener('fullscreenchange', syncFullscreen)
})

onBeforeUnmount(() => document.removeEventListener('fullscreenchange', syncFullscreen))
</script>

<template>
  <VTooltip>
    <template #activator="{ props }">
      <NavbarAction v-bind="props">
        <VBtn
          :aria-label="label"
          :icon="isFullscreen ? 'bx-exit-fullscreen' : 'bx-fullscreen'"
          color="default"
          variant="text"
          @click="switchFullscreen"
        />
      </NavbarAction>
    </template>
    <span>{{ label }}</span>
  </VTooltip>
</template>
