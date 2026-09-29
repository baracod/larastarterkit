import { deepMerge } from '@antfu/utils'
import type { App } from 'vue'
import { createVuetify } from 'vuetify'
import { VBtn } from 'vuetify/components/VBtn'
import { VDateInput } from 'vuetify/labs/VDateInput'
import { en, fr } from 'vuetify/locale'
import defaults from './defaults'
import { icons } from './icons'
import { staticDarkPrimaryColor, staticDarkPrimaryDarkenColor, staticPrimaryColor, staticPrimaryDarkenColor, themes } from './theme'

// Translations provided by Vuetify
import { themeConfig } from '@themeConfig'

// Styles
import { cookieRef } from '@/@layouts/stores/config'
import '@core-scss/template/libs/vuetify/index.scss'
import 'vuetify/styles'

export default function (app: App) {
  const cookieThemeValues = {
    defaultTheme: resolveVuetifyTheme(themeConfig.app.theme),
    themes: {
      light: {
        colors: {
          'primary': cookieRef('lightThemePrimaryColor', staticPrimaryColor).value,
          'primary-darken-1': cookieRef('lightThemePrimaryDarkenColor', staticPrimaryDarkenColor).value,
        },
      },
      dark: {
        colors: {
          'primary': cookieRef('darkThemePrimaryColor', staticDarkPrimaryColor).value,
          'primary-darken-1': cookieRef('darkThemePrimaryDarkenColor', staticDarkPrimaryDarkenColor).value,
        },
      },
    },
  }

  const optionTheme = deepMerge({ themes }, cookieThemeValues)

  const vuetify = createVuetify({
    aliases: {
      IconBtn: VBtn,
    },
    defaults,
    icons,
    theme: optionTheme,
    components: {
      VDateInput,
    },

    locale: {
      locale: cookieRef('language', themeConfig.app.i18n.defaultLocale).value,
      fallback: 'en',
      messages: { fr, en },
    },

  })

  app.use(vuetify)
}
