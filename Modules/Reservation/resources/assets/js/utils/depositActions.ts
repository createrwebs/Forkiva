export type ReservationDepositAction = 'collection' | 'application' | 'refund' | 'forfeiture'

const terminalReservationStatuses = new Set([
  'completed',
  'cancelled',
  'rejected',
  'no_show',
  'expired',
])

export function reservationDepositBalance(reservation: Record<string, any>): number {
  return Number(
    reservation.deposit_paid_amount?.original?.amount ??
      reservation.deposit_paid_amount?.amount ??
      reservation.deposit_paid_amount ??
      0,
  )
}

export function reservationIsTerminal(reservation: Record<string, any>): boolean {
  return terminalReservationStatuses.has(reservation.status?.id)
}

export function canCollectReservationDeposit(reservation: Record<string, any>): boolean {
  return Boolean(reservation.deposit_required) && !reservationIsTerminal(reservation)
}

export function canApplyReservationDeposit(reservation: Record<string, any>): boolean {
  return reservationDepositBalance(reservation) > 0
}

export function canRefundReservationDeposit(reservation: Record<string, any>): boolean {
  return (
    reservationDepositBalance(reservation) > 0 && reservation.deposit_policy?.refundable !== false
  )
}

export function canForfeitReservationDeposit(reservation: Record<string, any>): boolean {
  return (
    reservationDepositBalance(reservation) > 0 &&
    ['cancelled', 'no_show'].includes(reservation.status?.id)
  )
}
