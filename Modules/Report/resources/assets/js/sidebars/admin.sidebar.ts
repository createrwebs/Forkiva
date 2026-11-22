import type { SidebarItem, SidebarList } from '@/modules/core/contracts/SidebarItem.ts'
import { reports } from '@/modules/report/pages/admin/report/reports.ts'

const items: SidebarItem[] = Object.entries(reports).flatMap(
  ([groupKey, definitions], groupIndex) => {
    const headingKey = `admin.reports.heading.${groupKey}`

    return [
      {
        key: headingKey,
        label: `report::reports.groups.${groupKey}`,
        isHeading: true,
        permission: definitions.map(({ key }) => `admin.reports.${key}`),
        sort: groupIndex * 100,
      },
      ...definitions.map(({ key, icon }, reportIndex) => ({
        key: `admin.reports.${key}`,
        label: `report::reports.definitions.${key}.title`,
        to: { name: 'admin.reports.show', params: { key } },
        icon,
        parentKey: headingKey,
        permission: `admin.reports.${key}`,
        sort: groupIndex * 100 + reportIndex + 1,
      })),
    ]
  },
)

export const adminSidebar: SidebarList = {
  target: 'admin',
  workspace: 'reports',
  items,
}
