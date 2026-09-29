// Derives the VitePress brand scale from one validated hex color.
const HEX = /^#[0-9a-f]{6}$/i

function channels(hex: string) {
  return [1, 3, 5].map(index => Number.parseInt(hex.slice(index, index + 2), 16))
}

function mix(hex: string, target: number, ratio: number) {
  return `#${channels(hex).map(value => Math.round(value + (target - value) * ratio).toString(16).padStart(2, '0')).join('')}`
}

export function safeColor(value: unknown, fallback: string) {
  return typeof value === 'string' && HEX.test(value) ? value.toLowerCase() : fallback
}

export function brandPalette(color: string) {
  return {
    light: { 1: mix(color, 0, 0.12), 2: color, 3: mix(color, 255, 0.18), soft: `${color}24` },
    dark: { 1: mix(color, 255, 0.35), 2: mix(color, 255, 0.18), 3: color, soft: `${color}29` },
  }
}

export function themeStyle(theme: { brand_color?: string; hero_from?: string; hero_to?: string }) {
  const brand = safeColor(theme.brand_color, '#696cff')
  const from = safeColor(theme.hero_from, '#50bdff')
  const to = safeColor(theme.hero_to, '#b932fa')
  const { light, dark } = brandPalette(brand)

  return `:root{--vp-c-brand-1:${light[1]};--vp-c-brand-2:${light[2]};--vp-c-brand-3:${light[3]};--vp-c-brand-soft:${light.soft};--cms-hero-from:${from};--cms-hero-to:${to};--vp-home-hero-name-color:transparent;--vp-home-hero-name-background:linear-gradient(110deg,${from} 15%,${to} 85%);--vp-home-hero-image-background-image:linear-gradient(135deg,${from}70,${to}70);--vp-home-hero-image-filter:blur(64px)}`
    + `.dark{--vp-c-brand-1:${dark[1]};--vp-c-brand-2:${dark[2]};--vp-c-brand-3:${dark[3]};--vp-c-brand-soft:${dark.soft}}`
}
