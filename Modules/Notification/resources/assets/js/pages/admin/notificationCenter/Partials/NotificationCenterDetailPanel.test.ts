import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import NotificationCenterDetailPanel from './NotificationCenterDetailPanel.vue'

vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string) => key,
  }),
}))

describe('NotificationCenterDetailPanel', () => {
  it('renders notification content as text instead of executable HTML', () => {
    const wrapper = mount(NotificationCenterDetailPanel, {
      props: {
        mdAndDown: false,
        selectedItem: {
          id: 'notification-1',
          type: 'security_test',
          type_id: null,
          title: 'Security notification',
          subject: null,
          content: '<img src=x onerror="alert(1)"><script>alert(2)</script>',
          read_at: null,
          created_at: 'Now',
          updated_at: null,
        },
      },
      global: {
        stubs: {
          VIcon: true,
          VProgressCircular: true,
        },
      },
    })

    const content = wrapper.get('.notification-center-detail-panel__content')

    expect(content.find('img').exists()).toBe(false)
    expect(content.find('script').exists()).toBe(false)
    expect(content.text()).toContain('<img src=x onerror="alert(1)">')
  })
})
