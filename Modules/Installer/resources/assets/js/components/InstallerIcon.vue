<script setup lang="ts">
defineProps<{ name: 'check' | 'arrow' | 'back' | 'alert' | 'lock' | 'server' | 'user' | 'serve' }>()
const paths = {
  serve:
    'M3 18a9 9 0 0 1 18 0M2 18h20v3H2zM12 6V4m-1-1a1 1 0 1 1 2 0 1 1 0 0 1-2 0ZM7 15a5 5 0 0 1 4-5',
  server:
    'M3 3h18v5H3zM3 10h18v5H3zM3 17h18v5H3zM6 5.5h3m8 0h.01M6 12.5h3m8 0h.01M6 19.5h3m8 0h.01',
  user: 'M16.5 6.5a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM3 22v-2a9 7 0 0 1 18 0v2H3Z',
  check: 'm5 12 4 4L19 6',
  arrow: 'M4 12h16m-6-6 6 6-6 6',
  back: 'M20 12H4m6-6-6 6 6 6',
  alert: 'M12 9v4m0 3h.01M10.3 4.5 2 19h20L13.7 4.5a2 2 0 0 0-3.4 0Z',
  lock: 'M7 10V7a5 5 0 0 1 10 0v3M5 10h14v11H5zM12 14v3',
}
</script>

<template>
  <svg
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
  >
    <path :d="paths[name]" />
  </svg>
</template>
