import type { IBioEditable, IUser } from '../types/entities'

const parseMaybeJSON = (s?: string | null) => {
  if (!s || !s.trim())
    return undefined
  try {
    return JSON.parse(s)
  }
  catch {
    return s
  }
}

export const userToBioEditable = (u: IUser): IBioEditable => ({
  id: u.id,
  name: u.name,
  username: u.username ?? undefined,
  email: u.email,
  active: u.active == null ? undefined : u.active ? 1 : 0,
  avatar: u.avatar ?? null,
  additional_info: parseMaybeJSON(u.additional_info),
  avatarFile: null,
})
