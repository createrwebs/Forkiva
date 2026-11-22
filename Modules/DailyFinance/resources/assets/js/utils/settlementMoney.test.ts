import { describe, expect, it } from 'vitest'
import { settlementDecimal, settlementFormatted, settlementUnits } from './settlementMoney'

describe('settlement amount precision', () => {
  it('retains exact subunits for two, three and zero-decimal currencies', () => {
    expect(settlementUnits('10.345', 3)).toBe(10345n)
    expect(settlementUnits('10.345', 2)).toBeNull()
    expect(settlementUnits('10.3400', 2)).toBe(1034n)
    expect(settlementUnits('10.00', 0)).toBe(10n)
    expect(settlementUnits('-1', 2)).toBeNull()
    expect(settlementUnits('1e2', 2)).toBeNull()
    expect(settlementDecimal(10345n - 45n, 3)).toBe('10.300')
  })

  it('formats large values and negative fractional differences without floating-point loss', () => {
    const units = settlementUnits('99999999999999.999', 3)!
    expect(settlementDecimal(units, 3)).toBe('99999999999999.999')
    expect(settlementFormatted(units, 'JOD', 3, 'en-US')).toContain('99,999,999,999,999.999')
    expect(settlementFormatted(-5n, 'USD', 2, 'en-US')).toBe('-$0.05')
    expect(settlementDecimal(-5n, 3)).toBe('-0.005')
  })
})
