import assert from 'node:assert/strict'
import { test } from 'node:test'
import { UserAPI } from '../../Modules/Auth/resources/ts/api/User'

test('admin password reset sends only the target and email language', async () => {
  let request: { url: string; options: any } | undefined
  const previousApi = (globalThis as any).$api

  Object.assign(globalThis, {
    $api: async (url: string, options: any) => { request = { url, options } },
  })
  try {
    await UserAPI.forceChangePassword({
      userId: 42,
      emailLocale: 'en',
    })
    assert.ok(request?.url.endsWith('/users/force-change-password'))
    assert.equal(request?.options.method, 'POST')
    assert.deepEqual(request?.options.body, { user_id: 42, email_locale: 'en' })
  }
  finally {
    if (previousApi === undefined)
      delete (globalThis as any).$api
    else
      Object.assign(globalThis, { $api: previousApi })
  }
})
