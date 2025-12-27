import { ofetch } from 'ofetch'

// src/api/config.ts
function normalize(url: string) {
  return url.replace(/\/+$/, '')
}
const envBaseURL = import.meta.env.VITE_API_BASE_URL

const baseURL
  = (envBaseURL && envBaseURL.trim() !== '')
    ? normalize(envBaseURL)
    : `${normalize(window.location.origin)}/api/v1`

export const $api = ofetch.create({
  baseURL,
  async onRequest({ options }) {
    const accessToken = useCookie('accessToken').value
    if (accessToken) {
      options.headers = {
        ...options.headers,
        Authorization: `Bearer ${accessToken}`,
        accept: 'application/json',
      }
    }
  },
  async onResponseError({ response }) {
    if (response.status === 401)
      window.dispatchEvent(new Event('api:unauthorized'))

    else if (response.status === 423)
      window.dispatchEvent(new Event('api:suspended'))
  },
})
