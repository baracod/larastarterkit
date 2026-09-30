/**
 * This is an advanced example for creating icon bundles for Iconify SVG Framework.
 *
 * It creates a bundle from:
 * - All SVG files in a directory.
 * - Custom JSON files.
 * - Iconify icon sets.
 * - SVG framework.
 *
 * This example uses Iconify Tools to import and clean up icons.
 * For Iconify Tools documentation visit https://docs.iconify.design/tools/tools2/
 */
import { promises as fs } from 'node:fs'
import { createRequire } from 'node:module'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

// Installation: npm install --save-dev @iconify/tools @iconify/utils @iconify/json @iconify/iconify
import { cleanupSVG, importDirectory, isEmptyColor, parseColors, runSVGO } from '@iconify/tools'
import type { IconifyJSON } from '@iconify/types'
import { getIcons, getIconsCSS, stringToIcon } from '@iconify/utils'

const require = createRequire(import.meta.url)
const __filename = fileURLToPath(import.meta.url)
const __dirname = dirname(__filename)

/**
 * Script configuration
 */
interface BundleScriptCustomSVGConfig {

  // Path to SVG files
  dir: string

  // True if icons should be treated as monotone: colors replaced with currentColor
  monotone: boolean

  // Icon set prefix
  prefix: string
}

interface BundleScriptCustomJSONConfig {

  // Path to JSON file
  filename: string

  // List of icons to import. If missing, all icons will be imported
  icons?: string[]
}

interface BundleScriptConfig {

  // Custom SVG to import and bundle
  svg?: BundleScriptCustomSVGConfig[]

  // Icons to bundled from @iconify/json packages
  icons?: string[]

  // List of JSON files to bundled
  // Entry can be a string, pointing to filename or a BundleScriptCustomJSONConfig object (see type above)
  // If entry is a string or object without 'icons' property, an entire JSON file will be bundled
  json?: (string | BundleScriptCustomJSONConfig)[]
}

const sources: BundleScriptConfig = {

  svg: [
    // {
    //   dir: 'resources/images/iconify-svg',
    //   monotone: true,
    //   prefix: 'custom',
    // },

    // {
    //   dir: 'emojis',
    //   monotone: false,
    //   prefix: 'emoji',
    // },
  ],

  icons: [
    // 'mdi:home',
    // 'mdi:account',
    // 'mdi:login',
    // 'mdi:logout',
    // 'octicon:book-24',
    // 'octicon:code-square-24',
  ],

  json: [
    // Custom JSON file
    // 'json/gg.json',

    // Iconify JSON file (@iconify/json is a package name, /json/ is directory where files are, then filename)
    require.resolve('@iconify-json/mdi/icons.json'),
    require.resolve('@iconify-json/bx/icons.json'),
    {
      filename: require.resolve('@iconify-json/tabler/icons.json'),
      icons: [
        'adjustments-horizontal',
        'alert-circle',
        'alert-octagon',
        'alert-triangle',
        'archive',
        'archive-off',
        'arrow-back-up',
        'arrow-down',
        'arrow-forward-up',
        'arrow-up',
        'arrows-sort',
        'blockquote',
        'bold',
        'book',
        'book-2',
        'books',
        'brand-github',
        'bulb',
        'check',
        'checkbox',
        'chevron-down',
        'chevron-right',
        'chevron-up',
        'circle-check',
        'clock',
        'cloud-check',
        'cloud-upload',
        'code',
        'column-insert-right',
        'column-remove',
        'copy',
        'database',
        'device-floppy',
        'dots-vertical',
        'edit',
        'external-link',
        'eye',
        'file-off',
        'file-pencil',
        'file-plus',
        'file-text',
        'folder',
        'git-compare',
        'h-1',
        'h-2',
        'h-3',
        'h-4',
        'history',
        'home',
        'id',
        'indent-decrease',
        'indent-increase',
        'info-circle',
        'info-square-rounded',
        'italic',
        'layout-columns',
        'layout-dashboard',
        'layout-sidebar-left-collapse',
        'layout-sidebar-left-expand',
        'layout-sidebar-right-collapse',
        'layout-sidebar-right-expand',
        'link',
        'list',
        'list-check',
        'list-numbers',
        'list-tree',
        'loader-2',
        'markdown',
        'maximize',
        'menu-2',
        'minimize',
        'minus',
        'moon',
        'palette',
        'pencil',
        'photo',
        'photo-off',
        'photo-plus',
        'pilcrow',
        'plus',
        'point-filled',
        'refresh',
        'restore',
        'rocket',
        'row-insert-bottom',
        'row-insert-top',
        'row-remove',
        'schema',
        'search',
        'separator-horizontal',
        'settings',
        'source-code',
        'star',
        'star-filled',
        'strikethrough',
        'sun',
        'table',
        'table-plus',
        'terminal-2',
        'text-size',
        'trash',
        'typography',
        'unlink',
        'upload',
        'versions',
        'wand',
        'world',
        'writing',
      ],
    },
    {
      filename: require.resolve('@iconify-json/mdi/icons.json'),
      icons: [
        'close-circle',
        'language-javascript',
        'language-typescript',
        'translate',
        'arrow-bottom-left',
        'arrow-bottom-right',
        'arrow-top-left',
        'arrow-top-right',
        'arrow-collapse-all',
        'arrow-down-left',
        'file-remove-outline',
        'vuetify',
        'information-variant',
        'web',
        'cpu-32-bit',
        'alpha-r',
        'alpha-g',
        'alpha-b',
        'map-marker-off-outline',
        'laptop',
        'hub',
        'chevron-right',
        'chevron-left',
      ],
    },
    {
      filename: require.resolve('@iconify-json/fa/icons.json'),
      icons: [
        'circle',
      ],
    },
    {
      filename: require.resolve('@iconify-json/fluent/icons.json'),
      icons: [
        'lock-closed-key-24-regular',
      ],
    },

    // Custom file with only few icons
    // {
    //   filename: require.resolve('@iconify-json/line-md/icons.json'),
    //   icons: [
    //     'home-twotone-alt',
    //     'github',
    //     'document-list',
    //     'document-code',
    //     'image-twotone',
    //   ],
    // },
  ],
}

