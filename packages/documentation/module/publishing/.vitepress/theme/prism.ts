import Prism from 'prismjs'
import 'prismjs/components/prism-markup-templating'
import 'prismjs/components/prism-clike'
import 'prismjs/components/prism-c'
import 'prismjs/components/prism-csharp'
import 'prismjs/components/prism-php'
import 'prismjs/components/prism-javascript'
import 'prismjs/components/prism-typescript'
import 'prismjs/components/prism-jsx'
import 'prismjs/components/prism-tsx'
import 'prismjs/components/prism-bash'
import 'prismjs/components/prism-json'
import 'prismjs/components/prism-sql'
import 'prismjs/components/prism-yaml'
import 'prismjs/components/prism-toml'
import 'prismjs/components/prism-ini'
import 'prismjs/components/prism-python'
import 'prismjs/components/prism-go'
import 'prismjs/components/prism-rust'
import 'prismjs/components/prism-java'
import 'prismjs/components/prism-docker'
import 'prismjs/components/prism-nginx'
import 'prismjs/components/prism-powershell'
import 'prismjs/components/prism-diff'
import 'prismjs/components/prism-markdown'

const aliases: Record<string, string> = { vue: 'markup', html: 'markup', xml: 'markup', shell: 'bash', sh: 'bash', zsh: 'bash', js: 'javascript', ts: 'typescript', yml: 'yaml', dockerfile: 'docker', py: 'python', md: 'markdown', cs: 'csharp' }

/** Extracts the language, optional [title] and {1,3-5} highlighted lines of a fence info string. */
export function parseCodeInfo(info: string) {
  const language = (info.trim().split(/[\s[{]/)[0] || 'text').toLowerCase()
  const title = info.match(/\[([^\]]{1,120})\]/)?.[1]?.trim() || ''
  const lines = info.match(/\{([\d,\s-]{1,80})\}/)?.[1]?.replace(/\s+/g, '') || ''

  return { language, title, lines }
}

export function formatCodeInfo(meta: { language: string; title?: string; lines?: string }) {
  return [meta.language || 'text', meta.title ? `[${meta.title.replace(/[[\]]/g, '')}]` : '', meta.lines ? `{${meta.lines.replace(/[^\d,-]/g, '')}}` : ''].filter(Boolean).join(' ')
}

/** Expands "1,3-5" into a set of line numbers (bounded to avoid pathological ranges). */
export function lineSet(spec: string) {
  const lines = new Set<number>()
  for (const part of spec.split(',')) {
    const [start, end = start] = part.split('-').map(Number)
    if (!Number.isInteger(start) || !Number.isInteger(end) || start < 1 || end < start)
      continue
    for (let line = start; line <= Math.min(end, start + 500); line++)
      lines.add(line)
  }

  return lines
}

export function grammarFor(language: string) {
  const name = aliases[language] || language

  return Prism.languages[name] ? { name, grammar: Prism.languages[name] } : null
}

export { Prism }
