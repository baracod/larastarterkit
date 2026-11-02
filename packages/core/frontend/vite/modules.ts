import fs from 'node:fs'
import path from 'node:path'
import { execFileSync } from 'node:child_process'

export function installedModules(root = process.cwd()) {
  const data = JSON.parse(execFileSync('php', ['artisan', 'larastarterkit:modules', '--json', '--no-interaction'], { cwd: root, encoding: 'utf8' }))

  const modules = Object.entries(data.modules).map(([name, metadata]: [string, any]) => {
    const source = path.resolve(metadata.path, metadata.frontend?.path ?? 'resources/ts')
    if (!source.startsWith(`${path.resolve(metadata.path)}${path.sep}`))
      throw new Error(`Invalid frontend path for ${name}`)

    return { name, source, metadata }
  })

  return { modules, statuses: data.statuses }
}

// Two page files resolving to the same URL silently overwrite each other in unplugin-vue-router.
export function pageCollisions(routesFolder: Array<{ src: string; path: string }>) {
  const owners = new Map<string, string[]>()
  for (const folder of routesFolder) {
    if (!fs.existsSync(folder.src))
      continue
    for (const file of fs.readdirSync(folder.src, { recursive: true }) as string[]) {
      if (!file.endsWith('.vue'))
        continue

      // Same URL whatever the param names ([id] / [userId]) and route groups ((public)).
      const route = `${folder.path}${file.slice(0, -4).replace(/\\/g, '/')}`
        .split('/')
        .filter(segment => !/^\(.+\)$/.test(segment))
        .map(segment => segment.replace(/\[\.\.\.[^\]]+\]/g, '*').replace(/\[[^\]]+\]/g, ':param'))
        .join('/')
        .replace(/(?:^|\/)index$/, '')
        .replace(/\/$/, '')

      owners.set(route, [...(owners.get(route) ?? []), path.join(folder.src, file)])
    }
  }

  return [...owners].filter(([, files]) => files.length > 1).map(([route, files]) => ({ route: `/${route}`, files }))
}

export function pageCollisionPlugin(routesFolder: Array<{ src: string; path: string }>, strict: boolean) {
  return {
    name: 'baracod-page-collisions',
    buildStart(this: any) {
      for (const { route, files } of pageCollisions(routesFolder)) {
        const message = `Page ${route} is defined by ${files.join(' and ')}. Declare the override in resources/ts/starter.ts (pages) with a component outside resources/ts/pages.`
        if (strict)
          this.error(message)
        else
          this.warn(message)
      }
    },
  }
}

// resources/ts/overrides/<root>/<path> of the application replaces <path> of the core (root "core") or of a module
// (root = lowercase module name). Returns the original → override map and the overrides that match no original file.
export function fileOverrides(overridesDir: string, roots: Array<{ name: string; source: string }>) {
  const map = new Map<string, string>()
  const unknown: string[] = []

  // A module whose lowercase name is "core" (or two modules with the same lowercase name) cannot be targeted unambiguously.
  const ambiguous = [...new Set(roots.map(root => root.name).filter((name, index, names) => names.indexOf(name) !== index))]
  if (!fs.existsSync(overridesDir))
    return { map, unknown, ambiguous }

  for (const file of fs.readdirSync(overridesDir, { recursive: true }) as string[]) {
    const override = path.join(overridesDir, file)
    const [rootName, ...rest] = file.split(/[\\/]/)
    const root = ambiguous.includes(rootName) ? undefined : roots.find(candidate => candidate.name === rootName)
    const original = root ? path.join(root.source, ...rest) : undefined

    try {
      if (!fs.statSync(override).isFile())
        continue
      if (original && fs.existsSync(original))
        map.set(fs.realpathSync(original), fs.realpathSync(override))
      else
        unknown.push(override)
    }
    catch {
      // Dangling symlink or file removed during the scan.
      unknown.push(override)
    }
  }

  return { map, unknown, ambiguous }
}

