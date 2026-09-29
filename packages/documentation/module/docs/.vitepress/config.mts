import { defineConfig } from 'vitepress'
import { withMermaid } from 'vitepress-plugin-mermaid'

export default withMermaid(defineConfig({
  title: 'Sneat Starter',
  lang: 'fr-FR',
  description: 'Starter Laravel et Vue modulaire',
  cleanUrls: false,
  themeConfig: {
    search: { provider: 'local' },
    nav: [{ text: 'Accueil', link: '/' }, { text: 'Installation', link: '/installation' }],
    sidebar: [{ text: 'Distribution et mises à jour', link: '/distribution' }, { text: 'CMS et publication', link: '/cms' }, { text: 'Installation', link: '/installation' }, { text: 'Auth et Admin', link: '/auth-admin' }, { text: 'Modules et CRUD', link: '/modules' }, { text: 'Composants', link: '/components' }, { text: 'Documents', link: '/documents' }, { text: 'Tests', link: '/tests' }, { text: 'D\u00E9ploiement', link: '/deployment' }],
  },
}))
