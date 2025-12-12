import { IPermission, IRole, IUser } from '@auth/types/entities'
export interface IAbilityRuler {
  id?: number
  action: string
  subject: string
}

export interface IHttpResponse<T = any> {
  success: boolean
  message: string,
  data: T | null
  errors?: Record<string, any>
}

export interface ILoginResponse {
  user: IUser
  accessToken: string
  tokenType: string
  permissions?: IPermission[],
  roles: IRole[]
  abilityRules?: IAbilityRuler[]
}
