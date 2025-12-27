// src/plugins/api-events.ts

import { useAuthStore } from '@auth/stores'

export default function () {
  window.addEventListener('api:unauthorized', async () => {
    console.log('Unauthorized - logging out')

    const { unauthorized, unauthorizedRequest, logout } = useAuthStore()

    unauthorizedRequest()
    logout()

    console.log('Unauthorized - action à définir', unauthorized)
  })

  window.addEventListener('api:suspended', () => {
    // Ex: ouvrir un dialog “compte suspendu”
    // showSuspendDialog()
    const { unauthorized, unauthorizedRequest } = useAuthStore()

    unauthorizedRequest()
    console.log('Compte suspendu - action à définir', unauthorized)
  })
}
