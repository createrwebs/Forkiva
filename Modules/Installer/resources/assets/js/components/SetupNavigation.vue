<script setup lang="ts">
import InstallerIcon from './InstallerIcon.vue'
import type { Step } from '../contracts/installer'

defineProps<{
  steps: { id: Step; label: string; description: string }[]
  active: Step
  activeIndex: number
  finished: boolean
  canVisit: (step: Step) => boolean
}>()
defineEmits<{ navigate: [step: Step] }>()
</script>

<template>
  <nav
    class="setup-navigation"
    aria-label="Installation steps"
  >
    <ol class="steps">
      <li
        v-for="(item, index) in steps"
        :key="item.id"
        :class="{ active: active === item.id, complete: index < activeIndex || finished }"
      >
        <button
          :disabled="!canVisit(item.id)"
          :aria-current="active === item.id ? 'step' : undefined"
          @click="$emit('navigate', item.id)"
        >
          <span class="step-number">
            <InstallerIcon
              v-if="index < activeIndex || finished"
              name="check"
            />
            <template v-else>{{ index + 1 }}</template>
          </span>
          <span class="step-text">
            <strong>{{ item.label }}</strong>
            <small>{{ item.description }}</small>
          </span>
        </button>
      </li>
    </ol>
    <p class="navigation-note">
      <InstallerIcon name="lock" />
      Private installation session
    </p>
  </nav>
</template>
