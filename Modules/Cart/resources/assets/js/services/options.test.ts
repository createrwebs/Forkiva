import { describe, expect, it } from 'vitest'
import { validateProductOptions } from './options'
const validate = (type: string, value: any, required = true) =>
  validateProductOptions(
    { options: [{ id: 1, type, is_required: required, values: [{ id: 2 }, { id: 3 }] }] },
    { 1: value },
  )
describe('shared option rules', () => {
  it.each(['select', 'radio'])('%s accepts one belonging value only', (type) => {
    expect(validate(type, 2)).toEqual({})
    for (const value of [null, [], [2], 4, {}])
      expect(Object.keys(validate(type, value))).not.toHaveLength(0)
  })
  it.each(['checkbox', 'multiple_select'])('%s rejects foreign and duplicate values', (type) => {
    expect(validate(type, [2, 3])).toEqual({})
    for (const value of [[], 2, [2, 2], [4]])
      expect(Object.keys(validate(type, value))).not.toHaveLength(0)
  })
  it.each(['text', 'textarea', 'date', 'time'])('%s enforces string and Unicode length', (type) => {
    expect(validate(type, 'ا'.repeat(255))).toEqual({})
    for (const value of [2, 'ا'.repeat(256), '   '])
      expect(Object.keys(validate(type, value))).not.toHaveLength(0)
  })
  it('allows omitted optional options and rejects unknown option ids', () => {
    expect(validate('select', null, false)).toEqual({})
    expect(validateProductOptions({ options: [] }, { 9: 2 })).toHaveProperty('options.9')
  })
})
