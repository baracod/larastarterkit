import { createMongoAbility } from '@casl/ability'

export type Actions = string

// ex: Post, Comment, User, etc. We haven't used any of these in our demo though.
export type Subjects = string

export interface Rule { action: Actions; subject: Subjects }

export const ability = createMongoAbility<[Actions, Subjects]>()
