export interface INotification {
  id: number
  user_id: number
  type: 'info' | 'success' | 'warning' | 'error'
  title: string
  message: string | null
  data: Record<string, any> | null
  read_at: string | null
  created_at: string
  updated_at: string
  sender?: {
    id: number
    name: string
    avatar: string
  } | null
}

export interface IModule {
  name: string
  enabled: boolean
  is_primary: boolean
  description: string
}

export interface IModuleConfig {
  id: number
  module_name: string
  key: string
  value: any
  type: string
  description: string | null
  created_at: string
  updated_at: string
}

export interface IAdminSetting {
  id?: number | null
  type: 'system' | 'module' | 'user'
  module?: string | null
  user_id?: number | null
  key?: string | null
  value?: string | null
  value_type: string
  label?: string | null
  description?: string | null
  input_type: string
  options?: string | null
  default_value?: string | null
  is_public: number
}

export interface ISetting {
  id: number
  type: 'system' | 'module' | 'user'
  module: string | null
  userId: number | null
  key: string
  value: any
  valueType: string
  label: string | null
  description: string | null
  inputType: string
  options: Record<string, any> | null
  defaultValue: any | null
  isPublic: boolean
  createdAt: string
  updatedAt: string
}

export interface IPaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}
