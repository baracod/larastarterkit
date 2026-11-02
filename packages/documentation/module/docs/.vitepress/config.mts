import { defineConfig } from 'vitepress'
import { withMermaid } from 'vitepress-plugin-mermaid'

export default withMermaid(defineConfig({
  title: 'Larastarterkit',
  lang: 'fr-FR',
  description: 'Socle Laravel et Vue modulaire : guide d\'utilisation et de maintenance',
  cleanUrls: false,
  themeConfig: {
    search: { provider: 'local' },
    nav: [
      { text: 'Accueil', link: '/' },
      { text: 'Utiliser', link: '/concepts' },
      { text: 'Maintenir', link: '/maintenir-depot' },
    ],
    sidebar: [
      {
        text: 'Utiliser le starter',
        items: [
          { text: 'Concepts', link: '/concepts' },
          { text: 'Installation', link: '/installation' },
          { text: 'Configuration', link: '/configuration' },
          { text: 'Auth et Admin', link: '/auth-admin' },
          { text: 'Créer un module', link: '/modules' },
          { text: 'Générateur CRUD', link: '/generateur' },
          { text: 'Points d\'extension', link: '/extension' },
          { text: 'Composants', link: '/components' },
          { text: 'Documents', link: '/documents' },
          { text: 'CMS de documentation', link: '/cms' },
          { text: 'Mettre à jour', link: '/distribution' },
          { text: 'Tests', link: '/tests' },
          { text: 'Déploiement', link: '/deployment' },
          { text: 'Commandes', link: '/commandes' },
          { text: 'Dépannage', link: '/depannage' },
        ],
      },
      {
        text: 'Maintenir le starter',
        items: [
          { text: 'Le dépôt', link: '/maintenir-depot' },
          { text: 'Architecture interne', link: '/maintenir-architecture' },
          { text: 'API publique et versions', link: '/maintenir-api-publique' },
          { text: 'Qualité et tests', link: '/maintenir-qualite' },
          { text: 'Publier une version', link: '/maintenir-publication' },
          { text: 'Feuille de route', link: '/maintenir-feuille-de-route' },
        ],
      },
    ],
  },
}))
