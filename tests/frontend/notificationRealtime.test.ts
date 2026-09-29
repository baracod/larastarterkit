import assert from 'node:assert/strict'
import { beforeEach, test } from 'node:test'
import { createPinia, setActivePinia } from 'pinia'
import { useNotificationStore } from '../../Modules/Admin/resources/ts/stores/notifications'
import { NotificationAPI } from '../../Modules/Admin/resources/ts/api/NotificationAPI'

let toasts = 0
let handlers: ((payload: unknown) => void)[] = []
beforeEach(() => {
  setActivePinia(createPinia())
  handlers = []
  toasts = 0
  Object.assign(globalThis, {
    window: { Echo: { private: () => ({ listen: (_event: string, listener: (payload: unknown) => void) => handlers.push(listener) }) } },
    useNotify: () => ({ notify: () => toasts++ }),
  })
})

const notification = { id: 12, user_id: 42, title: 'Test', type: 'info' as const, message: 'Message', data: null, read_at: null, created_at: '2026-09-12', updated_at: '2026-09-12' }

test('repeated notification setup and repeated delivery do not duplicate rows, counts or toasts', () => {
  const store = useNotificationStore()

  store.setupWebsocketListener(42)
  store.setupWebsocketListener(42)
  handlers.forEach(listener => listener({ notification }))
  handlers.forEach(listener => listener({ notification }))
  assert.equal(store.notifications.length, 1)
  assert.equal(store.unreadCount, 1)
  assert.equal(toasts, 1)
})

test('system notifications show a toast and refresh persisted rows instead of inserting an item without an ID', async () => {
  const store = useNotificationStore()

  NotificationAPI.getAll = async () => ({ data: [notification], current_page: 1, last_page: 1, total: 1, per_page: 15 })
  NotificationAPI.unreadCount = async () => ({ count: 1 })
  store.addReceivedNotification({ title: 'System', type: 'info', message: 'Message' })
  await new Promise(resolve => setTimeout(resolve, 0))
  assert.equal(toasts, 1)
  assert.deepEqual(store.notifications.map(item => item.id), [12])
  assert.equal(store.unreadCount, 1)
})

test('an old HTTP response cannot restore the previous accounts notifications after reset', async () => {
  const store = useNotificationStore()
  let finish!: (value: any) => void
  NotificationAPI.getAll = () => new Promise(resolve => {
    finish = resolve
  })

  const pending = store.fetchNotifications()

  store.resetSession()
  finish({ data: [notification], current_page: 1, last_page: 1 })
  await pending
  assert.deepEqual(store.notifications, [])
})
