import fs from 'node:fs/promises'
import path, { dirname } from 'node:path'

import { fileURLToPath } from 'node:url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = dirname(__filename)

async function moduleAssetsPaths(paths: any, modulesPath: any) {
  modulesPath = path.join(__dirname, modulesPath)

  const moduleStatusesPath = path.join(__dirname, 'modules_statuses.json')

  try {
    // Read module_statuses.json
    const moduleStatusesContent = await fs.readFile(moduleStatusesPath, 'utf-8')
    const moduleStatuses = JSON.parse(moduleStatusesContent)

    // Read module directories
    const moduleDirectories = await fs.readdir(modulesPath)

    for (const moduleDir of moduleDirectories) {
      if (moduleDir === '.DS_Store') {
        // Skip .DS_Store directory
        continue
      }

      // Check if the module is enabled (status is true)
      if (moduleStatuses[moduleDir] === true) {
        const viteConfigPath = path.join(modulesPath, moduleDir, 'vite.config.ts')

        try {
          await fs.access(viteConfigPath)

          // Import the module-specific Vite configuration
          const moduleConfig = await import(viteConfigPath)

          // console.log('OKs : ', moduleConfig.paths)

          if (moduleConfig.paths && Array.isArray(moduleConfig.paths))
            paths.push(...moduleConfig.paths)
        }
        catch (error) {
          console.log('Error : ', error)

          // vite.config.js does not exist, skip this module
        }
      }
    }
  }
  catch (error) {
    console.error(`Error reading module statuses or module configurations: ${error}`)
  }

  // console.log('Path : ', paths)

  return paths
}
export { moduleAssetsPaths }

async function moduleRoutesFolder(modulesPath: any) {
  const moduleStatusesPath = path.join(__dirname, 'modules_statuses.json')

  const folders = [{
    src: 'resources/ts/pages',
    path: '',
  }]

  try {
    // Read module_statuses.json
    const moduleStatusesContent = await fs.readFile(moduleStatusesPath, 'utf-8')
    const moduleStatuses = JSON.parse(moduleStatusesContent)

    // Read module directories
    const moduleDirectories = await fs.readdir(modulesPath)

    for (const moduleDir of moduleDirectories) {
      if (moduleDir === '.DS_Store') {
        // Skip .DS_Store directory
        continue
      }

      // Check if the module is enabled (status is true)
      if (moduleStatuses[moduleDir] === true) {
        const mod = moduleDir.toLowerCase()

        const route = Object.assign({}, { src: path.join(modulesPath, moduleDir, 'resources/ts/pages'), path: `${mod}/` })

        try {
          folders.push(route)
        }
        catch (error) {
          console.error('Error : ', error)
        }
      }
    }
  }
  catch (error) {
    console.error(`Error reading module statuses or module configurations: ${error}`)
  }

  // console.log('Foldes : ', folders)

  return folders
}
export { moduleRoutesFolder }

async function getModuleAlias(modulesPath: any) {
  const moduleStatusesPath = path.join(__dirname, 'modules_statuses.json')

  let folders = {}

  try {
    // Read module_statuses.json
    const moduleStatusesContent = await fs.readFile(moduleStatusesPath, 'utf-8')
    const moduleStatuses = JSON.parse(moduleStatusesContent)

    // Read module directories
    const moduleDirectories = await fs.readdir(modulesPath)

    for (const moduleDir of moduleDirectories) {
      if (moduleDir === '.DS_Store') {
        // Skip .DS_Store directory
        continue
      }

      // Check if the module is enabled (status is true)
      if (moduleStatuses[moduleDir] === true) {
        const mod = moduleDir.toLowerCase()
        const moduleResources = new URL(path.join('./', modulesPath, moduleDir, 'resources/ts/'), import.meta.url)

        // '@images': fileURLToPath(new URL(moduleResources, import.meta.url)),
        // '@styles': fileURLToPath(new URL('./resources/styles/', import.meta.url))
        const alias = Object.assign({}, {
          [`@${mod}`]: fileURLToPath(moduleResources),
        })

        try {
          folders = Object.assign(folders, alias)
        }
        catch (error) {
          console.error('Error : ', error)
        }
      }
    }
  }
  catch (error) {
    console.error(`Error reading module statuses or module configurations: ${error}`)
  }

  // console.log('Foldes : ', folders)

  return folders
}
export { getModuleAlias }
