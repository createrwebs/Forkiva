import type { ModuleManifest } from '@/modules/core/contracts/ModuleManifest.ts'
import { adminSidebar } from '@/modules/admin/sidebars/admin.sidebar.ts'

const manifest: ModuleManifest = {
  name: 'admin',
  sidebars: [adminSidebar],
}

export default manifest
