import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { runInNewContext } from 'node:vm'

const script = readFileSync(new URL('../../public/initial-theme.js', import.meta.url), 'utf8')

function loadPreferences(cookie: string, systemDark = false, saved: Record<string, string> = {}, blockedStorage = false) {
  const colors: Record<string, string> = {}

  const root = {
    lang: '',
    style: {
      colorScheme: '',
      setProperty: (name: string, value: string) => { colors[name] = value },
    },
  }

  runInNewContext(script, {
    document: { cookie, documentElement: root },
    window: { matchMedia: () => ({ matches: systemDark }) },
    localStorage: {
      getItem: (key: string) => {
        if (blockedStorage)
          throw new Error('Storage unavailable')

        return saved[key] ?? null
      },
    },
  })

  return { root, colors }
}

test('explicit dark theme overrides a light system before Vue loads', () => {
  const { root, colors } = loadPreferences('STARTER-theme=dark')

  assert.equal(root.style.colorScheme, 'dark')
  assert.equal(colors['--initial-loader-bg'], '#232333')
})

test('explicit light theme overrides a dark system', () => {
  const { root, colors } = loadPreferences('STARTER-theme=light', true)

  assert.equal(root.style.colorScheme, 'light')
  assert.equal(colors['--initial-loader-bg'], '#f5f5f9')
})

test('system mode uses the current OS preference instead of the last visited theme', () => {
  for (const cookie of ['', 'STARTER-theme=system; STARTER-color-scheme=light']) {
    const { root, colors } = loadPreferences(cookie, true, { 'STARTER-initial-loader-light-bg': '#ffffff' })

    assert.equal(root.style.colorScheme, 'dark')
    assert.equal(colors['--initial-loader-bg'], '#232333')
  }
})

test('saved skin and primary colors are restored for the selected theme only', () => {
  const { colors } = loadPreferences('STARTER-theme=dark', false, {
    'STARTER-initial-loader-dark-bg': '#2B2C40',
    'STARTER-initial-loader-dark-color': '#E0B04A',
    'STARTER-initial-loader-light-bg': '#ffffff',
  })

  assert.equal(colors['--initial-loader-bg'], '#2B2C40')
  assert.equal(colors['--initial-loader-color'], '#E0B04A')
})

test('unavailable storage and malformed saved colors keep a valid dark background', () => {
  const blocked = loadPreferences('STARTER-theme=dark', false, {}, true)
  const malformed = loadPreferences('STARTER-theme=dark', false, { 'STARTER-initial-loader-dark-bg': 'undefined' })

  assert.equal(blocked.colors['--initial-loader-bg'], '#232333')
  assert.equal(malformed.colors['--initial-loader-bg'], '#232333')
})

test('language is restored before the application is mounted', () => {
  assert.equal(loadPreferences('STARTER-language=en').root.lang, 'en')
  assert.equal(loadPreferences('STARTER-language=fr').root.lang, 'fr')
  assert.equal(loadPreferences('').root.lang, 'fr')
})
