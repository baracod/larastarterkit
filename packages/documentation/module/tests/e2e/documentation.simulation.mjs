// End-to-end simulation of every Documentation use case against a running application.
// Usage (from the repository root):
//   DOC_E2E_EMAIL=… DOC_E2E_PASSWORD=… node Modules/Documentation/tests/e2e/documentation.simulation.mjs
// Optional: DOC_E2E_URL (default http://127.0.0.1:8000), DOC_E2E_OUT (report folder), DOC_E2E_HEADED=1.
// The scenario creates its own documentation ("Simulation …"); it never edits existing content.
import { Buffer } from 'node:buffer'
import { mkdirSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'
import { chromium } from 'playwright'

const base = (process.env.DOC_E2E_URL || 'http://127.0.0.1:8000').replace(/\/$/, '')
const email = process.env.DOC_E2E_EMAIL
const password = process.env.DOC_E2E_PASSWORD
if (!email || !password)
  throw new Error('Set DOC_E2E_EMAIL and DOC_E2E_PASSWORD.')

const stamp = new Date().toISOString().replace(/\D/g, '').slice(0, 12)
const out = process.env.DOC_E2E_OUT || join(process.cwd(), 'storage', 'app', 'documentation-e2e', stamp)

mkdirSync(out, { recursive: true })

const title = `Simulation ${stamp}`
const slug = `simulation-${stamp}`
const results = []
let shot = 0

const browser = await chromium.launch({ headless: !process.env.DOC_E2E_HEADED })
const context = await browser.newContext({ viewport: { width: 1600, height: 1000 }, locale: 'fr-FR', permissions: ['clipboard-read', 'clipboard-write'] })
const page = await context.newPage()
const consoleErrors = []

page.on('console', message => message.type() === 'error' && consoleErrors.push(message.text().slice(0, 300)))
page.on('pageerror', error => consoleErrors.push(`pageerror: ${error.message.slice(0, 300)}`))
page.on('dialog', dialog => dialog.accept())

async function capture(name, target = page) {
  const file = `${String(++shot).padStart(2, '0')}-${name}.png`

  await target.screenshot({ path: join(out, file), fullPage: false })

  return file
}
async function step(name, action) {
  const started = Date.now()
  try {
    const details = await action()
    const screenshot = await capture(name.replace(/\W+/g, '-').toLowerCase())

    results.push({ name, status: 'passed', ms: Date.now() - started, details, screenshot })
    console.log(`✔ ${name}`)
  }
  catch (error) {
    let screenshot = null
    try {
      screenshot = await capture(`FAILED-${name.replace(/\W+/g, '-').toLowerCase()}`)
    }
    catch { /* The page may be closed. */ }
    results.push({ name, status: 'failed', ms: Date.now() - started, error: String(error?.message || error).slice(0, 1500), screenshot })
    console.log(`✖ ${name}\n  ${String(error?.message || error).split('\n')[0]}`)
  }
}
function expect(condition, message) {
  if (!condition)
    throw new Error(message)
}
async function api(path) {
  return page.evaluate(async url => {
    const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'include' })

    return { status: response.status, body: await response.json().catch(() => null) }
  }, `/api/v1/documentation/${path}`)
}
async function confirmDialog() {
  const button = page.getByRole('button', { name: 'Confirmer' })

  await button.waitFor({ state: 'visible', timeout: 10000 })
  await button.click()
}
async function toast(text) {
  await page.locator('body').getByText(text, { exact: false }).first().waitFor({ timeout: 15000 })
}
const prose = () => page.locator('.documentation-prose')
const menuItem = name => page.locator('.v-overlay--active .v-list-item').filter({ hasText: name }).first()
const ids = {}

async function pagesOfEdition() {
  return (await api(`editions/${ids.edition}/pages`)).body
}
async function pageByPath(path) {
  return (await pagesOfEdition()).find(item => item.revision.path === path)
}
async function openEditor(query = '') {
  await page.goto(`${base}/documentation/editor?doc=${ids.collection}&edition=${ids.edition}${query}`)
  await page.locator('.cms-editor').waitFor()
  await page.waitForLoadState('networkidle')
}

