import { ofetch } from 'ofetch'
import { csrfToken, initializeCsrf } from '@/utils/csrf'

// auth import de useApi() qui est une instance de  { createFetch } from '@vueuse/core'
import type { IHttpResponse, ILoginResponse } from '@auth/types/auth'

const baseUrl = 'auth'

// Authentication failures are rendered by the form, not by session-expiry handlers.
const publicAuthRequest = ofetch.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  credentials: 'include',
  retry: 0,
  async onRequest({ options }) {
    await initializeCsrf()
    options.headers = { ...options.headers, 'Accept': 'application/json', 'X-XSRF-TOKEN': csrfToken() ?? '' }
  },
})

export const AuthAPI = {
  // POST /auth/login
  async login(payload: { email: string; password: string; remember_me?: boolean; email_locale?: 'fr' | 'en' }): Promise<IHttpResponse<ILoginResponse>> {
    return publicAuthRequest(`${baseUrl}/login`, { method: 'POST', body: payload })
  },

  // POST /auth/logout   (pas d’id côté Sanctum en général)
  async logout(): Promise<IHttpResponse | null> {
    const { data, error } = await useApi(`${baseUrl}/logout`)
      .post()
      .json<IHttpResponse>()

    if (error.value)
      throw error.value

    return data.value ?? null
  },

  // POST /auth/forgotten-password
  async forgottenPassword(payload: { email: string; email_locale?: 'fr' | 'en' }): Promise<IHttpResponse | null> {
    return publicAuthRequest(`${baseUrl}/forgotten-password`, { method: 'POST', body: payload })
  },

  // PUT /auth/reset-password/:id   (si ton backend attend un id dans l’URL)
  async validateCodeResetPassword(
    id: number | string,
    payload: number | { code: string; newPassword: string },
  ): Promise<IHttpResponse | null> {
    const { data, error } = await useApi(`${baseUrl}/reset-password/${id}`)
      .put(payload)
      .json<IHttpResponse>()

    if (error.value)
      throw error.value
    if (!data.value)
      throw new Error('Réponse vide du serveur')

    return data.value
  },

  async validateCodeResetPasswordByToken(
    payload: { newPassword: string; newPasswordConfirmation: string; emailLocale?: 'fr' | 'en'; token: string; email: string },
  ): Promise<IHttpResponse | null> {
    return publicAuthRequest(`${baseUrl}/reset-password`, {
      method: 'PATCH',
      body: { email_locale: payload.emailLocale, token: payload.token, email: payload.email, new_password: payload.newPassword, new_password_confirmation: payload.newPasswordConfirmation },
    })
  },

}

export default AuthAPI
