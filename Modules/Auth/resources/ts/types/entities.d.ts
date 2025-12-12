export interface IUser {
  id: number;
  name: string;
  username?: string | null;
  email: string;
  additional_info?: string | null;
  avatar?: string | null;
  email_verified_at?: string | null;
  password: string;
  remember_token?: string | null;
  active?: number | null;
  created_at?: string | null;
  updated_at?: string | null;
  role_names?: Array<string>;
  roles?: Array<IRole> | null;
}

export type IBioEditable = {
  id?: number | string
  name?: string
  username?: string
  email?: string
  active?: 0 | 1 | boolean              // tolère boolean ou 0/1
  avatar?: string | null                // URL actuelle (peut être null si absent)
  avatarFile?: File | null              // fichier choisi côté front
}

export interface IRole {
  id: number;
  name: string;
  display_name: string;
  description?: string | null;
  order?: number | null;
  is_owner?: number | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface IPermission {
  id: number;
  key: string;
  action?: string | null;
  subject?: string | null;
  description?: string | null;
  table_name?: string | null;
  always_allow: number;
  is_public: number;
  created_at?: string | null;
  updated_at?: string | null;
}
