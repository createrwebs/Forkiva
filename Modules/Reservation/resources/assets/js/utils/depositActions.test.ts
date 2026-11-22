import { describe, expect, it } from 'vitest'
import {
  canApplyReservationDeposit,
  canCollectReservationDeposit,
  canForfeitReservationDeposit,
  canRefundReservationDeposit,
  reservationDepositBalance,
  reservationIsTerminal,
} from './depositActions'

function reservation(overrides: Record<string, any> = {}): Record<string, any> {
  return {
    deposit_required: true,
    deposit_paid_amount: {
      original: {
        amount: 25,
      },
    },
    deposit_policy: {
      refundable: true,
    },
    status: {
      id: 'confirmed',
    },
    ...overrides,
  }
}

describe('reservation deposit actions', () => {
  it('reads balances from the Money resource contract', () => {
    expect(reservationDepositBalance(reservation())).toBe(25)
    expect(reservationDepositBalance(reservation({ deposit_paid_amount: { amount: 12.5 } }))).toBe(
      12.5,
    )
    expect(reservationDepositBalance(reservation({ deposit_paid_amount: 7 }))).toBe(7)
  })

  it('allows collection only when a deposit is required and the reservation is active', () => {
    expect(canCollectReservationDeposit(reservation())).toBe(true)
    expect(canCollectReservationDeposit(reservation({ deposit_required: false }))).toBe(false)
    expect(canCollectReservationDeposit(reservation({ status: { id: 'cancelled' } }))).toBe(false)
  })

  it.each(['completed', 'cancelled', 'rejected', 'no_show', 'expired'])(
    'recognizes %s as a terminal reservation status',
    (status) => {
      expect(reservationIsTerminal(reservation({ status: { id: status } }))).toBe(true)
    },
  )

  it('shows application only for a positive held balance', () => {
    expect(canApplyReservationDeposit(reservation())).toBe(true)
    expect(canApplyReservationDeposit(reservation({ deposit_paid_amount: 0 }))).toBe(false)
  })

  it('honors the refundable policy', () => {
    expect(canRefundReservationDeposit(reservation())).toBe(true)
    expect(
      canRefundReservationDeposit(
        reservation({
          deposit_policy: { refundable: false },
        }),
      ),
    ).toBe(false)
  })

  it.each(['cancelled', 'no_show'])(
    'allows forfeiture for %s reservations with a balance',
    (status) => {
      expect(canForfeitReservationDeposit(reservation({ status: { id: status } }))).toBe(true)
    },
  )

  it('hides forfeiture for active reservations or an empty balance', () => {
    expect(canForfeitReservationDeposit(reservation())).toBe(false)
    expect(
      canForfeitReservationDeposit(
        reservation({
          deposit_paid_amount: 0,
          status: { id: 'cancelled' },
        }),
      ),
    ).toBe(false)
  })
})
