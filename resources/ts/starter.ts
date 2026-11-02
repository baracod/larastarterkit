// Application-owned extension points. Never overwritten by starter upgrades.
// Available keys: menus, catalog, slots, redirects, pages, configure (see the documentation, "Points d'extension").
import { defineStarter } from '@/extension'

export default defineStarter({ menus: {}, catalog: [], slots: {}, configure: () => {} })
