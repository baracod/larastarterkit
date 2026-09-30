// Builtins d'abord (règle import/order)
import { fileURLToPath } from 'node:url'
import path from 'node:path'

import VueI18nPlugin from '@intlify/unplugin-vue-i18n/vite'
import vue from '@vitejs/plugin-vue'
import vueJsx from '@vitejs/plugin-vue-jsx'
import laravel from 'laravel-vite-plugin'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { VueRouterAutoImports, getPascalCaseRouteName } from 'unplugin-vue-router'
import VueRouter from 'unplugin-vue-router/vite'
import { defineConfig } from 'vite'
import Layouts from 'vite-plugin-vue-layouts'
import vuetify from 'vite-plugin-vuetify'
import svgLoader from 'vite-svg-loader'

import { iconsPlugin } from './icons'
import { installedModules, modulePlugin } from './modules'

const uiRoot = fileURLToPath(new URL('../', import.meta.url))
const ui = (relative: string) => path.join(uiRoot, relative)

// Utils
const toKebab = (s: string) => s.replace(/([a-z\d])([A-Z])/g, '$1-$2').toLowerCase()

const makeRouteName
  = (allRoutesFolder: Array<{ src: string; path: string }>) =>

    // ⚠️ Pas d’annotation ici → le type sera inféré par le plugin (TreeNode)
    (node: any) => {
      const filePath = (node as any)?.filePath as string | undefined
      const normalizedPath = filePath?.replace(/\\/g, '/')

      const matchedFolder = allRoutesFolder.find(folder =>
        normalizedPath?.startsWith(folder.src),
      )

      // getPascalCaseRouteName attend le même nœud → cast local
      const kebabName = toKebab(getPascalCaseRouteName(node as any))
      const prefix = matchedFolder?.path.replace(/\/$/, '')

      return prefix ? `${prefix}-${kebabName}` : kebabName
    }

export default defineConfig(async () => {
  const registry = installedModules()
  const routesFolder = [{ src: ui('resources/ts/pages'), path: '' }, ...registry.modules.map(m => ({ src: path.join(m.source, 'pages'), path: `${m.name.toLowerCase()}/` })), ...(process.cwd() === path.resolve(uiRoot, '../../..') ? [] : [{ src: path.resolve('resources/ts/pages'), path: '' }])]

  const inputEntries = ['resources/ts/main.ts']

  const componentsDirs = [
    ui('resources/ts/@core/components'),
    ui('resources/ts/layouts/*'),
    ui('resources/ts/components'),
    ...registry.modules.map(m => path.join(m.source, 'components')),
  ]

  const autoImportDirs = [
    ui('./resources/ts/@core/utils'),
    ui('./resources/ts/@core/composable/'),
    ui('./resources/ts/composables/**'),
    ui('./resources/ts/utils/'),
    ui('./resources/ts/plugins/*/composables/*'),
    ui('modules/menuItems.*'),
    ...registry.modules.map(m => path.join(m.source, 'composable/**')),
  ]

  const moduleAlias = Object.fromEntries(registry.modules.map(m => [`@${m.name.toLowerCase()}`, m.source]))

  return {
    plugins: [
      modulePlugin(registry),
      iconsPlugin(ui('resources/ts/plugins/iconify/build-icons.ts')),

      // 👉 Toujours avant `vue`
      VueRouter({
        getRouteName: makeRouteName(routesFolder),
        routesFolder,
      }),

      vue({
        template: {
          compilerOptions: {
            isCustomElement: tag =>
              tag === 'swiper-container' || tag === 'swiper-slide',
          },
          transformAssetUrls: { base: null, includeAbsolute: false },
        },
      }),

      vueJsx(),

      VueI18nPlugin({
        runtimeOnly: true,
        compositionOnly: true,
        include: [
          ui('./resources/ts/plugins/i18n/locales/**'),
          ...registry.modules.map(m => path.join(m.source, 'locales/*.json')),

          // ui('./Modules/*/resources/ts/locales/*'),
        ],
      }),

      Layouts({ layoutsDirs: [ui('resources/ts/layouts/'), 'resources/ts/layouts'] }),

      AutoImport({
        eslintrc: { enabled: true, filepath: './.eslintrc-auto-import.json' },
        imports: ['vue', VueRouterAutoImports, '@vueuse/core', '@vueuse/math', 'vue-i18n', 'pinia'],
        dirs: autoImportDirs,
        vueTemplate: true,
        ignore: ['useCookies'],
      }),

      Components({
        dirs: componentsDirs,
        dts: true,
        resolvers: [
          name => (name === 'VueApexCharts'
            ? { name: 'default', from: 'vue3-apexcharts', as: 'VueApexCharts' }
            : undefined),
        ],
      }),

      vuetify({
        autoImport: { labs: true },
        styles: { configFile: ui('resources/styles/variables/_vuetify.scss') },
      }),
      svgLoader(),

      laravel({
        input: inputEntries,
        refresh: true,
      }),
    ],

    define: { 'process.env': {} },

    resolve: {
      dedupe: ['vue', 'vue-router', 'pinia', 'vuetify', 'vue-i18n'],
      alias: {
        '@core-scss': ui('./resources/styles/@core'),
        '@': ui('./resources/ts'),
        '@themeConfig': ui('./themeConfig.ts'),
        '@core': ui('./resources/ts/@core'),
        '@layouts': ui('./resources/ts/@layouts'),
        '@images': ui('./resources/images/'),
        '@styles': ui('./resources/styles/'),
        '@configured-variables': fileURLToPath(
          new URL('../resources/styles/variables/_template.scss', import.meta.url),
        ),
        '@app': path.resolve('resources/ts'),
        ...moduleAlias,
      },
    },

    build: {
      chunkSizeWarningLimit: 5000,
      commonjsOptions: {
        esmExternals: true,
      },
    },

    optimizeDeps: {
      exclude: ['vuetify'],
      entries: ['./resources/ts/main.ts'],
    },

    server: {
      host: '0.0.0.0', // Indispensable pour Docker
      port: 5173,
      warmup: {
        clientFiles: ['./resources/ts/main.ts'],
      },
      hmr: {
        host: 'localhost', // Pour que ton navigateur trouve le HMR
      },
      watch: {
        usePolling: true, // Nécessaire sur Windows/WSL/Docker parfois
      },
    },
  }
})