// Places the caret at the very end of the document through Tiptap itself (native Ctrl+End is unreliable after tables).
async function focusEnd() {
  await prose().click()
  await page.evaluate(() => document.querySelector('.documentation-prose').editor.commands.focus('end'))
}
async function saveWithShortcut() {
  await page.keyboard.press('Control+s')
  await page.locator('[data-test=save-status]').filter({ hasText: /Enregistré à/ }).waitFor({ timeout: 15000 })
}

// 1. Authentication -----------------------------------------------------------------------------
await step('Connexion administrateur', async () => {
  await page.goto(`${base}/auth/login`)
  await page.locator('input[autocomplete=username]').fill(email)
  await page.locator('input[autocomplete=current-password]').fill(password)
  await page.locator('button[type=submit]').click()
  await page.waitForURL(url => !url.pathname.includes('/auth/login'), { timeout: 30000 })

  return { url: page.url().split('?')[0] }
})

await step('Tableau de bord Documentation', async () => {
  await page.goto(`${base}/documentation`)
  await page.getByRole('heading', { name: 'Tableau de bord' }).waitFor()
  await page.waitForLoadState('networkidle')
})

// 2. Documentation and editions -----------------------------------------------------------------
await step('Créer une documentation (slug automatique)', async () => {
  await page.goto(`${base}/documentation/collections`)
  await page.locator('[data-test=new-collection]').click()
  await page.locator('[data-test=collection-title] input').fill(title)
  await page.getByLabel('Description').fill('Guide de démonstration créé par la simulation de bout en bout.')
  await page.locator('[data-test=save-collection]').click()
  await toast('Documentation enregistrée.')

  const collection = (await api('collections')).body.find(item => item.title === title)

  expect(collection?.slug === slug, `slug attendu ${slug}, reçu ${collection?.slug}`)
  ids.collection = collection.id

  return { collection: collection.id, slug: collection.slug }
})

await step('Créer l’édition v1', async () => {
  await page.locator(`[data-test=collection-${slug}]`).click()
  await page.locator('[data-test=new-edition]').click()
  await page.locator('[data-test=edition-title] input').fill('v1')
  await page.locator('[data-test=save-edition]').click()
  await toast('Édition enregistrée.')

  const collection = (await api('collections')).body.find(item => item.id === ids.collection)

  ids.edition = collection.editions.find(item => item.slug === 'v1')?.id
  expect(ids.edition, 'édition v1 absente')

  return { edition: ids.edition }
})

// 3. Writing with the visual editor --------------------------------------------------------------
await step('Créer la page Introduction', async () => {
  await openEditor()
  await page.locator('[data-test=new-page]').click()
  await page.locator('[data-test=new-page-title] input').fill('Introduction')
  await page.locator('[data-test=create-page]').click()
  await toast('Page créée.')
  await prose().waitFor()

  const created = await pageByPath('introduction')

  expect(created, 'page introduction absente')
  ids.intro = created.id
})

