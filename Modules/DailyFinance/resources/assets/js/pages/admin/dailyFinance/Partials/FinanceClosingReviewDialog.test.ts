import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import FinanceClosingReviewDialog from './FinanceClosingReviewDialog.vue'
import type { DayClosing } from '@/modules/dailyfinance/contracts/dayClosing'

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))

describe('saved closing review', () => {
  it('shows the saved snapshot and branch, and leaves the parent open until the dialog transition ends', async () => {
    const record = {
      id: 7,
      reference: 'DC-0007',
      branch_id: 1,
      date_formatted: '2026-10-11',
      created_at: '2026-10-11 10:00 PM',
      created_by: { id: 1, name: 'Manager' },
      notes: 'Count difference explained.',
      summary: { branches: [{ id: 1, name: 'Saved branch' }] },
    } as DayClosing
    const wrapper = shallowMount(FinanceClosingReviewDialog, {
      props: { record, branchName: 'Current branch' },
      global: {
        renderStubDefaultSlot: true,
        stubs: { VDialog: true, VCard: true, VCardText: true, VBtn: true, VIcon: true },
      },
    })
    expect(wrapper.text()).toContain('Saved branch')
    expect(wrapper.text()).not.toContain('Current branch')
    expect(wrapper.text()).toContain(record.notes)
    expect(wrapper.findComponent({ name: 'FinanceClosingSummary' }).props('summary')).toEqual(
      record.summary,
    )
    const action = wrapper
      .findAllComponents({ name: 'VBtn' })
      .find((button) => button.text().includes('day_closings.dismiss'))!
    action.vm.$emit('click')
    await wrapper.vm.$nextTick()
    expect(wrapper.findComponent({ name: 'VDialog' }).attributes('modelvalue')).toBe('false')
    expect(wrapper.emitted('close')).toBeUndefined()
    wrapper.findComponent({ name: 'VDialog' }).vm.$emit('after-leave')
    expect(wrapper.emitted('close')).toHaveLength(1)
    wrapper.unmount()
  })
})
