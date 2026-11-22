import { nextTick } from 'vue'
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { useConfirmDialog } from '@/modules/core/composables/confirmDialog'

vi.mock('@/app/plugins/i18n.ts', () => ({ default: { install() {} } }))
vi.mock('@/app/plugins/vuetify', () => ({ default: { install() {} } }))
vi.mock('@/modules/core/components/Dialogs/ConfirmDialog.vue', async () => {
  const { defineComponent, h } = await import('vue')
  return {
    default: defineComponent({
      emits: ['close', 'update:modelValue'],
      setup(_props, { emit }) {
        // Use a render function so the stub does not depend on runtime template compilation.
        return () =>
          h(
            'section',
            ['cancel', 'accept', 'dismiss'].map((action) =>
              h(
                'button',
                {
                  id: `${action}-confirm`,
                  onClick: () => {
                    if (action !== 'dismiss') emit('close', action === 'accept')
                    emit('update:modelValue', false)
                  },
                },
                action,
              ),
            ),
          )
      },
    }),
  }
})

beforeEach(() => {
  document.body.innerHTML = ''
})
afterEach(() => {
  document.body.innerHTML = ''
})

it.each([
  ['cancel', false],
  ['accept', true],
  ['dismiss', false],
] as const)(
  'settles %s and removes the dialog so the page remains usable',
  async (action, result) => {
    const confirmation = useConfirmDialog()
    await nextTick()
    document.querySelector<HTMLButtonElement>(`#${action}-confirm`)!.click()
    await expect(confirmation).resolves.toBe(result)
    expect(document.body.children).toHaveLength(0)
    const nextConfirmation = useConfirmDialog()
    document.querySelector<HTMLButtonElement>('#cancel-confirm')!.click()
    await expect(nextConfirmation).resolves.toBe(false)
    expect(document.body.children).toHaveLength(0)
  },
)
