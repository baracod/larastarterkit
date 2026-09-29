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
