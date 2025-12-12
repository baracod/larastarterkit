// auth import de useApi() qui est une instance de  { createFetch } from '@vueuse/core'
import type { IHttpResponse, ILoginResponse } from '@auth/types/auth';

const baseUrl = 'auth'
export const AuthAPI = {
  // POST /auth/login
  async login(payload: { email: string; password: string }): Promise<IHttpResponse<ILoginResponse>> {
    const { data, error } = await useApi(`${baseUrl}/login`)
      .post(payload)
      .json<IHttpResponse<ILoginResponse>>()

    if (error.value)
      throw error.value

    // data est un Ref<ILoginResponse | undefined>
    if (!data.value)
      throw new Error('Réponse vide du serveur')

    return data.value
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
  async forgottenPassword(payload: { email: string }): Promise<IHttpResponse | null> {
    const { data, error } = await useApi(`${baseUrl}/forgotten-password`)
      .post(payload)
      .json<IHttpResponse>()

    if (error.value)
      throw error.value
    if (!data.value)
      throw new Error('Réponse vide du serveur')

    return data.value
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
    payload: { newPassword: string; newPasswordConfirmation: string; token: string; email: string },
  ): Promise<IHttpResponse | null> {
    return await $api(`${baseUrl}/reset-password`, { method: 'PATCH', body: payload })
  },

}

export default AuthAPI
