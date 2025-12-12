import { deepMerge } from '@antfu/utils'
import type { App } from 'vue'
import { createI18n } from 'vue-i18n'

import { cookieRef } from '@layouts/stores/config'
import { themeConfig } from '@themeConfig'
import { en, fr } from 'vuetify/locale'

// // Modules/Blog/resources/ts/lang/en/post.json
// const appLangage = Object.fromEntries(
//   Object.entries(
//     import.meta.glob<{ default: any }>('./locales/*.json', { eager: true }))
//     .map(([key, value]) => [key.slice(10, -5), value.default]),
// )

// console.log('appLangage', appLangage)

// const moduleLangageFiles = import.meta.glob<{ default: any }>('/Modules/*/resources/ts/lang/*/*.json', { eager: true })
// const moduleLangageObjet: any = {}

// Object.entries(moduleLangageFiles).forEach(([path, module]) => {
//   const match = path.match(/Modules\/[^/]+\/resources\/ts\/lang\/([^/]+)\/([^/]+)\.json$/)
//   if (match) {
//     const lang = match[1] // ex: "en" ou "fr"
//     const domain = match[2] // ex: "post"
//     if (!moduleLangageObjet[lang])
//       moduleLangageObjet[lang] = {}

//     moduleLangageObjet[lang][domain] = module.default
//   }
// })

// // console.log({
// //   en: { ...appLangage.en, ...moduleLangageObjet.en },
// //   fr: { ...appLangage.fr, ...moduleLangageObjet.fr },
// // })

// let _i18n: any = null

// export const getI18n = () => {
//   if (_i18n === null) {
//     _i18n = createI18n({
//       legacy: false,
//       locale: cookieRef('language', themeConfig.app.i18n.defaultLocale).value,
//       fallbackLocale: 'en',
//       messages: {
//         en: { ...appLangage.en, ...moduleLangageObjet.en },
//         fr: { ...appLangage.fr, ...moduleLangageObjet.fr },
//       },
//     })
//   }

//   return _i18n
// }

// export default function (app: App) {
//   app.use(getI18n())
// }
const vuetifyMessages = { en, fr }

const appMessages = Object.fromEntries(
  Object.entries(
    import.meta.glob<{ default: any }>('./locales/*.json', { eager: true }))
    .map(([key, value]) => [key.slice(10, -5), value.default]),
)

// Charge tous les fichiers JSON des modules
const files = import.meta.glob<{ default: any }>(
  '/Modules/**/resources/ts/locales/*.json',
  { eager: true, import: 'default' },
) as Record<string, any>

const moduleMessages: Record<string, any> = {}

for (const [path, msgs] of Object.entries(files)) {
  const parts = path.split('/')
  const moduleName = parts[2] // ex: "Auth"
  const locale = parts.pop()!.replace('.json', '') // ex: "en"

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