await step('Rédiger : titres, gras, liste, encadré, terminal, tableau', async () => {
  const editor = prose()

  await focusEnd()
  await page.keyboard.type('Ce guide présente ')
  await page.keyboard.press('Control+b')
  await page.keyboard.type('Sneat Starter')
  await page.keyboard.press('Control+b')
  await page.keyboard.type('.')
  await page.keyboard.press('Enter')

  // Slash menu with filtering and keyboard selection.
  await page.keyboard.type('/')
  await page.locator('.slash-menu').waitFor()
  await page.keyboard.type('sous-section')
  await page.keyboard.press('Enter')
  await page.keyboard.type('Prérequis')
  await page.keyboard.press('Enter')
  await page.keyboard.type('/')
  await page.keyboard.type('etapes')
  await page.keyboard.press('Enter')
  await page.keyboard.type('Installer PHP 8.4')
  await page.keyboard.press('Enter')
  await page.keyboard.type('Installer pnpm')
  await page.keyboard.press('Enter')
  await page.keyboard.press('Enter')
  await page.keyboard.type('/')
  await page.keyboard.type('astuce')
  await page.keyboard.press('Enter')
  await page.keyboard.type('Utilisez toujours la version LTS de Node.')
  await page.locator('[data-test=callout-title]').fill('Bon à savoir')
  await page.locator('[data-test=callout-title]').press('Tab')
  await editor.locator('.cms-callout p').last().click()
  await page.keyboard.press('End')
  await page.keyboard.press('Enter')
  await page.keyboard.press('Enter')
  await page.keyboard.type('/')
  await page.keyboard.type('terminal')
  await page.keyboard.press('Enter')
  await page.keyboard.type('composer install\npnpm install')
  await page.keyboard.press('ArrowDown')
  await page.keyboard.press('Enter')
  await page.keyboard.type('/')
  await page.keyboard.type('tableau')
  await page.keyboard.press('Enter')
  await page.keyboard.type('Outil')
  await page.keyboard.press('Tab')
  await page.keyboard.type('Version')
  await page.keyboard.press('Tab')
  await page.keyboard.press('Tab')
  await page.keyboard.type('PHP')
  await page.keyboard.press('Tab')
  await page.keyboard.type('8.4')
  expect(await page.locator('.context-toolbar').isVisible(), 'barre contextuelle de tableau absente')
  await saveWithShortcut()

  const markdown = (await pageByPath('introduction')).revision.markdown
  for (const fragment of ['**Sneat Starter**', '### Prérequis', '1. Installer PHP 8.4', '> [!TIP] Bon à savoir', '```bash', 'composer install', '| Outil', '| PHP'])
    expect(markdown.includes(fragment), `fragment absent du Markdown : ${fragment}\n${markdown}`)

  return { markdown }
})

await step('Mode Markdown : diagramme Mermaid et bloc de code titré', async () => {
  await page.locator('[data-test=mode-markdown]').click()

  const source = page.locator('textarea.markdown-source')

  await source.click()
  await page.keyboard.press('Control+End')
  await source.pressSequentially('\n\n## Architecture\n\n```mermaid\nflowchart LR\n  A[Navigateur] --> B[Laravel]\n```\n\n```php [app/Models/Page.php] {2}\n<?php\nreturn Page::query();\n```\n')
  await page.locator('[data-test=mode-visual]').click()
  await page.locator('.code-view.is-mermaid').waitFor()
  expect(await page.locator('.code-view__header input').first().inputValue() === 'app/Models/Page.php', 'titre de fichier non repris dans l’en-tête du bloc')
  expect(await page.locator('.code-view .token.keyword').count() > 0, 'coloration syntaxique absente de l’éditeur')
  await saveWithShortcut()
})

await step('Aperçu en direct (vue partagée) identique au site', async () => {
  await page.locator('[data-test=view-split]').click()

  const preview = page.locator('[data-test=preview]')

  await preview.locator('.cms-callout-tip').waitFor({ timeout: 15000 })
  await preview.locator('.cms-code-title', { hasText: 'app/Models/Page.php' }).waitFor()
  await preview.locator('.cms-line-highlight').first().waitFor()
  await preview.locator('svg').first().waitFor({ timeout: 20000 })

  const calloutTitle = await preview.locator('.cms-callout-title').innerText()

  expect(calloutTitle.includes('Bon à savoir'), `titre d’encadré inattendu : ${calloutTitle}`)

  return { calloutTitle }
})

