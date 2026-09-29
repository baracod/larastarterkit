import type { LiteralUnion } from 'type-fest'
import { cookieRef } from '@layouts/stores/config'

export const resolveVuetifyTheme = (defaultTheme: LiteralUnion<'light' | 'dark' | 'system', string>): 'light' | 'dark' => {
  const prefersDark = usePreferredDark().value
  const storedTheme = cookieRef('theme', defaultTheme).value

  return storedTheme === 'system'
    ? prefersDark
      ? 'dark'
      : 'light'
    : storedTheme as 'light' | 'dark'
}
