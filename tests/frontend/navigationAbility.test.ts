import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { runInNewContext } from 'node:vm'
import { createMongoAbility } from '@casl/ability'
import { ModuleKind, transpileModule } from 'typescript'

const source = readFileSync(new URL('../../resources/ts/@layouts/plugins/casl.ts', import.meta.url), 'utf8')
const { outputText } = transpileModule(source, { compilerOptions: { module: ModuleKind.CommonJS } })

test('navigation uses current shared abilities outside a Vue component after asynchronous work', async () => {
  const ability = createMongoAbility([{ action: 'browse', subject: 'auth_users' }])
  const navigation: any = {}

  runInNewContext(outputText, {
    exports: navigation,
    require: (name: string) => {
      assert.equal(name, '@/plugins/casl/ability')

      return { ability }
    },
  })
  await Promise.resolve()

  const users = { matched: [{ meta: { action: 'browse', subject: 'auth_users' } }] }
  const roles = { matched: [{ meta: { action: 'browse', subject: 'auth_roles' } }] }

  assert.equal(navigation.canNavigate(users), true)
  assert.equal(navigation.canNavigate(roles), false)
  ability.update([{ action: 'manage', subject: 'all' }])
  assert.equal(navigation.canNavigate(roles), true)
  ability.update([])
  assert.equal(navigation.canNavigate(users), false)
})