// 4. Page tree -------------------------------------------------------------------------------
await step('Sous-page via le sommaire et génération du chemin', async () => {
  await page.locator('[data-test=view-write]').click()
  await page.locator('.page-tree__item', { hasText: 'Introduction' }).hover()
  await page.locator('.page-tree__item', { hasText: 'Introduction' }).getByRole('button', { name: 'Actions' }).click()
  await menuItem('Ajouter une sous-page').click()
  await page.locator('[data-test=new-page-title] input').fill('Installation avec Docker')

  const path = await page.getByLabel('Chemin (ex. installation/demarrage)').last().inputValue()

  expect(path === 'introduction/installation-avec-docker', `chemin généré inattendu : ${path}`)
  await page.locator('[data-test=create-page]').click()
  await toast('Page créée.')
  await focusEnd()
  await page.keyboard.type('Lancez les conteneurs.')
  await saveWithShortcut()

  const child = await pageByPath(path)

  expect(child.revision.parent_id === ids.intro, 'la sous-page n’est pas rattachée à Introduction')
  ids.child = child.id

  return { path }
})

await step('Réorganiser le sommaire (remonter d’un niveau)', async () => {
  const item = page.locator('.page-tree__item', { hasText: 'Installation avec Docker' })

  await item.hover()
  await item.getByRole('button', { name: 'Actions' }).click()
  await menuItem('Remonter d’un niveau').click()
  await toast('Sommaire réorganisé.')

  const child = await pageByPath('introduction/installation-avec-docker')

  expect(child.revision.parent_id === null && child.revision.position === 1, `position inattendue ${JSON.stringify(child.revision)}`)
})

// 5. Media ------------------------------------------------------------------------------------
await step('Médiathèque : import, texte alternatif, métadonnées', async () => {
  await page.goto(`${base}/documentation/media?doc=${ids.collection}&edition=${ids.edition}`)
  await page.getByRole('heading', { name: 'Médiathèque' }).waitFor()
  // The upload control appears after the documentation context has loaded.
  await page.getByRole('button', { name: 'Importer', exact: true }).waitFor()

  const png = await page.evaluate(() => {
    const canvas = document.createElement('canvas')

    canvas.width = 640
    canvas.height = 360

    const draw = canvas.getContext('2d')
    const gradient = draw.createLinearGradient(0, 0, 640, 360)

    gradient.addColorStop(0, '#50bdff')
    gradient.addColorStop(1, '#b932fa')
    draw.fillStyle = gradient
    draw.fillRect(0, 0, 640, 360)
    draw.fillStyle = '#fff'
    draw.font = 'bold 42px sans-serif'
    draw.fillText('Capture de démonstration', 60, 190)

    return canvas.toDataURL('image/png').split(',')[1]
  })

  await page.locator('[data-test=media-input]').setInputFiles({ name: 'ecran-accueil.png', mimeType: 'image/png', buffer: Buffer.from(png, 'base64') })
  await toast('1 image(s) importée(s).')

  const image = (await api(`collections/${ids.collection}/images`)).body.find(item => item.name === 'ecran-accueil.png')

  expect(image?.alt === 'ecran accueil', `texte alternatif par défaut inattendu : ${image?.alt}`)
  ids.image = image.id
  await page.locator(`[data-test=media-${image.id}]`).click()
  await page.locator('[data-test=media-alt] textarea').first().fill('Écran d’accueil de l’application')
  await page.locator('[data-test=media-save]').click()
  await toast('Image mise à jour.')

  const updated = (await api(`collections/${ids.collection}/images`)).body.find(item => item.id === image.id)

  expect(updated.alt === 'Écran d’accueil de l’application', 'texte alternatif non enregistré')

  return { image: image.id, width: updated.width, height: updated.height }
})

