import assert from 'node:assert/strict'
import { test } from 'node:test'
import { clearRealtimeSubscriptions, subscribePrivateOnce } from '../../resources/ts/utils/realtimeSubscriptions'

function client() {
  const handlers: { channel: string; event: string; listener: (payload: unknown) => void }[] = []

  return {
    handlers,
    private: (channel: string) => ({
      listen: (event: string, listener: (payload: unknown) => void) => handlers.push({ channel, event, listener }),
    }),
  }
}

test('repeated setup delivers a notification only once', () => {
  const echo = client()
  let received = 0
  for (let i = 0; i < 3; i++)
    subscribePrivateOnce(echo, 'user.42', '.notification.created', () => received++)
  echo.handlers.forEach(handler => handler.listener({ notification: { title: 'Test' } }))
  assert.equal(received, 1)
})

test('replacement client restores geographic subscriptions and invalidates old callbacks', () => {
  const first = client()
  const second = client()
  let refreshed = 0
  subscribePrivateOnce(first, 'form-location-references', '.form-location-references.changed', () => refreshed++)
  first.handlers[0].listener({})
  clearRealtimeSubscriptions(first)
  subscribePrivateOnce(second, 'form-location-references', '.form-location-references.changed', () => refreshed++)
  first.handlers[0].listener({})
  second.handlers[0].listener({})
  assert.equal(refreshed, 2)
})