// File to save bundle to
const target = process.argv[2] || join(__dirname, 'icons.css')

  /**
   * Do stuff!
   */

  ; (async function () {
  // Create directory for output if missing
  const dir = dirname(target)
  try {
    await fs.mkdir(dir, {
      recursive: true,
    })
  }
  catch (err) {
    //
  }

  const allIcons: IconifyJSON[] = []

  /**
   * Convert sources.icons to sources.json
   */
  if (sources.icons) {
    const sourcesJSON = sources.json ? sources.json : (sources.json = [])

    // Sort icons by prefix
    const organizedList = organizeIconsList(sources.icons)

    for (const prefix in organizedList) {
      const filename = require.resolve(`@iconify/json/json/${prefix}.json`)

      sourcesJSON.push({
        filename,
        icons: organizedList[prefix],
      })
    }
  }

  /**
   * Bundle JSON files and collect icons
   */
  if (sources.json) {
    for (let i = 0; i < sources.json.length; i++) {
      const item = sources.json[i]

      // Load icon set
      const filename = typeof item === 'string' ? item : item.filename
      const content = JSON.parse(await fs.readFile(filename, 'utf8')) as IconifyJSON

      for (const key in content) {
        if (key === 'prefix' && content.prefix === 'tabler') {
          for (const k in content.icons)
            content.icons[k].body = content.icons[k].body.replace(/stroke-width="2"/g, 'stroke-width="1.5"')
        }
      }

      // Filter icons
      if (typeof item !== 'string' && item.icons?.length) {
        const filteredContent = getIcons(content, item.icons)

        if (!filteredContent)
          throw new Error(`Cannot find required icons in ${filename}`)

        // Collect filtered icons
        allIcons.push(filteredContent)
      }
      else {
        // Collect all icons from the JSON file
        allIcons.push(content)
      }
    }
  }

  /**
   * Bundle custom SVG icons and collect icons
   */
  if (sources.svg) {
    for (let i = 0; i < sources.svg.length; i++) {
      const source = sources.svg[i]

      // Import icons
      const iconSet = await importDirectory(source.dir, {
        prefix: source.prefix,
      })

      // Validate, clean up, fix palette, etc.
      await iconSet.forEach(async (name, type) => {
        if (type !== 'icon')
          return

        // Get SVG instance for parsing
        const svg = iconSet.toSVG(name)

        if (!svg) {
          // Invalid icon
          iconSet.remove(name)

          return
        }

        // Clean up and optimise icons
        try {
          // Clean up icon code
          await cleanupSVG(svg)

          if (source.monotone) {
            // Replace color with currentColor, add if missing
            // If icon is not monotone, remove this code
            await parseColors(svg, {
              defaultColor: 'currentColor',
              callback: (attr, colorStr, color) => {
                return !color || isEmptyColor(color) ? colorStr : 'currentColor'
              },
            })
          }

          // Optimise
          await runSVGO(svg)
        }
        catch (err) {
          // Invalid icon
          console.error(`Error parsing ${name} from ${source.dir}:`, err)
          iconSet.remove(name)

          return
        }

        // Update icon from SVG instance
        iconSet.fromSVG(name, svg)
      })

      // Collect the SVG icon
      allIcons.push(iconSet.export())
    }
  }

  // Generate CSS from collected icons
  const cssContent = allIcons
    .map(iconSet => getIconsCSS(
      iconSet,
      Object.keys(iconSet.icons),
      {
        iconSelector: '.{prefix}-{name}',
        mode: 'mask',
      },
    ))
    .join('\n')

  // Save the CSS to a file
  await fs.writeFile(target, cssContent, 'utf8')

  console.log(`Saved CSS to ${target}!`)
})().catch(err => {
  console.error(err)
  process.exitCode = 1
})

/**
 * Sort icon names by prefix
 */
function organizeIconsList(icons: string[]): Record<string, string[]> {
  const sorted: Record<string, string[]> = Object.create(null)

  icons.forEach(icon => {
    const item = stringToIcon(icon)

    if (!item)
      return

    const prefix = item.prefix
    const prefixList = sorted[prefix] ? sorted[prefix] : (sorted[prefix] = [])

    const name = item.name

    if (!prefixList.includes(name))
      prefixList.push(name)
  })

  return sorted
}