await step('Insérer l’image depuis l’éditeur et vérifier son utilisation', async () => {
  await openEditor(`&page=${ids.intro}`)
  await focusEnd()
  await page.keyboard.press('Enter')
  await page.keyboard.type('/')
  await page.keyboard.type('image')
  await page.keyboard.press('Enter')
  await page.locator('.picker-item', { hasText: 'ecran-accueil.png' }).click()
  await prose().locator('img[alt="Écran d’accueil de l’application"]').waitFor()
  await saveWithShortcut()

  const image = (await api(`collections/${ids.collection}/images`)).body.find(item => item.id === ids.image)

  expect(image.usage.some(use => use.page_id === ids.intro), 'utilisation de l’image non détectée')
})

// 6. Versions of content ----------------------------------------------------------------------
await step('Révision avec note, comparaison et restauration', async () => {
  await focusEnd()
  await page.keyboard.press('Enter')
  await page.keyboard.type('Paragraphe temporaire à annuler.')
  await page.locator('[data-test=history-tab]').click()
  await page.locator('[data-test=revision-note] input').fill('Ajout d’un paragraphe temporaire')
  await saveWithShortcut()

  const revisions = (await api(`pages/${ids.intro}/revisions`)).body

  expect(revisions[0].note === 'Ajout d’un paragraphe temporaire', 'note de révision absente')
  expect(revisions[0].author?.name && !('role_names' in revisions[0].author), 'auteur de révision mal exposé')

  await page.locator('.revision-item').nth(1).click()
  await page.locator('.diff-line.is-added', { hasText: 'Paragraphe temporaire' }).waitFor()
  await capture('diff-revision')
  await page.getByRole('button', { name: 'Restaurer cette version' }).click()
  await confirmDialog()
  await toast('Version restaurée.')

  const markdown = (await pageByPath('introduction')).revision.markdown

  expect(!markdown.includes('Paragraphe temporaire'), 'la restauration n’a pas retiré le paragraphe')

  return { revisions: revisions.length + 1 }
})

await step('Brouillon local récupéré après rechargement', async () => {
  await focusEnd()
  await page.keyboard.press('Enter')
  await page.keyboard.type('Texte non enregistré à récupérer.')
  await page.waitForTimeout(1200)
  await page.reload()
  await page.locator('[data-test=restore-draft]').click()
  await prose().getByText('Texte non enregistré à récupérer.').waitFor()
  await saveWithShortcut()
})

// 7. Portal configuration and theme ----------------------------------------------------------
await step('Configuration : identité et thème (palette, mode, code)', async () => {
  await page.goto(`${base}/documentation/configuration?doc=${ids.collection}&edition=${ids.edition}`)
  await page.getByRole('heading', { name: 'Configurations' }).waitFor()

  // A unique footer guarantees a real change even when the theme already matches a previous run.
  await page.getByRole('tab', { name: 'Navigation' }).click()
  await page.getByLabel('Pied de page').fill(`Documentation publiée par la simulation ${stamp}`)
  await page.locator('[data-test=theme-tab]').click()
  await page.getByRole('button', { name: 'Océan' }).click()
  await page.locator('[data-test=appearance-select]').click()
  await page.locator('.v-overlay--active .v-list-item').filter({ hasText: 'Sombre par défaut avec bascule' }).click()

  // Live preview of the portal in dark mode.
  await page.locator('[data-test=preview-dark]').click()
  await page.locator('.portal-preview.is-dark').waitFor()
  await capture('apercu-theme-sombre')
  await page.locator('[data-test=save-settings]').click()
  await toast('Réglages enregistrés')

  const settings = (await api('site-settings')).body.content

  expect(settings.theme.brand_color === '#0ea5e9' && settings.theme.appearance === 'dark' && settings.footer.includes(stamp), `réglages inattendus ${JSON.stringify(settings)}`)

  return { theme: settings.theme }
})

// 8. Publishing -------------------------------------------------------------------------------
async function waitForPublication(since) {
  for (let attempt = 0; attempt < 120; attempt++) {
    const latest = (await api('publications')).body.find(item => item.id > since)
    if (latest && ['succeeded', 'failed'].includes(latest.status))
      return latest
    await page.waitForTimeout(2000)
  }
  throw new Error('La publication n’est pas terminée après 4 minutes (le worker documentation tourne-t-il ?).')
}

