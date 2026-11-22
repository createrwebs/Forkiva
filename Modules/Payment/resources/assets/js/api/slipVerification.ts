import { http } from '@/modules/core/api/http.ts'

export type SlipVerificationDriver = 'local_mini_qr' | 'scb_open_api' | 'kbank_open_api'

export interface ParsedThaiSlip {
  api_id: string
  sending_bank_code: string
  sending_bank_short_name: string
  trans_ref: string
  country_code: string
}

export interface VerifySlipPayload {
  branch_id: number
  order_id?: number
  session_id?: number
  auto_pay?: boolean
  qr_payload: string
  expected_amount: string | number
  driver?: SlipVerificationDriver
}

export interface SlipVerificationRecord {
  id: number
  branch_id: number
  order_id: number | null
  provider: string
  sending_bank_code: string
  trans_ref: string
  amount: string
  sender_name: string | null
  receiver_account: string | null
  status: string
  verified_at: string | null
}

export async function parseThaiSlipQr(qrPayload: string): Promise<ParsedThaiSlip> {
  const response = await http.post('/v1/payments/slip-verify/parse', {
    qr_payload: qrPayload,
  })
  return response.data.body
}

export async function verifyThaiSlip(payload: VerifySlipPayload): Promise<SlipVerificationRecord> {
  const response = await http.post('/v1/payments/slip-verify', payload)
  return response.data.body
}
