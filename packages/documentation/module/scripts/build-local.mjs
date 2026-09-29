import { existsSync, lstatSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { spawnSync } from 'node:child_process'

const output = fileURLToPath(new URL('../../../public/docs', import.meta.url))
if (existsSync(output) && lstatSync(output).isSymbolicLink())
  throw new Error('Ce site est géré par le CMS. Publiez depuis Documentation pour conserver les instantanés.')

const cli = fileURLToPath(new URL('../node_modules/vitepress/bin/vitepress.js', import.meta.url))
const result = spawnSync(process.execPath, [cli, 'build', 'docs', '--base', '/docs/', '--outDir', output], { stdio: 'inherit', cwd: fileURLToPath(new URL('../', import.meta.url)) })

process.exit(result.status ?? 1)
