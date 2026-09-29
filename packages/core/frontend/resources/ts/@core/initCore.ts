import { useTheme } from 'vuetify'
import { useConfigStore } from '@core/stores/config'
import { cookieRef, namespaceConfig } from '@layouts/stores/config'
import { themeConfig } from '@themeConfig'

const _syncAppRtl = () => {
  const configStore = useConfigStore()
  const storedLang = cookieRef<string | null>('language', null)

  const { locale } = useI18n({ useScope: 'global' })

  // TODO: Handle case where i18n can't read persisted value
  if (locale.value !== storedLang.value && storedLang.value)
    locale.value = storedLang.value

  // watch and change lang attribute of html on language change
  watch(
    locale,
    val => {
      // Update lang attribute of html tag
      if (typeof document !== 'undefined')
        document.documentElement.setAttribute('lang', val as string)

      // Store selected language in cookie
      storedLang.value = val as string

      // set isAppRtl value based on selected language
      if (themeConfig.app.i18n.langConfig && themeConfig.app.i18n.langConfig.length) {
        themeConfig.app.i18n.langConfig.forEach(lang => {
          if (lang.i18nLang === storedLang.value)
            configStore.isAppRTL = lang.isRTL
        })
      }
    },
    { immediate: true },
  )
}

const _handleSkinChanges = () => {
  const { themes } = useTheme()
  const configStore = useConfigStore()

  // Create skin default color so that we can revert back to original (default skin) color when switch to default skin from bordered skin
  Object.values(themes.value).forEach(t => {
    t.colors['skin-default-background'] = t.colors.background
    t.colors['skin-default-surface'] = t.colors.surface
  })

  watch(
    () => configStore.skin,
    val => {
      Object.values(themes.value).forEach(t => {
        t.colors.background = t.colors[`skin-${val}-background`]
        t.colors.surface = t.colors[`skin-${val}-surface`]
      })
    },
    { immediate: true },
  )
}

/** Persist resolved colors for the next load, including system and skin changes. */
const _syncInitialLoaderTheme = () => {
  const vuetifyTheme = useTheme()

  watch(
    () => [vuetifyTheme.global.name.value, vuetifyTheme.current.value.colors.background, vuetifyTheme.current.value.colors.primary],
    ([name, background, primary]) => {
      document.documentElement.style.setProperty('--initial-loader-bg', background)
      document.documentElement.style.setProperty('--initial-loader-color', primary)
      document.documentElement.style.colorScheme = name
      try {
        localStorage.setItem(namespaceConfig(`initial-loader-${name}-bg`), background)
        localStorage.setItem(namespaceConfig(`initial-loader-${name}-color`), primary)
      }
      catch {
        // The selected theme still works when browser storage is unavailable.
      }
    },
    { immediate: true },
  )
}

const initCore = () => {
  _syncInitialLoaderTheme()
  _handleSkinChanges()

  // ℹ️ We don't want to trigger i18n in SK
  if (themeConfig.app.i18n.enable)
    _syncAppRtl()
}

export default initCore
