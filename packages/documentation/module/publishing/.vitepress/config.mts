import { defineConfig } from 'vitepress'
import portal from './portal.json'
import { labelsFor } from './theme/i18n'
import { themeStyle } from './theme/palette'

const site = portal.site as typeof portal.site & { locale?: string; description?: string; theme?: Record<string, string> }
const theme = site.theme || {}
const labels = labelsFor(site.locale)
const escape = (text: string) => text.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#039;' }[c]!))

// VitePress: true follows the system with a toggle, false is light only.
const appearances = { 'auto': true, 'light': { initialValue: 'light' as const }, 'dark': 'dark' as const, 'force-dark': 'force-dark' as const, 'force-light': false }
const appearance = appearances[theme.appearance as keyof typeof appearances] ?? true

export default defineConfig({
  title: site.title,
  lang: labels.lang,
  appearance,
  description: site.description || site.tagline || '',
  base: portal.base,
  cleanUrls: false,
  lastUpdated: false,
  head: [
    ['meta', { name: 'theme-color', content: theme.brand_color || '#696cff' }],
    ['style', { id: 'cms-theme' }, themeStyle(theme)],
  ],
  vite: { build: { chunkSizeWarningLimit: 1500 } },
  themeConfig: {
    logo: site.logo_image || undefined,
    nav: site.nav.length ? site.nav : [{ text: labels.catalogue, link: '/#catalogue' }],
    socialLinks: site.github_url ? [{ icon: 'github', link: site.github_url }] : [],
    footer: { message: escape(site.footer || '') },
    skipToContentLabel: labels.skipToContent,
    darkModeSwitchLabel: labels.appearance,
    lightModeSwitchTitle: labels.lightTheme,
    darkModeSwitchTitle: labels.darkTheme,
    sidebar: Object.fromEntries(portal.editions.map(edition => [`/${edition.prefix}/`, edition.navigation])),
    outline: { level: [2, 3], label: labels.outline },
    docFooter: { prev: labels.previous, next: labels.next },
    sidebarMenuLabel: labels.sidebarMenu,
    returnToTopLabel: labels.returnToTop,
  },
})
