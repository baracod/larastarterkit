import fs from 'node:fs'
import path from 'node:path'
import { execFileSync } from 'node:child_process'
import { createRequire } from 'node:module'

export const ICONS_MODULE = 'virtual:larastarterkit/icons.css'

// Regenerate only when the bundle is missing or older than its inputs (script, lockfile).
function isStale(target: string, inputs: string[]) {
  if (!fs.existsSync(target))
    return true
  const built = fs.statSync(target).mtimeMs

  return inputs.some(file => fs.existsSync(file) && fs.statSync(file).mtimeMs > built)
}

export function iconsPlugin(script: string, root = process.cwd()) {
  const target = path.resolve(root, '.larastarterkit/icons.css')

  return {
    name: 'baracod-generated-icons',
    enforce: 'pre' as const,
    buildStart() {
      if (!isStale(target, [script, path.resolve(root, 'pnpm-lock.yaml')]))
        return

      // Resolve tsx from the application, not from the (possibly symlinked) package location.
      const require = createRequire(path.resolve(root, 'package.json'))

      execFileSync(process.execPath, [require.resolve('tsx/cli'), script, target], { cwd: root, stdio: 'inherit' })
    },
    resolveId(id: string) {
      return id === ICONS_MODULE ? target : undefined
    },
  }
}