await step('Publier l’édition et suivre la construction', async () => {
  const before = Math.max(0, ...(await api('publications')).body.map(item => item.id))

  await openEditor(`&page=${ids.intro}`)
  await page.locator('[data-test=publish]').click()
  await confirmDialog()
  await page.goto(`${base}/documentation/publications?doc=${ids.collection}&edition=${ids.edition}`)

  const publication = await waitForPublication(before)

  expect(publication.status === 'succeeded', `publication en échec :\n${publication.log}`)
  await page.reload()
  await page.locator(`[data-test=publication-${publication.id}] [data-status=succeeded]`).waitFor({ timeout: 15000 })
  ids.publication = publication.id

  return { publication: publication.id, author: publication.author?.name, target: publication.edition?.title }
})

const publicBase = `${base}/docs/${slug}/v1`

await step('Site public : page, encadré, code, image et thème', async () => {
  const site = await context.newPage()

  await site.goto(`${publicBase}/introduction.html`)
  await site.locator('.vp-doc .cms-callout-tip .cms-callout-title', { hasText: 'Bon à savoir' }).waitFor()
  await site.locator('.vp-doc .cms-code-title', { hasText: 'app/Models/Page.php' }).waitFor()
  await site.locator('.vp-doc .cms-line-highlight').first().waitFor()
  await site.locator('.vp-doc .cms-diagram svg').first().waitFor({ timeout: 20000 })
  await site.locator('.vp-doc img[alt="Écran d’accueil de l’application"]').waitFor()

  // Dark mode uses lighter brand shades for contrast; the configured color is the deepest one (brand-3).
  const brand = await site.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--vp-c-brand-3').trim())

  expect(brand === '#0ea5e9', `couleur du thème non appliquée : ${brand}`)

  const dark = await site.evaluate(() => document.documentElement.classList.contains('dark'))

  expect(dark, 'le mode sombre par défaut n’est pas appliqué')
  await capture('site-public-page-sombre', site)
  await site.close()

  return { brand, dark }
})

await step('Site public : recherche Ctrl+K et navigation clavier', async () => {
  const site = await context.newPage()

  await site.goto(`${publicBase}/introduction.html`)
  await site.waitForLoadState('networkidle')
  await site.locator('.cms-search-trigger').waitFor()
  await site.keyboard.press('Control+k')
  await site.locator('#cms-search').fill('conteneurs')
  await site.locator('.cms-search-results li.is-active mark').first().waitFor()
  await capture('site-public-recherche', site)
  await site.keyboard.press('Enter')
  await site.waitForURL(/installation-avec-docker\.html/)
  await site.close()
})

await step('Site public : page d’accueil de l’édition et du portail', async () => {
  const site = await context.newPage()

  await site.goto(`${publicBase}/index.html`)
  await site.locator('.cms-chapters .cms-chapter', { hasText: 'Introduction' }).waitFor()
  await capture('site-public-edition', site)
  await site.goto(`${base}/docs/index.html`)
  await site.locator('.cms-catalogue section', { hasText: title }).waitFor()
  await capture('site-public-accueil', site)
  await site.close()
})

