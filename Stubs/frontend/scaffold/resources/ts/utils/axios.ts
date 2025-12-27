import { ofetch } from 'ofetch'

function normalize(url: string) {
  return url.replace(/\/+$/, '')
}

export const baseURL
  = (import.meta.env.VITE_API_BASE_URL && import.meta.env.VITE_API_BASE_URL.trim() !== '')
    ? normalize(import.meta.env.VITE_API_BASE_URL)
    : `${normalize(window.location.origin)}/api/v1`

export const $axios = ofetch.create({
  baseURL: baseURL || 'api/v1',
  async onRequest({ options }) {
    const accessToken = useCookie('accessToken').value
    if (accessToken) {
      options.headers = {
        ...options.headers,
        Authorization: `Bearer ${accessToken}`,
      }
    }
  },
})
