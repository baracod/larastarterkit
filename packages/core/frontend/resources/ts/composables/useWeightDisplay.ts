export type WeightInput = number | string | null | undefined
const unitToGrams: Record<string, number> = { mg: 0.001, g: 1, kg: 1000, oz: 31.1034768, tola: 11.6638038 }
export function useWeightDisplay() {
  const normalizeToGrams = (weight: WeightInput, unit = 'g'): number => {
    const value = Number(weight ?? 0)
    if (!Number.isFinite(value) || !(unit in unitToGrams))
      throw new Error('Invalid weight or unit')

    return value * unitToGrams[unit]
  }

  const formatWeight = (weight: WeightInput, unit = 'g', locale = 'fr'): string => `${Number(weight ?? 0).toLocaleString(locale, { maximumFractionDigits: 3 })} ${unit}`

  return { normalizeToGrams, formatWeight }
}
