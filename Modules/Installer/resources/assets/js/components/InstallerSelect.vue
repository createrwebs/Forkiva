<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import type { CSSProperties } from 'vue'
import type { ConfigurationOption } from '../contracts/installer'
const props = defineProps<{
  label: string
  name: string
  options: ConfigurationOption[]
  multiple?: boolean
  disabled?: boolean
  errors?: string[]
}>()
const model = defineModel<string | string[]>({ required: true })
const trigger = ref<HTMLButtonElement>()
const menu = ref<HTMLDivElement>()
const search = ref<HTMLInputElement>()
const open = ref(false)
const query = ref('')
const menuStyle = ref<CSSProperties>({})
const selected = computed(() =>
  Array.isArray(model.value) ? model.value : model.value ? [model.value] : [],
)
const selectedNames = computed(() =>
  selected.value.map((id) => props.options.find((option) => option.id === id)?.name ?? id),
)
const filtered = computed(() => {
  const term = query.value.trim().toLowerCase()
  return props.options.filter((option) =>
    `${option.name} ${option.id}`.toLowerCase().includes(term),
  )
})
function close(restoreFocus = false) {
  if (!open.value) return
  open.value = false
  query.value = ''
  if (restoreFocus) trigger.value?.focus({ preventScroll: true })
}
function positionMenu() {
  if (!open.value || !trigger.value) return
  const rect = trigger.value.getBoundingClientRect()
  const viewport = window.visualViewport
  const viewportTop = viewport?.offsetTop ?? 0
  const viewportHeight = viewport?.height ?? window.innerHeight
  const viewportWidth = document.documentElement.clientWidth || window.innerWidth
  const gap = 8
  const below = viewportTop + viewportHeight - rect.bottom - gap
  const above = rect.top - viewportTop - gap
  const showBelow = below >= Math.min(320, above)
  const maxHeight = Math.max(100, Math.min(320, showBelow ? below : above))
  const height = Math.min(menu.value?.getBoundingClientRect().height || maxHeight, maxHeight)
  const width = Math.min(rect.width, viewportWidth - gap * 2)
  menuStyle.value = {
    width: `${width}px`,
    maxHeight: `${maxHeight}px`,
    left: `${Math.max(gap, Math.min(rect.left, viewportWidth - width - gap))}px`,
    top: `${showBelow ? rect.bottom + gap : Math.max(viewportTop + gap, rect.top - height - gap)}px`,
  }
}
async function show(focusSearch = false) {
  if (props.disabled || open.value) return
  open.value = true
  positionMenu()
  await nextTick()
  // A close/unmount can happen before this continuation after a rapid click or Tab.
  if (!open.value || props.disabled || !trigger.value?.isConnected) return
  positionMenu()
  if (focusSearch) search.value?.focus({ preventScroll: true })
}
function toggleMenu(event: MouseEvent) {
  if (open.value) close()
  else void show(event.detail === 0)
}
function select(id: string) {
  if (props.disabled) return
  if (props.multiple) {
    model.value = selected.value.includes(id)
      ? selected.value.filter((value) => value !== id)
      : [...selected.value, id]
  } else {
    model.value = id
    close(true)
  }
}
function outside(event: Event) {
  const target = event.target
  if (target instanceof Node && !trigger.value?.contains(target) && !menu.value?.contains(target))
    close()
}
function scrolled(event: Event) {
  if (event.target instanceof Node && menu.value?.contains(event.target)) return
  // Keep the popup anchored without scrolling or moving focus ourselves.
  // A queued scroll event must not close a menu that has just opened.
  positionMenu()
}
function focusFirst() {
  menu.value
    ?.querySelector<HTMLInputElement>('.installer-select-option input')
    ?.focus({ preventScroll: true })
}
watch(
  () => props.disabled,
  (disabled) => {
    if (disabled) close()
  },
)
watch(query, async () => {
  await nextTick()
  positionMenu()
})
onMounted(() => {
  document.addEventListener('pointerdown', outside)
  document.addEventListener('focusin', outside)
  document.addEventListener('scroll', scrolled, true)
  window.addEventListener('resize', positionMenu)
  window.visualViewport?.addEventListener('resize', positionMenu)
})
onUnmounted(() => {
  document.removeEventListener('pointerdown', outside)
  document.removeEventListener('focusin', outside)
  document.removeEventListener('scroll', scrolled, true)
  window.removeEventListener('resize', positionMenu)
  window.visualViewport?.removeEventListener('resize', positionMenu)
})
</script>

