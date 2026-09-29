import { moduleMessages as registeredMessages } from 'virtual:larastarterkit'
import { deepMerge } from '@antfu/utils'
import type { App } from 'vue'
import { createI18n } from 'vue-i18n'

import { en, fr } from 'vuetify/locale'
import { cookieRef } from '@layouts/stores/config'
import { themeConfig } from '@themeConfig'

const vuetifyMessages = { en, fr }

const appMessages = Object.fromEntries(
  Object.entries(
    import.meta.glob<{ default: any }>('./locales/*.json', { eager: true }))
    .map(([key, value]) => [key.slice(10, -5), value.default]),
)

const moduleMessages: Record<string, any> = {}

for (const [moduleName, locale, msgs] of registeredMessages) {
  moduleMessages[locale] = moduleMessages[locale] ?? {}
  moduleMessages[locale][moduleName] = deepMerge(moduleMessages[locale][moduleName] ?? {}, msgs)
}

const messages = deepMerge(vuetifyMessages, appMessages, moduleMessages)

let _i18n: any = null

export const getI18n = () => {
  if (_i18n === null) {
    _i18n = createI18n({
      globalInjection: true,
      legacy: false,
      locale: cookieRef('language', themeConfig.app.i18n.defaultLocale).value,
      fallbackLocale: 'en',
      messages,
    })
  }

  return _i18n
}

export default function (app: App) {
  const i18n = getI18n()

  app.use(i18n)
}
