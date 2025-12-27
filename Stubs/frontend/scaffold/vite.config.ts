// Builtins d'abord (règle import/order)
import { fileURLToPath } from 'node:url'

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

import { getModuleAlias, moduleRoutesFolder } from './vite-module-loader'

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

export default defineConfig(async ({ mode }) => {
  const isDev = mode === 'development'
  const routesFolder = await moduleRoutesFolder('Modules')

  const inputEntries = ['resources/ts/main.ts']

  const componentsDirs = [
    'resources/ts/@core/components',
    'resources/ts/layouts/*',
    'resources/ts/components',
    'Modules/*/resources/ts/components',
  ]

  const autoImportDirs = [
    './resources/ts/@core/utils',
    './resources/ts/@core/composable/',
    './resources/ts/composables/**',
    './resources/ts/utils/',
    './resources/ts/plugins/',
    './resources/ts/plugins/*/composables/*',
    './Modules/menuItems.*',
    './Modules/Auth/resources/ts/composable/**',
    '@modules/*.ts',
  ]

  const moduleAlias = await getModuleAlias('Modules')

  console.log(moduleAlias)

  return {
    plugins: [
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
          fileURLToPath(new URL('./resources/ts/plugins/i18n/locales/**', import.meta.url)),
          fileURLToPath(new URL('./Modules/*/resources/ts/locales/*.json', import.meta.url)),

          // fileURLToPath(new URL('./Modules/*/resources/ts/locales/*', import.meta.url)),
        ],
      }),

      Layouts({ layoutsDirs: './resources/ts/layouts/' }),

      AutoImport({
        eslintrc: { enabled: isDev, filepath: './.eslintrc-auto-import.json' },
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
        styles: { configFile: 'resources/styles/variables/_vuetify.scss' },
      }),
      svgLoader(),

      laravel({
        input: inputEntries,
        refresh: true,
      }),
    ],

    define: { 'process.env': {} },

    resolve: {
      alias: {
        '@core-scss': fileURLToPath(new URL('./resources/styles/@core', import.meta.url)),
        '@': fileURLToPath(new URL('./resources/ts', import.meta.url)),
        '@themeConfig': fileURLToPath(new URL('./themeConfig.ts', import.meta.url)),
        '@core': fileURLToPath(new URL('./resources/ts/@core', import.meta.url)),
        '@layouts': fileURLToPath(new URL('./resources/ts/@layouts', import.meta.url)),
        '@images': fileURLToPath(new URL('./resources/images/', import.meta.url)),
        '@styles': fileURLToPath(new URL('./resources/styles/', import.meta.url)),
        '@configured-variables': fileURLToPath(
          new URL('./resources/styles/variables/_template.scss', import.meta.url),
        ),
        '@modules': fileURLToPath(new URL('./Modules', import.meta.url)),
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
      entries: ['./resources/ts/**/*.vue', './Modules/*/resources/ts/**/*.vue'],
    },

    // server: { host: '0.0.0.0', port: 5173, strictPort: true, hmr: { host: 'localhost', protocol: 'ws', port: 5173 } },
  }
})
