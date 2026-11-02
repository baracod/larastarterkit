/// <reference path="./runtime.d.ts" />
declare module 'virtual:larastarterkit' {
  export const applicationExtension: import('../resources/ts/extension/define').StarterExtension
  export const moduleMenus: Record<string, any[]>
  export const moduleMessages: [string, string, Record<string, any>][]
  export const moduleCatalog: import('../resources/ts/types/module').Module[]
  export const initialModuleStatuses: Record<string, boolean>
  export const navbarComponents: Record<string, () => Promise<{ default: import('vue').Component }>>
}
