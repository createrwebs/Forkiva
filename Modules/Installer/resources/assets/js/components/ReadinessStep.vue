<script setup lang="ts">
import InstallerIcon from './InstallerIcon.vue'
import type { InstallationState } from '../contracts/installer'
defineProps<{ state: InstallationState; busy: boolean }>()
</script>

<template>
  <section aria-label="Server readiness">
    <div class="check-panel">
      <div class="panel-heading">
        <h2>Server readiness</h2>
        <span
          class="status"
          :class="state.checks_passed ? 'passed' : 'attention'"
        >
          <span
            v-if="busy"
            class="spinner"
          />
          <InstallerIcon
            v-else
            :name="state.checks_passed ? 'check' : 'alert'"
          />
          {{
            busy ? 'Checking server' : state.checks_passed ? 'Ready to continue' : 'Action needed'
          }}
        </span>
      </div>
      <div
        v-for="check in state.checks"
        :key="check.id"
        class="check-row"
      >
        <div>
          <h3>{{ check.label }}</h3>
          <p>{{ check.detail }}</p>
          <p
            v-if="check.remedy"
            class="remedy"
          >
            <strong>How to fix:</strong>
            {{ check.remedy }}
          </p>
        </div>
        <span
          class="status"
          :class="check.passed ? 'passed' : 'attention'"
        >
          <InstallerIcon :name="check.passed ? 'check' : 'alert'" />
          {{ check.passed ? 'Passed' : 'Fix required' }}
        </span>
      </div>
      <p
        v-if="!state.checks"
        class="panel-empty"
      >
        Checks will appear here as soon as the server responds.
      </p>
    </div>
    <div class="notice warning">
      <InstallerIcon name="alert" />
      <p>Checks reflect this server. Recheck after changing its configuration.</p>
    </div>
    <p class="section-note">
      PCNTL is not required for browser installation. Enable it in command-line PHP when using queue
      workers that need job timeouts and signal handling.
    </p>
    <p class="section-note">
      Node.js and Puppeteer are not required by the default PDF renderer. Configure and verify
      optional PDF drivers after installation.
    </p>
  </section>
</template>
