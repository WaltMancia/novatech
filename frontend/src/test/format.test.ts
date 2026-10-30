import { describe, expect, it } from 'vitest'
import { formatMoney } from '../lib/format'

describe('formatMoney', () => {
  it('convierte centavos a quetzales con dos decimales', () => {
    expect(formatMoney(749900)).toMatch(/7,499\.00/)
    expect(formatMoney(5)).toMatch(/0\.05/)
  })

  it('incluye el símbolo de la moneda', () => {
    expect(formatMoney(100)).toMatch(/Q|GTQ/)
  })
})
