import assert from 'node:assert/strict'
import { test } from 'node:test'
import { overridePages, redirectFrom, resolveSlot, withQuery } from '../../packages/core/frontend/resources/ts/extension/core'

const Login = { name: 'Login' }
const AppLogin = { name: 'AppLogin' }
const Help = { name: 'Help' }
const Contact = { name: 'Contact' }

test('redirects keep the historical defaults', () => {
  assert.equal(redirectFrom(undefined, 'home'), '/')
  assert.deepEqual(redirectFrom({}, 'login'), { name: 'auth-login' })
  assert.deepEqual(redirectFrom({}, 'mustChangePassword'), { name: 'auth-change-password' })
})

test('redirects can be values or functions of the user', () => {
  const user = { id: 1, name: 'Ada', email: 'ada@example.test' }

  assert.equal(redirectFrom({ afterLogin: '/espace' }, 'afterLogin'), '/espace')
  assert.equal(redirectFrom({ afterLogin: u => `/espace/${u?.id}` }, 'afterLogin', user), '/espace/1')
})

test('login targets keep their own query', () => {
  assert.deepEqual(withQuery('/connexion', { to: '/x' }), { path: '/connexion', query: { to: '/x' } })
  assert.deepEqual(withQuery({ name: 'auth-login', query: { a: '1' } } as any, { to: '/x' }), { name: 'auth-login', query: { a: '1', to: '/x' } })
})

test('a bare slot component replaces the default content', () => {
  assert.deepEqual(resolveSlot(Help, {}), { replacement: Help, contributions: [] })
  assert.deepEqual(resolveSlot(undefined, {}), { contributions: [] })
})

test('slot contributions are ordered, filtered and receive the context', () => {
  const resolved = resolveSlot([
    { component: Contact, order: 20 },
    { component: Help, order: 10, props: (context: any) => ({ user: context.user }) },
    { component: Login, when: (context: any) => context.user === null },
  ], { user: 'ada' })

  assert.deepEqual(resolved.contributions.map(c => c.component), [Help, Contact])
  assert.deepEqual(resolved.contributions[0].props, { user: 'ada' })
})

test('page overrides keep the route name, meta and children', () => {
  const routes = [{
    path: '/auth',
    children: [{ path: 'login', name: 'auth-login', component: Login, meta: { layout: 'blank', unauthenticatedOnly: true } }],
  }] as any

  const result = overridePages(routes, { 'auth-login': { component: AppLogin, layout: 'public' } } as any)
  const login = result.routes[0].children![0] as any

  assert.equal(login.name, 'auth-login')
  assert.equal(login.component, AppLogin)
  assert.deepEqual(login.meta, { layout: 'public', unauthenticatedOnly: true })
  assert.equal(routes[0].children[0].component, Login)
  assert.deepEqual(result.unknown, [])
})

test('unknown page overrides are reported', () => {
  assert.deepEqual(overridePages([], { 'missing-page': AppLogin } as any).unknown, ['missing-page'])
})
