import { createFetch } from '@vueuse/core'
import { destr } from 'destr'

import { csrfToken } from '@/utils/csrf'

const emit = (name: string, detail?: any) =>
  window.dispatchEvent(new CustomEvent(name, { detail }))

export const useApi = createFetch({
  baseUrl: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  fetchOptions: {
    credentials: 'include',
    headers: {
      Accept: 'application/json',
    },
  },
  options: {
    refetch: true,
    async beforeFetch({ options }) {
      const token = csrfToken()

      if (token) {
        options.headers = {
          ...options.headers,
          'X-XSRF-TOKEN': token,
        }
      }

      return { options }
    },
    afterFetch(ctx) {
      const { data, response } = ctx

      // Parse data if it's JSON
      let parsedData = null
      try {
        parsedData = destr(data)
      }
      catch (error) {
        console.error(error)
      }

      return { data: parsedData, response }
    },
    onFetchError(ctx) {
      const { response } = ctx

      if (response && new URL(response.url, window.location.origin).pathname.endsWith('/auth/logout'))
        return ctx

      // Network error ou fetch abort, etc.
      console.error(ctx)

      if (response?.status === 401)
        emit('api:unauthorized', { status: 401 })
      else if (response?.status === 403)
        emit('api:forbidden', { status: 403 })
      else if (response?.status === 423)
        emit('api:suspended', { status: 423 })

      return ctx
    },
  },
})
