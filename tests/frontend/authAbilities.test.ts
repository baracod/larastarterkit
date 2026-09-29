import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { runInNewContext } from 'node:vm'
import { createPinia, defineStore, setActivePinia } from 'pinia'
import { computed, nextTick, ref } from 'vue'
import { ModuleKind, transpileModule } from 'typescript'
import { createMongoAbility } from '@casl/ability'

const source = readFileSync(new URL('../../Modules/Auth/resources/ts/stores/index.ts', import.meta.url), 'utf8')
const { outputText } = transpileModule(source, { compilerOptions: { module: ModuleKind.CommonJS } })

function loadStore() {
  setActivePinia(createPinia())

  const rules = ref([])
  const storeModule: any = {}

  // Use the real store and Vue/Pinia; isolate its network and browser dependencies.
  runInNewContext(outputText, {
    exports: storeModule,
    require: (name: string) => {
      if (name === '@/utils/realtime')
        return { stopRealtime: () => {} }
      if (name === '@auth/api/Auth')
        return { AuthAPI: { logout: async () => {} } }
      throw new Error(`Unexpected import: ${name}`)
    },
    defineStore,
    computed,
    ref,
    nextTick,
    useStorage: () => rules,
    useRouter: () => ({ replace: async () => {} }),
    useCookie: () => ref(null),
  })

  return { store: storeModule.useAuthStore(), rules }
}

test('hydration preserves public and always-allowed rules for CASL and module navigation', async () => {
  const { store, rules } = loadStore()

  const abilityRules = [
    { action: 'access', subject: 'dashboard' },
    { action: 'access', subject: 'documentation' },
    { action: 'browse', subject: 'fixture_entries' },
  ]

  await store.hydrate({ user: { id: 1 }, permissions: [], abilityRules })

  const ability = createMongoAbility(rules.value)

  for (const { action, subject } of abilityRules) {
    assert.equal(ability.can(action, subject), true)
    assert.equal(store.can(action, subject), true)
    assert.equal(store.hasAbility(`${action}:${subject}`), true)
  }
  assert.equal(store.can('delete', 'fixture_entries'), false)

  await store.hydrate({ user: { id: 2 }, permissions: [], abilityRules: [] })
  assert.equal(rules.value.length, 0)
  assert.equal(store.can('access', 'documentation'), false)
  assert.equal(store.hasAbility('access:documentation'), false)
})

test('administrator keeps full access and logout clears rules', async () => {
  const { store, rules } = loadStore()

  await store.hydrate({ user: { id: 1 }, roles: [{ name: 'administrator' }], permissions: [], abilityRules: [] })
  assert.equal(createMongoAbility(rules.value).can('delete', 'future_feature'), true)
  assert.equal(store.can('delete', 'future_feature'), true)
  await store.logout()
  assert.equal(rules.value.length, 0)
  assert.equal(store.can('delete', 'future_feature'), false)
})

test('legacy hydration falls back to assigned permissions when rules are absent', async () => {
  const { store } = loadStore()

  await store.hydrate({ user: { id: 1 }, permissions: [{ action: 'browse', subject: 'fixture_entries' }] })
  assert.equal(store.can('browse', 'fixture_entries'), true)
  assert.equal(store.can('delete', 'fixture_entries'), false)
})
