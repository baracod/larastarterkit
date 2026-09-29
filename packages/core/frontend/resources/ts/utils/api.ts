import { ofetch } from 'ofetch'

import { csrfToken } from './csrf'

export const $api = ofetch.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  credentials: 'include',
  async onRequest({ options }) {
    const token = csrfToken()

    options.headers = {
      ...options.headers,
      ...(token ? { 'X-XSRF-TOKEN': token } : {}),
      Accept: 'application/json',
    }
  },
  async onResponseError({ response }) {
    if (response.status === 401)
      window.dispatchEvent(new Event('api:unauthorized'))
    else if (response.status === 403)
      window.dispatchEvent(new Event('api:forbidden'))
    else if (response.status === 423)
      window.dispatchEvent(new Event('api:suspended'))
  },
})
