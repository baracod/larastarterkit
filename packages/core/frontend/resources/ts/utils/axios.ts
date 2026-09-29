import { ofetch } from 'ofetch'

import { csrfToken } from './csrf'

export const $axios = ofetch.create({
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
})
