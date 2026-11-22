import { createApp, h } from 'vue'
import i18n from '@/app/plugins/i18n.ts'
import vuetify from '@/app/plugins/vuetify'
import ConfirmDialog from '@/modules/core/components/Dialogs/ConfirmDialog.vue'

export function useConfirmDialog(options?: {
  title?: string
  message?: string
  confirmButtonText?: string
  cancelButtonText?: string
  confirmColor?: string
}): Promise<boolean> {
  return new Promise((resolve) => {
    const container = document.createElement('div')
    document.body.append(container)
    let settled = false

    // Both close events may fire; every dismissal must resolve and clean up exactly once.
    const finish = (confirmed: boolean) => {
      if (settled) return
      settled = true
      resolve(confirmed)
      app.unmount()
      container.remove()
    }

    const app = createApp({
      render() {
        return h(ConfirmDialog, {
          modelValue: true,
          ...options,
          'onUpdate:modelValue': (val: boolean) => {
            if (!val) finish(false)
          },
          onClose: finish,
        })
      },
    })

    app.use(vuetify)
    app.use(i18n)
    app.mount(container)
  })
}
