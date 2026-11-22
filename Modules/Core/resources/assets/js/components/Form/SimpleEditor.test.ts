import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { defineComponent, nextTick } from 'vue'
import SimpleEditor from './SimpleEditor.vue'

const ButtonStub = defineComponent({
  inheritAttrs: false,
  template: '<button v-bind="$attrs" />',
})

describe('SimpleEditor', () => {
  it('sanitizes externally supplied HTML before rendering it', async () => {
    const wrapper = mount(SimpleEditor, {
      props: {
        label: 'Message',
        modelValue:
          '<p class="unsafe"><strong>Welcome</strong></p><img src=x onerror="alert(1)"><script>alert(2)</script>',
      },
      global: {
        stubs: {
          VBtn: ButtonStub,
        },
      },
    })

    await nextTick()

    const content = wrapper.get('.simple-editor__content').element as HTMLElement
    expect(content.innerHTML).toBe('<p><strong>Welcome</strong></p>')
    expect(content.querySelector('img')).toBeNull()
    expect(content.querySelector('script')).toBeNull()
  })
})
