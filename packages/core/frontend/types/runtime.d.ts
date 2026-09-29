// Keep global properties visible to Vue 3.5 and vue-tsc 2 in packaged SFCs.
import type { Vuetify } from 'vue'
import type { ability } from '../resources/ts/plugins/casl/ability'
import type { paginationMeta } from '../resources/ts/utils/paginationMeta'
import '@vue/runtime-core'

declare module '@vue/runtime-core' {
  interface ComponentCustomProperties {
    $vuetify: Vuetify
    $ability: typeof ability
    $can: typeof ability.can
    paginationMeta: typeof paginationMeta
  }
}
