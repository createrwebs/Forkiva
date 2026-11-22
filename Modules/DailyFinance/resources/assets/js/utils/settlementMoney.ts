export function settlementUnits(value: string, precision: number): bigint | null {
  if (!/^\d{1,14}(?:\.\d{1,4})?$/.test(value)) return null
  const [whole = '0', fraction = ''] = value.split('.')
  if (fraction.slice(precision).replace(/0/g, '')) return null

  return BigInt(whole + fraction.slice(0, precision).padEnd(precision, '0'))
}

export function settlementDecimal(value: bigint, precision: number): string {
  const scale = 10n ** BigInt(precision)
  const absolute = value < 0n ? -value : value

  return `${value < 0n ? '-' : ''}${absolute / scale}${precision ? `.${(absolute % scale).toString().padStart(precision, '0')}` : ''}`
}

export function settlementFormatted(
  value: bigint,
  currency: string,
  precision: number,
  locale: string,
): string {
  const scale = 10n ** BigInt(precision)
  const absolute = value < 0n ? -value : value
  const whole = absolute / scale
  const formatter = new Intl.NumberFormat(locale, {
    style: 'currency',
    currency,
    minimumFractionDigits: precision,
    maximumFractionDigits: precision,
  })
  const sample = value < 0n ? -(whole || 1n) : whole

  return formatter
    .formatToParts(sample)
    .map((part) => {
      if (part.type === 'fraction') return (absolute % scale).toString().padStart(precision, '0')
      if (part.type === 'integer' && value < 0n && whole === 0n) return '0'

      return part.value
    })
    .join('')
}
