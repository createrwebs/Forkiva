import { http } from '@/modules/core/api/http.ts'

export interface TenantRegistrationPayload {
  shop_name: string
  slug?: string
  business_type?: 'fnb' | 'retail' | 'hybrid'
  owner_name: string
  email: string
  password: string
  phone?: string
  promptpay_id?: string
  tax_branch_code?: string
  vat_tin?: string
  plan_code?: string
}

export interface TenantEntitlementsResponse {
  tenant: {
    id: number
    uuid: string
    slug: string
    name: string
    business_type: 'fnb' | 'retail' | 'hybrid'
    status: string
    onboarding_completed_at: string | null
  }
  plan: {
    id: number
    code: string
    currency: string
  } | null
  features: string[]
  limits: Record<string, number>
}

export async function registerTenant(payload: TenantRegistrationPayload) {
  const response = await http.post('/v1/public/register-tenant', payload)
  return response.data.body
}

export async function fetchCurrentTenant(): Promise<TenantEntitlementsResponse> {
  const response = await http.get('/v1/tenant/current')
  return response.data.body
}

export async function completeTenantOnboarding() {
  const response = await http.post('/v1/tenant/onboarding/complete')
  return response.data.body
}