// 9. Editions lifecycle and rollback ---------------------------------------------------------
await step('Dupliquer l’édition v1 en v2 puis l’archiver et la réactiver', async () => {
  await page.goto(`${base}/documentation/collections?doc=${ids.collection}&edition=${ids.edition}`)

  const row = page.locator('tr', { hasText: 'v1' }).first()

  await row.getByRole('button', { name: 'Actions' }).click()
  await menuItem('Dupliquer l’édition').click()
  await page.locator('[data-test=edition-title] input').fill('v2')
  await page.locator('[data-test=save-edition]').click()
  await toast('Édition enregistrée.')

  const v2 = (await api('collections')).body.find(item => item.id === ids.collection).editions.find(item => item.slug === 'v2')
  const copies = (await api(`editions/${v2.id}/pages`)).body

  expect(copies.length === 2, `pages dupliquées : ${copies.length}`)

  const v2Row = page.locator('tr', { hasText: 'v2' }).first()

  await v2Row.getByRole('button', { name: 'Actions' }).click()
  await menuItem('Archiver').click()
  await confirmDialog()
  await toast('Élément archivé.')
  await page.locator('.v-switch').filter({ hasText: 'Afficher les archives' }).locator('label').click()
  await page.locator('tr', { hasText: 'v2' }).first().getByRole('button', { name: 'Actions' }).click()
  await menuItem('Réactiver').click()
  await confirmDialog()
  await toast('Élément réactivé.')

  return { v2: v2.id, pages: copies.length }
})

await step('Restaurer (rollback) une publication précédente du portail', async () => {
  const before = Math.max(0, ...(await api('publications')).body.map(item => item.id))

  await page.goto(`${base}/documentation/publications?doc=${ids.collection}&edition=${ids.edition}`)

  const row = page.locator(`[data-test=publication-${ids.publication}]`)

  await row.getByRole('button', { name: 'Restaurer' }).click()
  await confirmDialog()

  const restore = await waitForPublication(before)

  expect(restore.status === 'succeeded' && restore.kind === 'restore', `restauration en échec :\n${restore.log}`)
  await page.reload()
  await page.locator(`[data-test=publication-${restore.id}] [data-status=succeeded]`).waitFor({ timeout: 15000 })

  return { restore: restore.id }
})

await step('Écran mobile : éditeur et site public', async () => {
  const mobile = await browser.newContext({ viewport: { width: 390, height: 844 }, storageState: await context.storageState(), isMobile: true })
  const phone = await mobile.newPage()

  await phone.goto(`${base}/documentation/editor?doc=${ids.collection}&edition=${ids.edition}&page=${ids.intro}`)
  await phone.locator('.cms-editor').waitFor()
  await phone.waitForLoadState('networkidle')
  await phone.locator('.page-head__title').waitFor()
  await capture('mobile-editeur', phone)
  await phone.goto(`${publicBase}/introduction.html`)
  await phone.locator('.vp-doc').waitFor()
  await capture('mobile-site', phone)

  const overflow = await phone.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1)

  expect(!overflow, 'défilement horizontal sur mobile')
  await mobile.close()
})

await browser.close()

const passed = results.filter(item => item.status === 'passed').length
const report = { url: base, collection: { id: ids.collection, slug, title }, startedAt: stamp, passed, failed: results.length - passed, results, consoleErrors: [...new Set(consoleErrors)].slice(0, 50) }

writeFileSync(join(out, 'report.json'), JSON.stringify(report, null, 2))
writeFileSync(join(out, 'report.md'), [
  `# Simulation Documentation — ${stamp}`,
  '',
  `Application : ${base} · Documentation créée : **${title}** (\`${slug}\`)`,
  '',
  `Résultat : **${passed}/${results.length}** scénarios réussis.`,
  '',
  '| # | Scénario | Résultat | Durée | Capture |',
  '| --- | --- | --- | --- | --- |',
  ...results.map((item, index) => `| ${index + 1} | ${item.name} | ${item.status === 'passed' ? '✅' : `❌ ${item.error.split('\n')[0].replace(/\|/g, '\\|')}`} | ${(item.ms / 1000).toFixed(1)} s | ${item.screenshot ? `![](${item.screenshot})` : ''} |`),
  '',
  report.consoleErrors.length ? `## Erreurs console\n\n${report.consoleErrors.map(error => `- \`${error.replace(/`/g, '\'')}\``).join('\n')}` : 'Aucune erreur console.',
  '',
].join('\n'))
console.log(`\n${passed}/${results.length} scénarios réussis — rapport : ${join(out, 'report.md')}`)
process.exit(passed === results.length ? 0 : 1)
