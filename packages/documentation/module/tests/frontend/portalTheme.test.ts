import assert from 'node:assert/strict'
import { test } from 'node:test'
import { highlight, searchPortal } from '../../publishing/.vitepress/theme/search'
import { labelsFor } from '../../publishing/.vitepress/theme/i18n'
import { brandPalette, safeColor, themeStyle } from '../../publishing/.vitepress/theme/palette'

const entries = [
  { scope: 'guide/v1', title: 'Installation', url: '/docs/guide/v1/install.html', text: 'Préparez Docker puis lancez la commande de démarrage.' },
  { scope: 'guide/v1', title: 'Déploiement Docker', url: '/docs/guide/v1/deploy.html', text: 'Publier en production.' },
  { scope: 'api/v2', title: 'Docker API', url: '/docs/api/v2/docker.html', text: 'Référence.' },
]

test('portal search is accent-insensitive, scoped and ranks title matches first', () => {
  assert.deepEqual(searchPortal(entries, 'docker', 'guide/v1').map(result => result.url), ['/docs/guide/v1/deploy.html', '/docs/guide/v1/install.html'])
  assert.deepEqual(searchPortal(entries, 'DEMARRAGE').map(result => result.url), ['/docs/guide/v1/install.html'])
  assert.deepEqual(searchPortal(entries, 'docker production').map(result => result.url), ['/docs/guide/v1/deploy.html'])
  assert.deepEqual(searchPortal(entries, '   '), [])
})

test('highlighting splits text without HTML and keeps original accents', () => {
  assert.deepEqual(highlight('Déploiement <b>', ['deploiement']), [{ text: 'Déploiement', match: true }, { text: ' <b>', match: false }])
  assert.deepEqual(highlight('abc', []), [{ text: 'abc', match: false }])
})

test('theme colors are validated before reaching the injected stylesheet', () => {
  assert.equal(safeColor('#ABCDEF', '#000000'), '#abcdef')
  assert.equal(safeColor('red;}</style><script>', '#696cff'), '#696cff')
  assert.match(themeStyle({ brand_color: '#16a34a' }), /--vp-c-brand-2:#16a34a/)
  assert.doesNotMatch(themeStyle({ brand_color: '</style>', hero_from: 'url(x)' }), /<|url\(/)
  assert.equal(brandPalette('#000000').dark[1], '#595959')
})

test('portal labels follow the configured locale with a French fallback', () => {
  assert.equal(labelsFor('en').search, 'Search')
  assert.equal(labelsFor('fr').search, 'Rechercher')
  assert.equal(labelsFor('de').search, 'Rechercher')
})
