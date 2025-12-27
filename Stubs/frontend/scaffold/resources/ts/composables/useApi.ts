import { createFetch } from '@vueuse/core'
import { destr } from 'destr'

function normalize(url: string) {
  return url.replace(/\/+$/, '')
}

const baseURL
  = (import.meta.env.VITE_API_BASE_URL && import.meta.env.VITE_API_BASE_URL.trim() !== '')
    ? normalize(import.meta.env.VITE_API_BASE_URL)
    : `${normalize(window.location.origin)}/api/v1`

const emit = (name: string, detail?: any) =>
  window.dispatchEvent(new CustomEvent(name, { detail }))

export const useApi = createFetch({
  baseUrl: baseURL,
  fetchOptions: {
    headers: {
      Accept: 'application/json',
    },
  },
  options: {
    refetch: true,
    async beforeFetch({ options }) {
      const accessToken = useCookie('accessToken').value

      if (accessToken) {
        options.headers = {
          ...options.headers,
          Authorization: `Bearer ${accessToken}`,
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
        console.log('Response status: omba', ctx)

        if (response.status === 423) {
          const isSuspendUser = useState('isSuspendUser', () => true)
        }
        console.log(response.status)
        console.error(error)
      }

      return { data: parsedData, response }
    },
    onFetchError(ctx) {
      const { response } = ctx

      // Network error ou fetch abort, etc.
      console.error(ctx)

      if (response?.status === 401)
        emit('api:unauthorized', { status: 401 })
      if (response?.status === 423)
        emit('api:suspended', { status: 423 })

      return ctx
    },
  },
})