<template>
  <div class="field">
    <label
      :id="`${name}-label`"
      :for="name"
    >
      {{ label }}
    </label>
    <button
      :id="name"
      ref="trigger"
      type="button"
      :name="name"
      class="installer-select-trigger"
      :class="{ 'is-open': open }"
      :aria-labelledby="`${name}-label ${name}-value`"
      :aria-describedby="`${name}-help`"
      :aria-invalid="!!errors?.length"
      :aria-expanded="open"
      :aria-controls="open ? `${name}-menu` : undefined"
      aria-haspopup="dialog"
      :disabled="disabled"
      @click="toggleMenu"
      @keydown.down.prevent="show(true)"
      @keydown.esc.prevent.stop="close(true)"
    >
      <span
        :id="`${name}-value`"
        class="installer-selection"
      >
        <span
          v-if="!selected.length"
          class="select-placeholder"
        >
          Choose {{ label.toLowerCase() }}
        </span>
        <template v-else-if="multiple">
          <span
            v-for="(selectedName, index) in selectedNames.slice(0, 2)"
            :key="selected[index]"
            class="selection-chip"
          >
            {{ selectedName }}
          </span>
          <span
            v-if="selected.length > 2"
            class="selection-count"
          >
            +{{ selected.length - 2 }}
          </span>
        </template>
        <span v-else>{{ selectedNames[0] }}</span>
      </span>
      <svg
        class="select-chevron"
        width="16"
        height="16"
        viewBox="0 0 24 24"
        fill="none"
        aria-hidden="true"
      >
        <path
          d="m6 9 6 6 6-6"
          stroke="currentColor"
          stroke-width="1.8"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>
    </button>
    <Teleport to="body">
      <div
        v-if="open"
        :id="`${name}-menu`"
        ref="menu"
        class="field installer-select-menu installer-select-floating"
        :style="menuStyle"
        role="dialog"
        :aria-labelledby="`${name}-label`"
        @keydown.esc.prevent.stop="close(true)"
      >
        <div class="installer-select-search">
          <input
            ref="search"
            v-model="query"
            type="search"
            :aria-label="`Search ${label.toLowerCase()}`"
            placeholder="Search by name or code…"
            :disabled="disabled"
            @keydown.enter.prevent
            @keydown.down.prevent="focusFirst"
          />
        </div>
        <div
          class="installer-select-options"
          role="group"
          :aria-labelledby="`${name}-label`"
        >
          <label
            v-for="option in filtered"
            :key="option.id"
            class="installer-select-option"
            :class="{ selected: selected.includes(option.id) }"
          >
            <input
              :type="multiple ? 'checkbox' : 'radio'"
              :name="`${name}-choice`"
              :value="option.id"
              :checked="selected.includes(option.id)"
              :disabled="disabled"
              @change="select(option.id)"
            />
            <span>{{ option.name }}</span>
            <small>{{ option.id }}</small>
          </label>
          <p
            v-if="!filtered.length"
            class="select-empty"
          >
            No matches found.
          </p>
        </div>
        <div class="installer-select-footer">
          <span>{{ selected.length }} selected</span>
          <button
            type="button"
            :disabled="disabled"
            @click="close(true)"
          >
            Done
          </button>
        </div>
      </div>
    </Teleport>
    <div :id="`${name}-help`">
      <p
        v-for="message in errors"
        :key="message"
        class="field-error"
      >
        {{ message }}
      </p>
    </div>
  </div>
</template>
