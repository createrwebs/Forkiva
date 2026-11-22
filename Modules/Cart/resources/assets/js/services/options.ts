/** Validate every loaded option rule before changing a local draft; incomplete catalogs cannot be used. */
export function validateProductOptions(
  product: Record<string, any>,
  values: Record<string, any>,
): Record<string, string[]> {
  const errors: Record<string, string[]> = {}
  if (!Array.isArray(product.options)) return { options: ['cart::local.options_unavailable'] }
  for (const key of Object.keys(values))
    if (!product.options.some((option: any) => String(option.id) === key))
      errors[`options.${key}`] = ['cart::local.invalid_option']
  for (const option of product.options) {
    const type = option.type?.id ?? option.type
    if (
      ![
        'text',
        'textarea',
        'date',
        'time',
        'select',
        'multiple_select',
        'checkbox',
        'radio',
      ].includes(type) ||
      typeof option.is_required !== 'boolean' ||
      !Array.isArray(option.values)
    ) {
      errors[`options.${option.id}`] = ['cart::local.options_unavailable']
      continue
    }
    const value = values[option.id]
    const missing =
      value === null ||
      value === undefined ||
      (typeof value === 'string' && value.trim() === '') ||
      (Array.isArray(value) && !value.length)
    const key = `options.${option.id}`
    if (missing) {
      if (option.is_required) errors[key] = ['cart::validation.this_field_is_required']
      continue
    }
    if (['text', 'textarea', 'date', 'time'].includes(type)) {
      if (typeof value !== 'string' || [...value].length > 255)
        errors[key] = ['cart::local.invalid_text']
      continue
    }
    if (!Array.isArray(option.values)) {
      errors[key] = ['cart::local.options_unavailable']
      continue
    }
    const multiple = ['checkbox', 'multiple_select'].includes(type)
    const selected = multiple && Array.isArray(value) ? value : [value]
    if (
      multiple !== Array.isArray(value) ||
      selected.some((id: any) => !option.values.some((v: any) => String(v.id) === String(id))) ||
      new Set(selected.map(String)).size !== selected.length
    )
      errors[key] = ['cart::local.invalid_option']
  }
  return errors
}
/** Canonicalize selected values so equivalent option combinations have a stable line identity. */
export function normalizeOptions(options: Record<string, any>): Record<string, any> {
  return Object.fromEntries(
    Object.entries(options)
      .filter(
        ([, v]) =>
          v !== null &&
          v !== undefined &&
          (typeof v !== 'string' || v.trim() !== '') &&
          (!Array.isArray(v) || v.length),
      )
      .sort(([a], [b]) => a.localeCompare(b))
      .map(([key, value]) => [
        key,
        Array.isArray(value) ? [...value].sort((a, b) => Number(a) - Number(b)) : value,
      ]),
  )
}