export function fileOverridePlugin(overridesDir: string, roots: Array<{ name: string; source: string }>) {
  let overrides = fileOverrides(overridesDir, roots)

  return {
    name: 'baracod-file-overrides',
    enforce: 'pre' as const,
    buildStart(this: any) {
      overrides = fileOverrides(overridesDir, roots)
      for (const file of overrides.unknown)
        this.warn(`Override ${file} matches no file of the core or of a module.`)
      for (const name of overrides.ambiguous)
        this.warn(`Override root "${name}" is shared by the core and/or several modules: files under resources/ts/overrides/${name} are ignored.`)
    },
    configureServer(server: any) {
      // Adding or removing an override changes how imports resolve: restart instead of serving stale modules.
      server.watcher.add(overridesDir)

      const restart = (file: string) => {
        if (path.resolve(file).startsWith(`${path.resolve(overridesDir)}${path.sep}`))
          server.restart()
      }

      server.watcher.on('add', restart)
      server.watcher.on('unlink', restart)
    },
    async resolveId(this: any, source: string, importer: string | undefined, options: any) {
      // Bare package imports (vue, vuetify/…) and virtual modules never point to an overridable file.
      const bare = /^[\w-]/.test(source) && !/^[a-z]:[\\/]/i.test(source)
      if (!overrides.map.size || !importer || bare || source.startsWith('\0') || source.startsWith('virtual:') || importer.includes('/node_modules/'))
        return null
      const resolved = await this.resolve(source, importer, { ...options, skipSelf: true })
      if (!resolved || resolved.external)
        return null
      const [file, query] = resolved.id.split('?')
      const override = overrides.map.get(file)
      const importerFile = importer.split('?')[0]

      // The override itself keeps access to the original, so that it can wrap or extend it;
      // an SFC's own blocks (?vue&type=style…) always belong to that SFC.
      if (!override || importerFile === override || importerFile === file || query?.startsWith('vue'))
        return null

      return query ? `${override}?${query}` : override
    },
  }
}

export function modulePlugin(data: ReturnType<typeof installedModules>) {
  return {
    name: 'baracod-module-registry',
    generateBundle(this: any) {
      const fingerprint = execFileSync('php', ['artisan', 'larastarterkit:frontend', '--fingerprint', '--no-interaction'], { encoding: 'utf8' }).trim()

      this.emitFile({ type: 'asset', fileName: 'larastarterkit.json', source: JSON.stringify({ fingerprint }) })
    },
    resolveId(id: string) { return id === 'virtual:larastarterkit' ? `\0${id}` : undefined },
    load(id: string) {
      if (id !== '\0virtual:larastarterkit')
        return
      const custom = path.resolve('resources/ts/starter.ts')
      const imports: string[] = [fs.existsSync(custom) ? `import extension from ${JSON.stringify(custom)}` : 'const extension = {}']
      const menus: string[] = []
      const messages: string[] = []
      const navbars: string[] = []
      const catalog: any[] = []
      for (const [index, module] of data.modules.entries()) {
        const menu = path.join(module.source, 'menuItems.json')
        if (fs.existsSync(menu)) {
          imports.push(`import menu${index} from ${JSON.stringify(menu)}`)
          menus.push(`${JSON.stringify(module.name.toLowerCase())}: menu${index}`)
        }
        const locales = path.join(module.source, 'locales')
        if (fs.existsSync(locales)) {
          for (const [j, file] of fs.readdirSync(locales).filter(f => f.endsWith('.json')).entries()) {
            imports.push(`import msg${index}_${j} from ${JSON.stringify(path.join(locales, file))}`)
            messages.push(`[${JSON.stringify(module.name)}, ${JSON.stringify(file.slice(0, -5))}, msg${index}_${j}]`)
          }
        }
        const navbar = path.join(module.source, 'components/ModuleNavbar.vue')
        if (fs.existsSync(navbar))
          navbars.push(`${JSON.stringify(module.name.toLowerCase())}: () => import(${JSON.stringify(navbar)})`)
        catalog.push(module.metadata.navigation ?? { title: module.name, module: module.name, icon: 'mdi-puzzle-outline', action: 'access', subject: module.name.toLowerCase(), to: { name: module.name.toLowerCase() } })
      }

      return `${imports.join('\n')}\nexport const moduleMenus = {...{${menus.join(',')}}, ...extension.menus};\nexport const moduleMessages = [${messages.join(',')}];\nexport const moduleCatalog = [...${JSON.stringify(catalog)}, ...(extension.catalog ?? [])];\nexport const initialModuleStatuses = ${JSON.stringify(data.statuses)};\nexport const navbarComponents = {${navbars.join(',')}};\nexport const applicationExtension = extension;`
    },
  }
}
