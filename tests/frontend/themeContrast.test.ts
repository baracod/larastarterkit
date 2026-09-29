import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createVuetify } from 'vuetify'
import { themes } from '../../resources/ts/plugins/vuetify/theme'

for (const name of ['light', 'dark']) {
  test(`${name} theme derives primary text color from saved preferences`, () => {
    for (const [primary, foreground] of [['#3D68FF', '#fff'], ['#000000', '#fff'], ['#FFFFFF', '#000']]) {
      const vuetify = createVuetify({
        theme: {
          themes: {
            ...themes,
            [name]: {
              ...themes[name],
              colors: { ...themes[name].colors, primary },
            },
          },
        },
      })

      assert.equal(vuetify.theme.computedThemes.value[name].colors['on-primary'], foreground)
    }
  })

  test(`${name} theme updates primary text color after customization and reset`, () => {
    const vuetify = createVuetify({ theme: { themes } })
    const defaultPrimary = vuetify.theme.themes.value[name].colors.primary
    const defaultForeground = name === 'light' ? '#fff' : '#000'

    assert.equal(vuetify.theme.computedThemes.value[name].colors['on-primary'], defaultForeground)

    for (const [primary, foreground] of [['#FFFFFF', '#000'], ['#3D68FF', '#fff'], ['#E0B04A', '#000']]) {
      vuetify.theme.themes.value[name].colors.primary = primary
      assert.equal(vuetify.theme.computedThemes.value[name].colors['on-primary'], foreground)
    }

    vuetify.theme.themes.value[name].colors.primary = defaultPrimary
    assert.equal(vuetify.theme.computedThemes.value[name].colors['on-primary'], defaultForeground)
  })
}
