/** Turns a title into a URL segment compatible with the page path validation. */
export function slugify(value: string) {
  return value.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase()
    .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80)
}

export const pathPattern = /^[a-z0-9]+(?:[-/][a-z0-9]+)*$/

export function isValidPath(path: string) {
  return pathPattern.test(path) && !['index', '404', 'assets'].includes(path)
}

/** Words and reading time, ignoring fenced code and Markdown punctuation. */
export function textStats(markdown: string) {
  const code = (markdown.match(/```[\s\S]*?```/g) || []).length
  const prose = markdown.replace(/```[\s\S]*?```/g, ' ').replace(/!\[[^\]]*\]\([^)]*\)/g, ' ').replace(/[#>*_`~|[\]()-]/g, ' ')
  const words = prose.split(/\s+/).filter(word => /[\p{L}\p{N}]/u.test(word)).length

  return { words, characters: markdown.length, minutes: Math.max(1, Math.round(words / 220)), codeBlocks: code }
}

export interface OutlineItem { level: number; text: string; line: number }

/** Heading outline of a Markdown document (fenced code is skipped). */
export function outline(markdown: string): OutlineItem[] {
  const items: OutlineItem[] = []
  let fence = false
  markdown.split('\n').forEach((line, index) => {
    if (/^\s*(?:```|~~~)/.test(line))
      fence = !fence
    const match = !fence && line.match(/^(#{1,4})[ \t]+(\S.*)$/)
    if (match)
      items.push({ level: match[1].length, text: match[2].replace(/[ \t]#+[ \t]*$/, '').replace(/[*_`]/g, '').trim(), line: index })
  })

  return items
}

export interface DiffLine { type: 'same' | 'added' | 'removed'; text: string; left?: number; right?: number }

/** Line diff based on the longest common subsequence; large inputs degrade to a replace block. */
export function lineDiff(before: string, after: string): DiffLine[] {
  const a = before.split('\n')
  const b = after.split('\n')
  if (a.length * b.length > 4_000_000)
    return [...a.map((text, i) => ({ type: 'removed' as const, text, left: i + 1 })), ...b.map((text, i) => ({ type: 'added' as const, text, right: i + 1 }))]
  const table = Array.from({ length: a.length + 1 }, () => new Uint32Array(b.length + 1))
  for (let i = a.length - 1; i >= 0; i--) {
    for (let j = b.length - 1; j >= 0; j--)
      table[i][j] = a[i] === b[j] ? table[i + 1][j + 1] + 1 : Math.max(table[i + 1][j], table[i][j + 1])
  }
  const result: DiffLine[] = []
  let i = 0
  let j = 0
  while (i < a.length || j < b.length) {
    if (i < a.length && j < b.length && a[i] === b[j])
      result.push({ type: 'same', text: a[i], left: ++i, right: ++j })

    else if (j < b.length && (i >= a.length || table[i][j + 1] >= table[i + 1][j]))
      result.push({ type: 'added', text: b[j], right: ++j })

    else
      result.push({ type: 'removed', text: a[i], left: ++i })
  }

  return result
}

export function diffSummary(lines: DiffLine[]) {
  return { added: lines.filter(line => line.type === 'added').length, removed: lines.filter(line => line.type === 'removed').length }
}
