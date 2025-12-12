/// <reference types="vite/client" />

const getModuleNames = (path: string) => {
  const parts = path.split('/')
  const moduleIndex = parts.indexOf('Modules')

  const moduleName = moduleIndex !== -1 ? parts[moduleIndex + 1] : 'default'

  return moduleName.toLowerCase()
}

export default async function loadMenuItems() {
  // Utilisation de import.meta.glob pour récupérer tous les fichiers menuItems.ts
  const modules = import.meta.glob('@modules/**/menuItems.json')
  const menuItems: Record<string, any> = {}

  // On itère sur chaque fichier trouvé pour l'importer
  await Promise.all(
    Object.keys(modules).map(async path => {
      const mod = await modules[path]() as { default: any }

      const moduleName = getModuleNames(path)

      menuItems[moduleName] = [...mod.default as Array<MenuItem>]
    }),
  )

  return menuItems
}
