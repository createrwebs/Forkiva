import type { ModuleManifest } from '@/modules/core/contracts/ModuleManifest.ts'
import adminRoutes from '@/modules/seatingPlan/routes/admin.routes.ts'
import publicRoutes from '@/modules/seatingPlan/routes/public.routes.ts'
import { adminSidebar } from '@/modules/seatingPlan/sidebars/admin.sidebar.ts'

const manifest: ModuleManifest = {
  name: 'seating_plan',
  routes: [
    {
      target: 'admin',
      routes: adminRoutes,
    },
    {
      target: 'public',
      routes: publicRoutes,
    },
  ],
  sidebars: [adminSidebar],
}

export default manifest
