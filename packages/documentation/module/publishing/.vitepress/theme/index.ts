import DefaultTheme from 'vitepress/theme'
import { h, nextTick, onMounted, watch } from 'vue'
import { useRoute } from 'vitepress'
import portal from '../portal.json'
import CmsTools from './CmsTools.vue'
import { renderDiagrams } from './mermaid'
import { enhanceCode } from './code'
import { labelsFor } from './i18n'
import './custom.css'
import './content.css'

const site = portal.site as typeof portal.site & { locale?: string; theme?: Record<string, string> }
const labels = labelsFor(site.locale)

export default {
  extends: DefaultTheme,
  Layout: () => h(DefaultTheme.Layout, null, { 'nav-bar-content-before': () => h(CmsTools) }),
  setup() {
    const route = useRoute()

    const render = async () => {
      await nextTick()
      enhanceCode(document.querySelector('.vp-doc'), labels)
      await renderDiagrams(document.querySelector('.vp-doc'), document.documentElement.classList.contains('dark'))
    }

    onMounted(() => {
      const theme = site.theme || {}

      document.documentElement.classList.add(`cms-code-${theme.code_theme || 'auto'}`, `cms-layout-${theme.layout || 'default'}`)
      render()
    })
    watch(() => route.path, render)
  },
}
