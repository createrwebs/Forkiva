<script lang="ts" setup>
import { nextTick, onMounted, ref, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    modelValue: string
    label: string
    errorMessages?: string
  }>(),
  {
    errorMessages: '',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
  focus: []
}>()

const editorRoot = ref<HTMLElement | null>(null)

const toolbar = [
  { command: 'bold', icon: 'tabler-bold', label: 'Bold' },
  { command: 'italic', icon: 'tabler-italic', label: 'Italic' },
  { command: 'underline', icon: 'tabler-underline', label: 'Underline' },
  { command: 'insertUnorderedList', icon: 'tabler-list', label: 'Bulleted list' },
  { command: 'insertOrderedList', icon: 'tabler-list-numbers', label: 'Numbered list' },
  { command: 'removeFormat', icon: 'tabler-clear-formatting', label: 'Clear formatting' },
]

watch(
  () => props.modelValue,
  (value) => {
    void nextTick(() => syncEditor(value))
  },
  { immediate: true },
)

onMounted(() => {
  runCommand('defaultParagraphSeparator', 'p')
  syncEditor(props.modelValue)
})

function runCommand(command: string, value?: string): boolean {
  // The browser platform has no Selection/Range replacement for rich-text editing
  // commands. Keep this compatibility boundary isolated from clipboard operations.
  const editingDocument = document as unknown as {
    execCommand?: (commandId: string, showUI?: boolean, value?: string) => boolean
  }

  if (typeof editingDocument.execCommand !== 'function') {
    return false
  }

  return editingDocument.execCommand(command, false, value)
}

function syncEditor(value: string) {
  const root = editorRoot.value
  if (!root) {
    return
  }

  const nextValue = sanitizeHtml(value || '')
  if (root.innerHTML !== nextValue) {
    root.innerHTML = nextValue
  }
}

function execute(command: string) {
  editorRoot.value?.focus()
  runCommand(command)
  emitContent()
}

function emitContent() {
  const value = sanitizeHtml(editorRoot.value?.innerHTML || '')
  emit('update:modelValue', value)
}

function insertToken(token: string) {
  const root = editorRoot.value
  if (!root) {
    return
  }

  root.focus()
  const selection = window.getSelection()
  const activeRange = selection?.rangeCount ? selection.getRangeAt(0) : null
  const range =
    activeRange && root.contains(activeRange.commonAncestorContainer)
      ? activeRange
      : document.createRange()

  if (!activeRange || !root.contains(activeRange.commonAncestorContainer)) {
    range.selectNodeContents(root)
    range.collapse(false)
  }

  const text = document.createTextNode(token)
  range.deleteContents()
  range.insertNode(text)
  range.setStartAfter(text)
  range.collapse(true)
  selection?.removeAllRanges()
  selection?.addRange(range)
  emitContent()
  emit('focus')
}

function focusEditor() {
  editorRoot.value?.focus()
  emit('focus')
}

function handlePaste(event: ClipboardEvent) {
  const html = event.clipboardData?.getData('text/html')
  const text = event.clipboardData?.getData('text/plain') || ''

  if (!html && !text) {
    return
  }

  event.preventDefault()
  if (html) {
    runCommand('insertHTML', sanitizeHtml(html))
  } else {
    runCommand('insertText', text)
  }
  emitContent()
}

function sanitizeHtml(html: string): string {
  if (!html || typeof window === 'undefined') {
    return html
  }

  const parser = new DOMParser()
  const parsed = parser.parseFromString(html, 'text/html')
  const allowedTags = new Set(['P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'UL', 'OL', 'LI'])
  const droppedTags = new Set(['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'SVG', 'MATH'])

  for (const element of Array.from(parsed.body.querySelectorAll('*'))) {
    if (droppedTags.has(element.tagName)) {
      element.remove()
      continue
    }

    if (!allowedTags.has(element.tagName)) {
      element.replaceWith(...Array.from(element.childNodes))
      continue
    }

    for (const attribute of Array.from(element.attributes)) {
      element.removeAttribute(attribute.name)
    }
  }

  return normalizeEditorHtml(parsed.body.innerHTML)
}

function normalizeEditorHtml(html: string): string {
  const normalized = html
    .replace(/<p><br><\/p>/gi, '')
    .replace(/<p>\s*<\/p>/gi, '')
    .trim()

  return normalized === '<br>' ? '' : normalized
}

defineExpose({
  insertToken,
  focusEditor,
})
</script>

<template>
  <div
    class="simple-editor"
    :class="{ 'simple-editor--error': !!errorMessages }"
  >
    <div class="simple-editor__label">
      {{ label }}
    </div>

    <div
      class="simple-editor__toolbar"
      role="toolbar"
    >
      <VBtn
        v-for="item in toolbar"
        :key="item.command"
        :aria-label="item.label"
        :icon="item.icon"
        :title="item.label"
        density="comfortable"
        size="small"
        variant="text"
        @mousedown.prevent
        @click="execute(item.command)"
      />
    </div>

    <div
      ref="editorRoot"
      :aria-label="label"
      aria-multiline="true"
      class="simple-editor__content"
      contenteditable="true"
      role="textbox"
      @focus="emit('focus')"
      @input="emitContent"
      @paste="handlePaste"
    />

    <div
      v-if="errorMessages"
      class="simple-editor__error"
    >
      {{ errorMessages }}
    </div>
  </div>
</template>

<style lang="scss" scoped>
.simple-editor {
  display: grid;
  gap: 8px;
}

.simple-editor__label {
  color: rgba(var(--v-theme-on-surface), 0.76);
  font-size: 0.86rem;
  font-weight: 500;
}

.simple-editor__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 2px;
  padding: 4px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px 8px 0 0;
  background: rgb(var(--v-theme-surface));
}

.simple-editor__content {
  min-height: 260px;
  margin-top: -8px;
  padding: 12px 15px;
  overflow: auto;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0 0 8px 8px;
  outline: none;
  background: rgb(var(--v-theme-surface));
  font-family: inherit;
  font-size: 1rem;
  line-height: 1.6;

  &:focus {
    border-color: rgb(var(--v-theme-primary));
  }

  :deep(p) {
    margin-block: 0 0.75rem;
  }
}

.simple-editor__error {
  color: rgb(var(--v-theme-error));
  font-size: 0.75rem;
}

.simple-editor--error {
  .simple-editor__toolbar,
  .simple-editor__content {
    border-color: rgb(var(--v-theme-error));
  }
}
</style>
