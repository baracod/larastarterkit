export interface RealtimeClient {
  private: (channel: string) => {
    listen: (event: string, listener: (payload: any) => void) => unknown
  }
}

const subscriptions = new WeakMap<RealtimeClient, Set<string>>()

// Each Echo instance owns its subscriptions; a replacement must subscribe anew.
export function subscribePrivateOnce(client: RealtimeClient, channel: string, event: string, listener: (payload: any) => void) {
  let active = subscriptions.get(client)
  if (!active) {
    active = new Set()
    subscriptions.set(client, active)
  }
  const key = JSON.stringify([channel, event])
  if (active.has(key))
    return

  const current = active

  client.private(channel).listen(event, payload => {
    if (subscriptions.get(client) === current && current.has(key))
      listener(payload)
  })
  active.add(key)
}

export function clearRealtimeSubscriptions(client: RealtimeClient) {
  subscriptions.delete(client)
}
