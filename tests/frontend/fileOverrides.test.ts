import assert from 'node:assert/strict'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { test } from 'node:test'
import { fileOverrides, pageCollisions } from '../../packages/core/frontend/vite/modules'

function tree(files: string[]) {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'lsk-overrides-'))
  for (const file of files) {
    fs.mkdirSync(path.dirname(path.join(root, file)), { recursive: true })
    fs.writeFileSync(path.join(root, file), '')
  }

  return fs.realpathSync(root)
}

test('application files shadow core and module files with the same path', () => {
  const root = tree([
    'core/components/CoreDataTable.vue',
    'auth/stores/index.ts',
    'app/overrides/core/components/CoreDataTable.vue',
    'app/overrides/auth/stores/index.ts',
    'app/overrides/auth/components/Missing.vue',
    'app/overrides/unknown/file.ts',
  ])

  const roots = [{ name: 'core', source: path.join(root, 'core') }, { name: 'auth', source: path.join(root, 'auth') }]
  const { map, unknown } = fileOverrides(path.join(root, 'app/overrides'), roots)

  assert.equal(map.get(path.join(root, 'core/components/CoreDataTable.vue')), path.join(root, 'app/overrides/core/components/CoreDataTable.vue'))
  assert.equal(map.get(path.join(root, 'auth/stores/index.ts')), path.join(root, 'app/overrides/auth/stores/index.ts'))
  assert.deepEqual(unknown.sort(), [path.join(root, 'app/overrides/auth/components/Missing.vue'), path.join(root, 'app/overrides/unknown/file.ts')])
  fs.rmSync(root, { recursive: true })
})

test('no overrides directory means no change', () => {
  const { map, unknown } = fileOverrides('/nonexistent/overrides', [])

  assert.equal(map.size, 0)
  assert.deepEqual(unknown, [])
})

test('a root shared by the core and a module is reported and ignored', () => {
  const root = tree(['core/components/A.vue', 'module/components/A.vue', 'app/overrides/core/components/A.vue'])
  const roots = [{ name: 'core', source: path.join(root, 'core') }, { name: 'core', source: path.join(root, 'module') }]
  const { map, unknown, ambiguous } = fileOverrides(path.join(root, 'app/overrides'), roots)

  assert.deepEqual(ambiguous, ['core'])
  assert.equal(map.size, 0)
  assert.deepEqual(unknown, [path.join(root, 'app/overrides/core/components/A.vue')])
  fs.rmSync(root, { recursive: true })
})

test('a dangling symlink is reported instead of crashing', () => {
  const root = tree(['core/components/A.vue'])

  fs.mkdirSync(path.join(root, 'app/overrides/core/components'), { recursive: true })
  fs.symlinkSync(path.join(root, 'missing.vue'), path.join(root, 'app/overrides/core/components/A.vue'))

  const { map, unknown } = fileOverrides(path.join(root, 'app/overrides'), [{ name: 'core', source: path.join(root, 'core') }])

  assert.equal(map.size, 0)
  assert.equal(unknown.length, 1)
  fs.rmSync(root, { recursive: true })
})

test('page collisions ignore param names and route groups', () => {
  const root = tree(['core/users/[id].vue', 'app/users/[userId].vue', 'core/index.vue', 'app/(public)/index.vue', 'app/about.vue'])
  const collisions = pageCollisions([{ src: path.join(root, 'core'), path: '' }, { src: path.join(root, 'app'), path: '' }])

  assert.deepEqual(collisions.map(c => c.route).sort(), ['/', '/users/:param'])
  fs.rmSync(root, { recursive: true })
})
