import Echo from 'laravel-echo'
import type { EchoOptions } from 'laravel-echo'
import Pusher from 'pusher-js'
import { ofetch } from 'ofetch'
import { clearRealtimeSubscriptions } from './realtimeSubscriptions'
import { csrfToken } from './csrf'

export function startRealtime() {
  if (window.Echo)
    return
  const element = document.getElementById('starter-broadcast-config')
  const config = JSON.parse(element?.textContent || '{}')
  if (!['pusher', 'reverb'].includes(config.driver) || !config.key)
    return

  const options: EchoOptions<'pusher'> = {
    broadcaster: 'pusher',
    Pusher,
    key: config.key,
    cluster: config.cluster || 'mt1',
    ...(config.driver === 'reverb' ? { wsHost: config.host, wsPort: Number(config.port), wssPort: Number(config.port) } : {}),
    forceTLS: config.driver === 'reverb' ? config.scheme === 'https' : true,
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel: { name: string }) => ({
      authorize: async (socketId, callback) => {
        const token = csrfToken()

        let data
        try {
          data = await ofetch('/api/broadcasting/auth', {
            method: 'POST',
            credentials: 'include',
            headers: { Accept: 'application/json', ...(token ? { 'X-XSRF-TOKEN': token } : {}) },
            body: { socket_id: socketId, channel_name: channel.name },
          })
        }
        catch {
          callback(new Error('Channel authorization failed'), null)

          return
        }
        callback(null, data)
      },
    }),
  }

  window.Echo = config.driver === 'reverb'
    ? new Echo({ ...options, broadcaster: 'reverb' })
    : new Echo(options)
}

export function stopRealtime() {
  if (window.Echo) {
    clearRealtimeSubscriptions(window.Echo)
    window.Echo.disconnect()
  }
  window.Echo = undefined
}
